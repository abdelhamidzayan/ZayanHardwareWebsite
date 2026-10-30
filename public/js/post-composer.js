'use strict';
(() => {
    const form = document.querySelector('[data-post-composer]');
    if (!form) return;

    const csrf = form.querySelector('[name="_token"]')?.value || '';
    const saveState = form.querySelector('[data-save-state]');
    const saveLabel = form.querySelector('[data-save-label]');
    const draftIdInput = form.querySelector('[data-draft-id]');
    const revisionInput = form.querySelector('[data-draft-revision]');
    const titleInput = form.querySelector('[name="title"]');
    const detailsInput = form.querySelector('[data-details]');
    const typeRadios = Array.from(form.querySelectorAll('[name="content_type"]'));
    const modeRadios = Array.from(form.querySelectorAll('[name="details_mode"]'));
    const textMode = form.querySelector('[data-text-mode]');
    const audioMode = form.querySelector('[data-audio-mode]');
    const categoryWrap = form.querySelector('[data-help-category]');
    const categoryInput = form.querySelector('[data-category]');
    const manufacturerSelect = form.querySelector('[data-manufacturer]');
    const manufacturerMark = form.querySelector('[data-manufacturer-mark]');
    const manufacturerName = form.querySelector('[data-manufacturer-name]');
    const brandTrigger = form.querySelector('[data-brand-trigger]');
    const brandOptions = form.querySelector('[data-brand-options]');
    const modelQuery = form.querySelector('[data-model-query]');
    const modelIdInput = form.querySelector('[data-model-id]');
    const modelResults = form.querySelector('[data-model-results]');
    const suggestButton = form.querySelector('[data-open-model-suggest]');
    const suggestDialog = document.querySelector('[data-model-suggest-dialog]');
    const suggestForm = document.querySelector('[data-model-suggest-form]');
    const suggestMessage = document.querySelector('[data-suggest-message]');
    const mediaInput = form.querySelector('[data-media-input]');
    const mediaList = form.querySelector('[data-media-list]');
    const stepList = form.querySelector('[data-step-list]');
    const publishButton = form.querySelector('[data-publish-button]');
    const modelsUrl = form.dataset.modelsUrl || '';
    const suggestUrl = form.dataset.suggestUrl || '';
    const mediaBase = form.dataset.mediaBase || '';
    const saveUrl = form.dataset.saveUrl || '';
    let revision = Number(revisionInput.value || 0);
    let draftId = draftIdInput.value || '';
    let media = [];
    let audioMeta = null;
    let saveTimer = null;
    let saving = false;
    let dirty = false;
    let initialized = false;
    let conflict = false;
    const pendingDeletes = new Set();
    let currentAbort = null;
    let dbPromise = null;
    let audioChunkSeq = 0;
    let saveQueued = false;

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        return Array.from(bytes, function (b, i) {
            const value = b.toString(16).padStart(2, '0');
            return [4, 6, 8, 10].includes(i) ? '-' + value : value;
        }).join('');
    }

    function setStatus(text, status) {
        if (saveLabel) saveLabel.textContent = text;
        if (saveState) {
            saveState.dataset.status = status || 'idle';
            saveState.classList.toggle('is-saving', status === 'saving');
            saveState.classList.toggle('is-saved', status === 'saved');
            saveState.classList.toggle('is-error', status === 'error' || status === 'conflict');
        }
    }

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise(function (resolve, reject) {
            if (!('indexedDB' in window)) {
                reject(new Error('التخزين المحلي غير متاح في هذا المتصفح.'));
                return;
            }
            const request = indexedDB.open('zayan-post-drafts', 2);
            request.onupgradeneeded = function () {
                const db = request.result;
                if (!db.objectStoreNames.contains('drafts')) db.createObjectStore('drafts', { keyPath: 'id' });
                if (!db.objectStoreNames.contains('media')) db.createObjectStore('media', { keyPath: 'key' });
                if (!db.objectStoreNames.contains('audioSessions')) db.createObjectStore('audioSessions', { keyPath: 'key' });
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error || new Error('تعذر فتح التخزين المحلي.')); };
        });
        return dbPromise;
    }

    async function dbGet(storeName, key) {
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction(storeName, 'readonly');
            const request = transaction.objectStore(storeName).get(key);
            request.onsuccess = function () { resolve(request.result || null); };
            request.onerror = function () { reject(request.error); };
        });
    }

    async function dbPut(storeName, value) {
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction(storeName, 'readwrite');
            transaction.objectStore(storeName).put(value);
            transaction.oncomplete = function () { resolve(true); };
            transaction.onerror = function () { reject(transaction.error); };
            transaction.onabort = function () { reject(transaction.error); };
        });
    }

    async function dbGetAll(storeName) {
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction(storeName, 'readonly');
            const request = transaction.objectStore(storeName).getAll();
            request.onsuccess = function () { resolve(request.result || []); };
            request.onerror = function () { reject(request.error); };
        });
    }

    async function clearAudioSession() {
        const records = await dbGetAll('audioSessions').catch(function () { return []; });
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction('audioSessions', 'readwrite');
            const store = transaction.objectStore('audioSessions');
            records.filter(function (item) { return item.draft_id === draftId; }).forEach(function (item) {
                store.delete(item.key);
            });
            transaction.oncomplete = function () { audioChunkSeq = 0; resolve(true); };
            transaction.onerror = function () { reject(transaction.error); };
        });
    }

    async function pendingAudioSession() {
        const records = (await dbGetAll('audioSessions')).filter(function (item) {
            return item.draft_id === draftId;
        }).sort(function (left, right) { return Number(left.sequence || 0) - Number(right.sequence || 0); });
        const chunks = records.filter(function (item) { return item.record_type === 'chunk' && item.blob; });
        const meta = records.find(function (item) { return item.record_type === 'meta'; }) || null;
        audioChunkSeq = chunks.reduce(function (max, item) { return Math.max(max, Number(item.sequence || 0)); }, 0);
        if (!chunks.length) return null;
        return {
            blob: new Blob(chunks.map(function (item) { return item.blob; }), { type: meta?.mime_type || chunks[0].mime_type || 'audio/webm' }),
            duration_ms: Number(meta?.duration_ms || 0),
            mime_type: meta?.mime_type || chunks[0].mime_type || 'audio/webm'
        };
    }

    async function dbDelete(storeName, key) {
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction(storeName, 'readwrite');
            transaction.objectStore(storeName).delete(key);
            transaction.oncomplete = function () { resolve(true); };
            transaction.onerror = function () { reject(transaction.error); };
        });
    }

    function getType() {
        return form.querySelector('[name="content_type"]:checked')?.value || 'technical_post';
    }

    function getMode() {
        return form.querySelector('[name="details_mode"]:checked')?.value || 'text';
    }

    function collectSteps() {
        return Array.from(stepList.querySelectorAll('[data-step-input]'))
            .map(function (input) { return input.value.trim(); })
            .filter(Boolean);
    }

    function collectPayload() {
        return {
            content_type: getType(),
            title: titleInput.value.trim(),
            manufacturer_id: manufacturerSelect.value,
            device_model_id: modelIdInput.value,
            device_model_label: modelQuery.value.trim(),
            category: categoryInput ? categoryInput.value : '',
            details_mode: getMode(),
            description: getMode() === 'text' ? (detailsInput.value || '') : '',
            steps: collectSteps(),
            audio: getMode() === 'audio' ? audioMeta : null
        };
    }

    function createLocalRecord() {
        return {
            id: draftId,
            serverRevision: revision,
            payload: collectPayload(),
            media: media.map(function (item) {
                return {
                    media_uuid: item.media_uuid || '',
                    local_id: item.local_id,
                    media_kind: item.media_kind,
                    mime_type: item.mime_type,
                    file_size: item.file_size,
                    sort_order: item.sort_order,
                    original_url: item.original_url || item.url || '',
                    editor_state: item.editor_state || null,
                    needs_render: Boolean(item.needsRender),
                    url: item.url || '',
                    uploaded: Boolean(item.media_uuid)
                };
            }),
            dirty: dirty,
            pendingDeletes: Array.from(pendingDeletes),
            updatedAt: Date.now()
        };
    }

    async function persistLocal() {
        if (!draftId) return;
        try {
            await dbPut('drafts', createLocalRecord());
            setStatus(navigator.onLine ? 'تم الحفظ على هذا الجهاز · جاري المزامنة' : 'محفوظ على هذا الجهاز · غير متصل', 'saved');
        } catch (error) {
            setStatus('تعذر الحفظ محليًا: ' + (error.message || ''), 'error');
        }
    }

    async function persistBlob(item) {
        if (!item.local_id) return;
        await dbPut('media', {
            key: draftId + ':' + item.local_id,
            draft_id: draftId,
            local_id: item.local_id,
            media_uuid: item.media_uuid || '',
            media_kind: item.media_kind,
            mime_type: item.mime_type,
            file_size: item.file_size,
            blob: item.blob || null,
            rendered_blob: item.renderedBlob || null,
            editor_state: item.editor_state || null,
            needs_render: Boolean(item.needsRender)
        });
    }

    async function clearLocalMediaForDraft(id) {
        const records = await dbGetAll('media');
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction('media', 'readwrite');
            const store = transaction.objectStore('media');
            records.filter(function (item) { return item.draft_id === id; }).forEach(function (item) { store.delete(item.key); });
            transaction.oncomplete = function () { resolve(true); };
            transaction.onerror = function () { reject(transaction.error); };
        });
    }

    async function loadLocalMedia() {
        const db = await openDb();
        return new Promise(function (resolve, reject) {
            const transaction = db.transaction('media', 'readonly');
            const request = transaction.objectStore('media').getAll();
            request.onsuccess = function () {
                resolve((request.result || []).filter(function (item) { return item.draft_id === draftId; }));
            };
            request.onerror = function () { reject(request.error); };
        });
    }

    function renderSteps() {
        const rows = Array.from(stepList.querySelectorAll('[data-step-row]'));
        rows.forEach(function (row, index) {
            row.querySelector('.post-composer__step-number').textContent = String(index + 1);
        });
    }

    function addStep(value) {
        const row = document.createElement('div');
        row.className = 'post-composer__step-row';
        row.dataset.stepRow = '';
        const number = document.createElement('span');
        number.className = 'post-composer__step-number';
        const textarea = document.createElement('textarea');
        textarea.rows = 1;
        textarea.maxLength = 2000;
        textarea.dataset.stepInput = '';
        textarea.placeholder = 'اكتب الفحص أو التجربة...';
        textarea.value = value || '';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.removeStep = '';
        remove.setAttribute('aria-label', 'حذف الخطوة');
        remove.textContent = '×';
        row.append(number, textarea, remove);
        stepList.appendChild(row);
        renderSteps();
        textarea.focus();
        markDirty();
    }

    function renderManufacturerMark() {
        const option = brandOptions.querySelector('[data-id="' + CSS.escape(manufacturerSelect.value) + '"]');
        const name = option ? (option.dataset.name || '') : 'اختار الشركة';
        manufacturerMark.textContent = option ? (option.dataset.monogram || name.slice(0, 2)) : '؟';
        manufacturerMark.dataset.slug = option?.dataset.slug || '';
        manufacturerName.textContent = name;
        brandOptions.querySelectorAll('[data-manufacturer-option]').forEach(function (button) {
            button.classList.toggle('is-selected', button.dataset.id === manufacturerSelect.value);
        });
    }

    async function searchModels(query) {
        if (!manufacturerSelect.value || query.trim().length < 1) {
            modelResults.hidden = true;
            modelResults.replaceChildren();
            suggestButton.hidden = true;
            return;
        }
        if (currentAbort) currentAbort.abort();
        currentAbort = new AbortController();
        const params = new URLSearchParams({ manufacturer_id: manufacturerSelect.value, q: query });
        try {
            const response = await fetch(modelsUrl + '?' + params.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: currentAbort.signal
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'تعذر البحث عن الموديل.');
            modelResults.replaceChildren();
            (result.models || []).forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'post-composer__model-result';
                const main = document.createElement('strong');
                main.textContent = item.model_code ? item.model_code : item.commercial_name;
                const secondary = document.createElement('small');
                secondary.textContent = item.model_code ? item.commercial_name : 'اسم الجهاز المسجل';
                button.append(main, secondary);
                button.addEventListener('click', function () {
                    modelIdInput.value = String(item.id);
                    modelQuery.value = item.model_code ? item.model_code : item.commercial_name;
                    modelResults.hidden = true;
                    suggestButton.hidden = true;
                    markDirty();
                });
                modelResults.appendChild(button);
            });
            modelResults.hidden = !(result.models || []).length;
            suggestButton.hidden = (result.models || []).length > 0;
        } catch (error) {
            if (error.name !== 'AbortError') setStatus('تعذر البحث الآن. حاول مرة أخرى.', 'error');
        }
    }

    function renderMedia() {
        mediaList.replaceChildren();
        media.filter(function (item) { return item.media_kind !== 'audio'; }).forEach(function (item, index) {
            const card = document.createElement('article');
            card.className = 'post-composer__media-item';
            card.dataset.mediaItem = '';
            card.dataset.mediaUuid = item.media_uuid || item.local_id;
            card.dataset.kind = item.media_kind;
            card.draggable = true;
            card.addEventListener('dragstart', function (event) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(item.media_uuid || item.local_id));
                card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('is-dragging');
                mediaList.querySelectorAll('.is-drag-over').forEach(function (node) { node.classList.remove('is-drag-over'); });
            });
            card.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                card.classList.add('is-drag-over');
            });
            card.addEventListener('dragleave', function () { card.classList.remove('is-drag-over'); });
            card.addEventListener('drop', function (event) {
                event.preventDefault();
                card.classList.remove('is-drag-over');
                const sourceId = event.dataTransfer.getData('text/plain');
                const targetId = String(item.media_uuid || item.local_id);
                if (sourceId && sourceId !== targetId) reorderMedia(sourceId, targetId);
            });
            if (item.media_kind === 'image') {
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = 'مرفق فني';
                img.loading = 'lazy';
                card.appendChild(img);
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.textContent = 'تعديل الصورة';
                edit.dataset.editImage = '';
                edit.addEventListener('click', function () {
                    if (window.ZayanImageEditor) {
                        window.ZayanImageEditor.open(item, async function (result) {
                            item.editor_state = result.state;
                            item.renderedBlob = result.renderedBlob || null;
                            item.needsRender = false;
                            if (item.renderedBlob) {
                                if (item.url && item.url.startsWith('blob:')) URL.revokeObjectURL(item.url);
                                item.url = URL.createObjectURL(item.renderedBlob);
                            }
                            item.dirtyEditor = true;
                            markDirty();
                            await persistBlob(item);
                            await saveMediaState(item, index);
                            renderMedia();
                        });
                    }
                });
                card.appendChild(edit);
            } else {
                const video = document.createElement('video');
                video.src = item.url;
                video.muted = true;
                video.playsInline = true;
                video.preload = 'metadata';
                card.appendChild(video);
                const label = document.createElement('span');
                label.className = 'post-composer__video-label';
                label.textContent = 'فيديو';
                card.appendChild(label);
            }

            const tools = document.createElement('div');
            tools.className = 'post-composer__media-tools';
            if (index > 0) {
                const up = document.createElement('button');
                up.type = 'button';
                up.textContent = '↑';
                up.setAttribute('aria-label', 'تقديم');
                up.addEventListener('click', function () { moveMedia(index, -1); });
                tools.appendChild(up);
            }
            if (index < media.filter(function (entry) { return entry.media_kind !== 'audio'; }).length - 1) {
                const down = document.createElement('button');
                down.type = 'button';
                down.textContent = '↓';
                down.setAttribute('aria-label', 'تأخير');
                down.addEventListener('click', function () { moveMedia(index, 1); });
                tools.appendChild(down);
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'حذف المرفق');
            remove.addEventListener('click', function () { removeMedia(item); });
            tools.appendChild(remove);
            card.appendChild(tools);
            mediaList.appendChild(card);
        });
    }

    function reorderMedia(sourceId, targetId) {
        const visible = media.filter(function (item) { return item.media_kind !== 'audio'; });
        const from = visible.findIndex(function (item) { return String(item.media_uuid || item.local_id) === sourceId; });
        const to = visible.findIndex(function (item) { return String(item.media_uuid || item.local_id) === targetId; });
        if (from < 0 || to < 0 || from === to) return;
        const [moved] = visible.splice(from, 1);
        visible.splice(to, 0, moved);
        let position = 0;
        media = media.map(function (item) {
            return item.media_kind === 'audio' ? item : visible[position++];
        });
        media.forEach(function (item, index) { item.sort_order = index; });
        media.forEach(function (item, index) { item.dirtyEditor = true; });
        renderMedia();
        markDirty();
    }

    function moveMedia(index, direction) {
        const visible = media.filter(function (item) { return item.media_kind !== 'audio'; });
        const item = visible[index];
        const target = index + direction;
        if (!item || target < 0 || target >= visible.length) return;
        const other = visible[target];
        const actualA = media.indexOf(item);
        const actualB = media.indexOf(other);
        media[actualA] = other;
        media[actualB] = item;
        media.forEach(function (entry, i) { entry.sort_order = i; });
        media.forEach(function (entry) { entry.dirtyEditor = true; });
        renderMedia();
        markDirty();
    }

    async function removeMedia(item) {
        if (item.media_uuid) {
            if (navigator.onLine) {
                const body = new URLSearchParams({ _token: csrf, revision: String(revision) });
                try {
                    const response = await fetch(mediaBase + '/' + encodeURIComponent(draftId) + '/media/' + encodeURIComponent(item.media_uuid) + '/delete', {
                        method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    if (handleRevisionConflict(response, result)) return false;
                    if (!response.ok && response.status !== 404) throw new Error('تعذر حذف المرفق من المسودة.');
                    if (response.ok) updateDraftRevision(result);
                } catch (_) {
                    pendingDeletes.add(item.media_uuid);
                }
            } else {
                pendingDeletes.add(item.media_uuid);
            }
        }
        media = media.filter(function (entry) { return entry !== item; });
        if (item.url && item.url.startsWith('blob:')) URL.revokeObjectURL(item.url);
        if (item.local_id) await dbDelete('media', draftId + ':' + item.local_id).catch(function () {});
        if (item.media_kind === 'audio') {
            audioMeta = null;
            window.dispatchEvent(new CustomEvent('zayan-audio-removed'));
        }
        renderMedia();
        markDirty();
        return true;
    }

    async function syncPendingDeletes() {
        if (!navigator.onLine || !pendingDeletes.size) return true;
        for (const mediaUuid of Array.from(pendingDeletes)) {
            const body = new URLSearchParams({ _token: csrf, revision: String(revision) });
            try {
                const response = await fetch(
                    mediaBase + '/' + encodeURIComponent(draftId) + '/media/' + encodeURIComponent(mediaUuid) + '/delete',
                    { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }
                );
                const result = await response.json();
                if (handleRevisionConflict(response, result)) return false;
                if (response.ok) updateDraftRevision(result);
                if (response.ok || response.status === 404) pendingDeletes.delete(mediaUuid);
                else return false;
            } catch (_) {
                return false;
            }
        }
        return true;
    }

    async function uploadItem(item) {
        if (!item.blob || item.media_uuid) return true;
        if (!navigator.onLine) return false;
        const data = new FormData();
        data.append('_token', csrf);
        data.append('revision', String(revision));
        data.append('media[]', item.blob, item.fileName || ('attachment.' + extensionFor(item.mime_type)));
        try {
            const response = await fetch(mediaBase + '/' + encodeURIComponent(draftId) + '/media', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (handleRevisionConflict(response, result)) return false;
            if (!response.ok || !result.media || !result.media[0]) {
                throw new Error(result.message || 'تعذر مزامنة المرفق.');
            }
            updateDraftRevision(result);
            Object.assign(item, result.media[0]);
            item.original_url = result.media[0].original_url || result.media[0].url;
            item.uploaded = true;
            await persistBlob(item);
            return true;
        } catch (error) {
            setStatus(error.message || 'تعذر مزامنة المرفق.', 'error');
            return false;
        }
    }

    function updateDraftRevision(result) {
        const nextRevision = Number(result && result.revision);
        if (Number.isInteger(nextRevision) && nextRevision >= 0) {
            revision = nextRevision;
            revisionInput.value = String(revision);
        }
    }

    function handleRevisionConflict(response, result) {
        if (response.status !== 409 || !result || result.error !== 'revision_conflict') return false;
        conflict = true;
        showConflict(result);
        setStatus('المسودة اتعدلت من جهاز آخر. لم نستبدل أي تغييرات.', 'conflict');
        return true;
    }

    function extensionFor(mime) {
        const map = {
            'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp',
            'video/mp4': 'mp4', 'video/webm': 'webm', 'video/quicktime': 'mov',
            'audio/webm': 'webm', 'audio/ogg': 'ogg', 'audio/mpeg': 'mp3',
            'audio/wav': 'wav', 'audio/x-wav': 'wav', 'audio/mp4': 'm4a', 'audio/aac': 'aac'
        };
        return map[mime] || 'bin';
    }

    async function saveMediaState(item, index) {
        if (!item.media_uuid) {
            markDirty();
            return true;
        }
        try {
            if (item.renderedBlob) {
                const renderedData = new FormData();
                renderedData.append('_token', csrf);
                renderedData.append('revision', String(revision));
                renderedData.append('rendered', item.renderedBlob, 'edited-image.webp');
                const renderedResponse = await fetch(
                    mediaBase + '/' + encodeURIComponent(draftId) + '/media/' + encodeURIComponent(item.media_uuid) + '/rendered',
                    { method: 'POST', body: renderedData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }
                );
                const renderedResult = await renderedResponse.json();
                if (handleRevisionConflict(renderedResponse, renderedResult)) return false;
                if (!renderedResponse.ok) throw new Error(renderedResult.message || 'تعذر حفظ نسخة الصورة المعدلة.');
                updateDraftRevision(renderedResult);
                item.url = renderedResult.rendered_url;
                item.original_url = renderedResult.original_url;
                item.renderedBlob = null;
                item.needsRender = false;
                item.rendered = true;
            }
            const body = new URLSearchParams({
                _token: csrf,
                revision: String(revision),
                editor_state: JSON.stringify(item.editor_state || {}),
                sort_order: String(index)
            });
            const response = await fetch(
                mediaBase + '/' + encodeURIComponent(draftId) + '/media/' + encodeURIComponent(item.media_uuid) + '/editor',
                { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }
            );
            const result = await response.json();
            if (handleRevisionConflict(response, result)) return false;
            if (!response.ok) throw new Error(result.message || 'تعذر حفظ تعديلات الصورة.');
            updateDraftRevision(result);
            item.sort_order = index;
            item.dirtyEditor = false;
            await persistBlob(item);
            await persistLocal();
            return true;
        } catch (error) {
            dirty = true;
            await persistLocal();
            setStatus(error.message || 'تعذر حفظ التعديلات.', 'error');
            return false;
        }
    }

    async function syncAllEditorStates() {
        for (let i = 0; i < media.length; i++) {
            if (media[i].media_uuid && !(await saveMediaState(media[i], i))) return false;
        }
        return true;
    }

    async function createServerDraftIfNeeded() {
        if (!draftId) {
            draftId = uuid();
            draftIdInput.value = draftId;
            const url = new URL(window.location.href);
            url.searchParams.set('draft', draftId);
            history.replaceState({}, '', url.toString());
        }
        return true;
    }

    async function syncServer(force) {
        if (conflict) return false;
        if (saving) { saveQueued = true; return false; }
        if (!navigator.onLine) {
            await persistLocal();
            return !force;
        }
        saving = true;
        setStatus('جاري حفظ المسودة...', 'saving');
        try {
            await createServerDraftIfNeeded();
            if (!(await syncPendingDeletes())) throw new Error('فيه مرفقات محذوفة محليًا ولم تكتمل مزامنة حذفها.');
            const body = new URLSearchParams();
            body.set('_token', csrf);
            body.set('draft_id', draftId);
            body.set('revision', String(revision));
            body.set('payload_json', JSON.stringify(collectPayload()));
            const response = await fetch(saveUrl, {
                method: 'POST', body, credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (response.status === 409 && result.error === 'revision_conflict') {
                conflict = true;
                showConflict(result.server || null);
                setStatus('فيه نسخة أحدث من جهاز آخر. لم نستبدل أي تعديلات.', 'conflict');
                return false;
            }
            if (!response.ok) throw new Error(result.message || 'تعذر حفظ المسودة على الحساب.');
            revision = Number(result.revision || revision);
            revisionInput.value = String(revision);
            draftIdInput.value = draftId;
            dirty = false;
            await persistLocal();
            setStatus('تم الحفظ والمزامنة', 'saved');

            for (const item of media) {
                if (!item.media_uuid) {
                    const uploaded = await uploadItem(item);
                    if (!uploaded) { dirty = true; await persistLocal(); return false; }
                }
            }
            if (!(await syncAllEditorStates())) {
                dirty = true;
                await persistLocal();
                return false;
            }
            setStatus('تم الحفظ والمزامنة', 'saved');
            return true;
        } catch (error) {
            setStatus(error.message || 'تعذر الاتصال بالخادم. النسخة المحلية محفوظة.', 'error');
            await persistLocal();
            return false;
        } finally {
            saving = false;
            if (saveQueued && !conflict) {
                saveQueued = false;
                queueMicrotask(function () { syncServer(true); });
            }
        }
    }

    function markDirty() {
        dirty = true;
        if (!initialized) return;
        persistLocal();
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () { syncServer(false); }, 900);
    }

    function showConflict(server) {
        let box = form.querySelector('[data-draft-conflict]');
        if (!box) {
            box = document.createElement('section');
            box.className = 'post-composer__conflict';
            box.dataset.draftConflict = '';
            const message = document.createElement('p');
            message.textContent = 'المسودة اتعدلت من جهاز آخر. اختار أي نسخة تكمل عليها. نسختك المحلية محفوظة كما هي.';
            const useServer = document.createElement('button');
            useServer.type = 'button';
            useServer.className = 'btn btn--secondary';
            useServer.textContent = 'استخدام نسخة الحساب';
            const keepLocal = document.createElement('button');
            keepLocal.type = 'button';
            keepLocal.className = 'btn btn--primary';
            keepLocal.textContent = 'الاحتفاظ بنسختي كمسودة جديدة';
            useServer.addEventListener('click', async function () {
                if (!server) return;
                try {
                    const response = await fetch(mediaBase + '/' + encodeURIComponent(draftId) + '/data', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) throw new Error('تعذر تحميل نسخة الحساب كاملة.');
                    const latest = await response.json();
                    await applyServerData(latest);
                    await clearLocalMediaForDraft(draftId);
                    await clearAudioSession();
                    revisionInput.value = String(revision);
                    pendingDeletes.clear();
                    conflict = false;
                    dirty = false;
                    box.remove();
                    await persistLocal();
                    setStatus('تم استرجاع نسخة الحساب كاملة', 'saved');
                } catch (error) {
                    setStatus(error.message || 'تعذر استرجاع النسخة الأحدث.', 'error');
                }
            });
            keepLocal.addEventListener('click', async function () {
                const hasRemoteOnlyMedia = media.some(function (item) {
                    return Boolean(item.media_uuid) && !item.blob;
                });
                if (hasRemoteOnlyMedia) {
                    setStatus('لا يمكن إنشاء نسخة محلية جديدة لأن بعض المرفقات لا توجد على هذا الجهاز. اختار نسخة الحساب للحفاظ عليها.', 'error');
                    return;
                }
                const oldDraftId = draftId;
                draftId = uuid();
                draftIdInput.value = draftId;
                revision = 0;
                revisionInput.value = '0';
                pendingDeletes.clear();
                media.forEach(function (item) {
                    item.media_uuid = '';
                    item.uploaded = false;
                    if (item.blob) {
                        item.url = URL.createObjectURL(item.renderedBlob || item.blob);
                        item.original_url = URL.createObjectURL(item.blob);
                    }
                });
                conflict = false;
                box.remove();
                const url = new URL(window.location.href);
                url.searchParams.set('draft', draftId);
                history.replaceState({}, '', url.toString());
                for (const item of media) {
                    if (item.blob) await persistBlob(item);
                }
                await persistLocal();
                await syncServer(true);
                setStatus('تم إنشاء مسودة مستقلة من تعديلاتك', 'saved');
            });
            box.append(message, useServer, keepLocal);
            form.insertBefore(box, form.firstChild);
        }
    }

    function applyPayload(payload) {
        if (payload.content_type) {
            const radio = typeRadios.find(function (item) { return item.value === payload.content_type; });
            if (radio) radio.checked = true;
        }
        if (payload.details_mode) {
            const radio = modeRadios.find(function (item) { return item.value === payload.details_mode; });
            if (radio) radio.checked = true;
        }
        titleInput.value = payload.title || '';
        manufacturerSelect.value = payload.manufacturer_id || '';
        modelIdInput.value = payload.device_model_id || '';
        modelQuery.value = payload.device_model_label || '';
        if (categoryInput) categoryInput.value = payload.category || '';
        detailsInput.value = payload.description || '';
        stepList.replaceChildren();
        (payload.steps || []).forEach(function (step) { addStepWithoutFocus(step); });
        audioMeta = payload.audio || null;
        syncModeVisibility();
        renderSteps();
        renderManufacturerMark();
        updateTitleCount();
    }

    function addStepWithoutFocus(value) {
        const row = document.createElement('div');
        row.className = 'post-composer__step-row';
        row.dataset.stepRow = '';
        const number = document.createElement('span');
        number.className = 'post-composer__step-number';
        const textarea = document.createElement('textarea');
        textarea.rows = 1;
        textarea.maxLength = 2000;
        textarea.dataset.stepInput = '';
        textarea.placeholder = 'اكتب الفحص أو التجربة...';
        textarea.value = value || '';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.removeStep = '';
        remove.setAttribute('aria-label', 'حذف الخطوة');
        remove.textContent = '×';
        row.append(number, textarea, remove);
        stepList.appendChild(row);
    }

    function syncModeVisibility() {
        const mode = getMode();
        textMode.hidden = mode !== 'text';
        audioMode.hidden = mode !== 'audio';
        detailsInput.required = mode === 'text';
        detailsInput.disabled = mode !== 'text';
        categoryWrap.hidden = getType() !== 'help_request';
        if (categoryInput) categoryInput.required = getType() === 'help_request';
        typeRadios.forEach(function (radio) {
            radio.closest('.post-composer__type')?.classList.toggle('is-selected', radio.checked);
        });
        modeRadios.forEach(function (radio) {
            radio.closest('.post-composer__mode')?.classList.toggle('is-selected', radio.checked);
        });
    }

    function updateTitleCount() {
        const count = form.querySelector('[data-title-count]');
        if (count) count.textContent = String(titleInput.value.length);
    }

    function applyFormat(format) {
        const start = detailsInput.selectionStart;
        const end = detailsInput.selectionEnd;
        const selected = detailsInput.value.slice(start, end);
        const wrappers = {
            bold: ['**', '**'],
            heading: ['### ', ''],
            numbered: ['1. ', ''],
            bulleted: ['• ', ''],
            divider: ['\n────────────\n', '']
        };
        const pair = wrappers[format];
        if (!pair) return;
        const replacement = pair[0] + (selected || 'نص') + pair[1];
        detailsInput.setRangeText(replacement, start, end, 'end');
        detailsInput.focus();
        markDirty();
    }

    brandTrigger.addEventListener('click', function () {
        const open = brandOptions.hidden;
        brandOptions.hidden = !open;
        brandTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    brandOptions.querySelectorAll('[data-manufacturer-option]').forEach(function (button) {
        button.addEventListener('click', function () {
            manufacturerSelect.value = button.dataset.id || '';
            manufacturerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            brandOptions.hidden = true;
            brandTrigger.setAttribute('aria-expanded', 'false');
        });
    });
    manufacturerSelect.addEventListener('change', function () {
        modelQuery.value = '';
        modelIdInput.value = '';
        modelResults.hidden = true;
        suggestButton.hidden = true;
        renderManufacturerMark();
        markDirty();
    });
    let modelTimer = null;
    modelQuery.addEventListener('input', function () {
        modelIdInput.value = '';
        clearTimeout(modelTimer);
        modelTimer = setTimeout(function () { searchModels(modelQuery.value); }, 250);
        markDirty();
    });
    modelQuery.addEventListener('focus', function () {
        if (modelQuery.value) searchModels(modelQuery.value);
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.post-composer__model-field')) modelResults.hidden = true;
        if (!event.target.closest('[data-brand-picker]')) { brandOptions.hidden = true; brandTrigger.setAttribute('aria-expanded', 'false'); }
    });
    suggestButton.addEventListener('click', function () {
        if (!manufacturerSelect.value) return;
        suggestForm.dataset.manufacturerId = manufacturerSelect.value;
        suggestMessage.textContent = '';
        suggestDialog.showModal();
    });
    document.querySelector('[data-close-suggest]').addEventListener('click', function () { suggestDialog.close(); });
    suggestForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const data = new FormData(suggestForm);
        data.set('_token', csrf);
        data.set('manufacturer_id', suggestForm.dataset.manufacturerId || manufacturerSelect.value);
        const button = suggestForm.querySelector('[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(suggestUrl, { method: 'POST', body: new URLSearchParams(data), credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'تعذر إرسال الاقتراح.');
            suggestMessage.textContent = result.message;
            suggestForm.reset();
            setTimeout(function () { suggestDialog.close(); }, 1800);
        } catch (error) {
            suggestMessage.textContent = error.message || 'تعذر إرسال الاقتراح.';
        } finally {
            button.disabled = false;
        }
    });

    form.addEventListener('click', function (event) {
        const formatButton = event.target.closest('[data-format]');
        if (formatButton) applyFormat(formatButton.dataset.format);
    });
    titleInput.addEventListener('input', function () { updateTitleCount(); markDirty(); });
    detailsInput.addEventListener('input', markDirty);
    categoryInput?.addEventListener('change', markDirty);
    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () { syncModeVisibility(); markDirty(); });
    });
    modeRadios.forEach(function (radio) {
        radio.addEventListener('change', async function () {
            if (radio.checked && radio.value === 'audio' && detailsInput.value.trim()) {
                if (!window.confirm('الكتابة والتسجيل اختيار واحد. التحويل للتسجيل هيمسح نص التفاصيل الحالي. تكمل؟')) {
                    modeRadios.find(function (item) { return item.value === 'text'; }).checked = true;
                    return;
                }
                detailsInput.value = '';
            }
            if (radio.checked && radio.value === 'text' && media.some(function (item) { return item.media_kind === 'audio'; })) {
                if (!window.confirm('التحويل للكتابة هيحذف التسجيل الصوتي الحالي. تكمل؟')) {
                    modeRadios.find(function (item) { return item.value === 'audio'; }).checked = true;
                    return;
                }
                const audio = media.find(function (item) { return item.media_kind === 'audio'; });
                if (audio && !(await removeMedia(audio))) {
                    modeRadios.find(function (item) { return item.value === 'audio'; }).checked = true;
                    syncModeVisibility();
                    return;
                }
            }
            syncModeVisibility();
            markDirty();
        });
    });

    form.addEventListener('click', function (event) {
        if (event.target.closest('[data-add-step]')) addStep('');
        const remove = event.target.closest('[data-remove-step]');
        if (remove) {
            remove.closest('[data-step-row]')?.remove();
            renderSteps();
            markDirty();
        }
    });
    stepList.addEventListener('input', markDirty);
    mediaInput.addEventListener('change', async function () {
        const incoming = Array.from(mediaInput.files || []);
        mediaInput.value = '';
        for (const file of incoming) {
            if (media.filter(function (item) { return item.media_kind !== 'audio'; }).length >= 8) {
                setStatus('الحد الأقصى 8 صور وفيديوهات لكل منشور.', 'error');
                break;
            }
            const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'];
            if (!validTypes.includes(file.type)) {
                setStatus('نوع الملف غير مدعوم.', 'error');
                continue;
            }
            const localId = uuid();
            const kind = file.type.startsWith('image/') ? 'image' : 'video';
            const item = {
                local_id: localId,
                media_uuid: '',
                media_kind: kind,
                mime_type: file.type,
                file_size: file.size,
                blob: file,
                fileName: file.name,
                url: URL.createObjectURL(file),
                sort_order: media.length,
                editor_state: null
            };
            media.push(item);
            await persistBlob(item);
        }
        renderMedia();
        markDirty();
        await syncServer(false);
    });

    async function syncDraftMedia() {
        for (const item of media) {
            if (!item.media_uuid) {
                const ok = await uploadItem(item);
                if (!ok) return false;
            }
        }
        return true;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (conflict) {
            setStatus('حل تعارض المسودة قبل النشر.', 'conflict');
            return;
        }
        if (getMode() === 'audio' && !media.some(function (item) { return item.media_kind === 'audio'; })) {
            setStatus('سجّل مقطعًا صوتيًا قبل النشر.', 'error');
            return;
        }
        if (getMode() === 'text' && detailsInput.value.trim().length < 10) {
            detailsInput.focus();
            setStatus('اكتب تفاصيل لا تقل عن 10 أحرف.', 'error');
            return;
        }
        if (media.some(function (item) { return item.media_kind === 'image' && item.needsRender; })) {
            setStatus('فيه صورة عليها تعديلات غير مكتملة. افتح الصورة واضغط «تم» عشان نعتمد التعديلات قبل النشر.', 'error');
            return;
        }
        if (!titleInput.value.trim() || !manufacturerSelect.value || !modelIdInput.value) {
            setStatus('اختار الشركة والموديل واكتب عنوان المنشور.', 'error');
            return;
        }
        if (getType() === 'help_request' && !categoryInput.value) {
            setStatus('اختار التخصص الفني لطلب المساعدة.', 'error');
            categoryInput.focus();
            return;
        }
        publishButton.disabled = true;
        const synced = await syncServer(true);
        const mediaSynced = synced && await syncDraftMedia();
        if (!synced || !mediaSynced || revision < 1) {
            publishButton.disabled = false;
            setStatus('المسودة أو المرفقات لم تكتمل مزامنتها. لن نفقدها؛ حاول مرة أخرى عند استقرار الاتصال.', 'error');
            return;
        }
        revisionInput.value = String(revision);
        draftIdInput.value = draftId;
        form.submit();
    });

    async function applyServerData(data) {
        revision = Number(data.revision || 0);
        revisionInput.value = String(revision);
        applyPayload(data.payload || {});
        const serverMedia = (data.media || []).map(function (item) {
            return Object.assign({}, item, { blob: null, local_id: item.media_uuid, uploaded: true });
        });
        media = serverMedia;
        renderMedia();
    }

    async function recoverPendingAudio(server) {
        if (server && (server.media || []).some(function (item) { return item.media_kind === 'audio'; })) return;
        const pending = await pendingAudioSession().catch(function () { return null; });
        if (!pending || !pending.blob || !pending.blob.size) return;
        const audioRadio = modeRadios.find(function (item) { return item.value === 'audio'; });
        if (audioRadio) audioRadio.checked = true;
        syncModeVisibility();
        const recovered = {
            local_id: uuid(),
            media_uuid: '',
            media_kind: 'audio',
            mime_type: pending.mime_type,
            file_size: pending.blob.size,
            blob: pending.blob,
            fileName: 'recovered-voice-note.' + extensionFor(pending.mime_type),
            url: URL.createObjectURL(pending.blob),
            sort_order: media.length,
            editor_state: null
        };
        media.push(recovered);
        audioMeta = { duration_ms: pending.duration_ms, trim_start_ms: 0, trim_end_ms: pending.duration_ms };
        await persistBlob(recovered);
        await clearAudioSession();
        dirty = true;
        markDirty();
    }

    async function restore() {
        const initialRaw = form.dataset.initial || '{}';
        let initial = {};
        try { initial = JSON.parse(initialRaw); } catch (_) {}
        if (!draftId) {
            draftId = uuid();
            draftIdInput.value = draftId;
            const url = new URL(window.location.href);
            url.searchParams.set('draft', draftId);
            history.replaceState({}, '', url.toString());
        }

        const local = await dbGet('drafts', draftId).catch(function () { return null; });
        let server = null;
        if (navigator.onLine) {
            try {
                const response = await fetch(mediaBase + '/' + encodeURIComponent(draftId) + '/data', {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' }
                });
                if (response.ok) server = await response.json();
            } catch (_) {}
        }

        if (local && Array.isArray(local.pendingDeletes)) local.pendingDeletes.forEach(function (id) { pendingDeletes.add(String(id)); });
        if (local && local.dirty && server && Number(local.serverRevision || 0) < Number(server.revision || 0)) {
            applyPayload(local.payload || {});
            const localMedia = await loadLocalMedia();
            await hydrateLocalMedia(localMedia, server.media || []);
            conflict = true;
            showConflict({ revision: server.revision, payload: server.payload });
            await recoverPendingAudio(server);
            setStatus('وجدنا تعديلات محلية ونسخة أحدث على الحساب. اختار النسخة التي تريد استكمالها.', 'conflict');
            return;
        }
        if (local && (!server || (local.dirty && Number(local.serverRevision || 0) === Number(server.revision || 0)))) {
            applyPayload(local.payload || {});
            revision = Number(server?.revision || local.serverRevision || 0);
            revisionInput.value = String(revision);
            const localMedia = await loadLocalMedia();
            await hydrateLocalMedia(localMedia, server ? server.media || [] : []);
            dirty = true;
            setStatus('استعدنا آخر تعديلات الجهاز. جاري المزامنة...', 'saving');
            initialized = true;
            await recoverPendingAudio(server);
            await syncServer(false);
            return;
        }
        if (server) {
            await applyServerData(server);
            const localMedia = await loadLocalMedia();
            await hydrateLocalMedia(localMedia, server.media || []);
        } else if (initial.payload && Object.keys(initial.payload).length) {
            applyPayload(initial.payload);
            revision = Number(initial.revision || 0);
            revisionInput.value = String(revision);
            media = (initial.media || []).map(function (item) {
                return Object.assign({}, item, { blob: null, local_id: item.media_uuid, uploaded: true });
            });
            renderMedia();
        }
        await recoverPendingAudio(server);
        initialized = true;
        await persistLocal();
        setStatus(navigator.onLine ? 'المسودة جاهزة للحفظ التلقائي' : 'المسودة محفوظة على الجهاز فقط', 'saved');
    }

    async function hydrateLocalMedia(localRecords, serverMedia) {
        const serverByUuid = new Map((serverMedia || []).map(function (item) { return [item.media_uuid, item]; }));
        const restored = [];
        for (const record of localRecords || []) {
            let matched = record.media_uuid ? serverByUuid.get(record.media_uuid) : null;
            const originalLocalUrl = record.blob ? URL.createObjectURL(record.blob) : '';
            const renderedLocalUrl = record.rendered_blob ? URL.createObjectURL(record.rendered_blob) : '';
            const url = matched ? matched.url : (renderedLocalUrl || originalLocalUrl);
            if (!url) continue;
            restored.push({
                local_id: record.local_id,
                media_uuid: matched ? matched.media_uuid : (record.media_uuid || ''),
                media_kind: record.media_kind,
                mime_type: record.mime_type,
                file_size: record.file_size,
                blob: record.blob || null,
                url: renderedLocalUrl || url,
                original_url: matched ? (matched.original_url || matched.url) : (originalLocalUrl || url),
                renderedBlob: record.rendered_blob || null,
                sort_order: matched ? matched.sort_order : record.sort_order,
                editor_state: record.editor_state || (matched ? matched.editor_state : null),
                needsRender: Boolean(record.needs_render) || Boolean((record.editor_state || matched?.editor_state) && !matched?.url?.includes('variant=rendered') && !record.rendered_blob),
                rendered: Boolean(matched?.url?.includes('variant=rendered') || record.rendered_blob),
                uploaded: Boolean(matched)
            });
        }
        const known = new Set(restored.map(function (item) { return item.media_uuid; }).filter(Boolean));
        (serverMedia || []).forEach(function (item) {
            if (!known.has(item.media_uuid)) restored.push(Object.assign({}, item, { blob: null, local_id: item.media_uuid, uploaded: true }));
        });
        media = restored.sort(function (a, b) { return Number(a.sort_order || 0) - Number(b.sort_order || 0); });
        renderMedia();
    }

    function onOnline() {
        if (dirty && !conflict) syncServer(false);
        else setStatus('الاتصال رجع · جاري التحقق من المزامنة', 'saving');
    }
    window.addEventListener('online', onOnline);
    window.addEventListener('beforeunload', function (event) {
        if (dirty || saving) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    window.ZayanComposer = {
        addAudioBlob: async function (blob, durationMs, trimStartMs, trimEndMs, waveform) {
            const existing = media.find(function (item) { return item.media_kind === 'audio'; });
            if (existing) await removeMedia(existing);
            const item = {
                local_id: uuid(),
                media_uuid: '',
                media_kind: 'audio',
                mime_type: blob.type || 'audio/webm',
                file_size: blob.size,
                blob: blob,
                fileName: 'voice-note.' + extensionFor(blob.type || 'audio/webm'),
                url: URL.createObjectURL(blob),
                sort_order: media.length,
                editor_state: null
            };
            audioMeta = {
                duration_ms: durationMs,
                trim_start_ms: trimStartMs || 0,
                trim_end_ms: trimEndMs == null ? durationMs : trimEndMs,
                waveform: Array.isArray(waveform) ? waveform : []
            };
            media.push(item);
            await persistBlob(item);
            await clearAudioSession();
            markDirty();
            await syncServer(false);
            return item;
        },
        removeAudio: async function () {
            const item = media.find(function (entry) { return entry.media_kind === 'audio'; });
            if (item) await removeMedia(item);
            audioMeta = null;
            await clearAudioSession();
            markDirty();
        },
        updateAudioTrim: function (startMs, endMs, durationMs) {
            audioMeta = Object.assign({}, audioMeta || {}, { duration_ms: durationMs, trim_start_ms: startMs, trim_end_ms: endMs });
            markDirty();
        },
        saveAudioChunk: async function (blob, mimeType) {
            if (!draftId || !blob || !blob.size) return;
            audioChunkSeq += 1;
            await dbPut('audioSessions', {
                key: draftId + ':chunk:' + audioChunkSeq,
                draft_id: draftId,
                record_type: 'chunk',
                sequence: audioChunkSeq,
                mime_type: mimeType || blob.type || 'audio/webm',
                blob: blob,
                created_at: Date.now()
            });
        },
        saveAudioProgress: async function (durationMs, mimeType) {
            if (!draftId) return;
            await dbPut('audioSessions', {
                key: draftId + ':meta',
                draft_id: draftId,
                record_type: 'meta',
                duration_ms: Math.max(0, Number(durationMs) || 0),
                mime_type: mimeType || 'audio/webm',
                sequence: audioChunkSeq,
                updated_at: Date.now()
            });
        },
        restorePendingAudio: pendingAudioSession,
        clearAudioSession: clearAudioSession,
        getAudio: function () { return media.find(function (item) { return item.media_kind === 'audio'; }) || null; },
        getAudioMeta: function () { return audioMeta; },
        getDraftId: function () { return draftId; },
        saveImageEditorDraft: function (item, state) {
            item.editor_state = state;
            item.needsRender = true;
            item.dirtyEditor = true;
            persistBlob(item);
            markDirty();
        },
        markDirty: markDirty
    };

    renderManufacturerMark();
    updateTitleCount();
    syncModeVisibility();
    restore().then(function () {
        initialized = true;
        renderSteps();
        renderMedia();
        window.dispatchEvent(new CustomEvent('zayan-composer-restored'));
    }).catch(function (error) {
        setStatus(error.message || 'تعذر استعادة المسودة المحلية.', 'error');
        initialized = true;
    });
})();
