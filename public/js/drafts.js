'use strict';
(() => {
    const page = document.querySelector('[data-drafts-page]');
    if (!page) return;
    const localList = page.querySelector('[data-local-drafts]');
    const baseUrl = page.dataset.baseUrl || '';
    let serverIds = [];
    try { serverIds = JSON.parse(page.dataset.serverDraftIds || '[]'); } catch (_) { serverIds = []; }
    const serverIdSet = new Set(serverIds.map(String));

    function openDraftDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('zayan-post-drafts', 2);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('drafts')) db.createObjectStore('drafts', { keyPath: 'id' });
                if (!db.objectStoreNames.contains('media')) db.createObjectStore('media', { keyPath: 'key' });
                if (!db.objectStoreNames.contains('audioSessions')) db.createObjectStore('audioSessions', { keyPath: 'key' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('تعذر فتح المسودات المحلية.'));
        });
    }

    async function allFrom(storeName) {
        const db = await openDraftDb();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readonly');
            const request = transaction.objectStore(storeName).getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async function deleteLocalDraft(id) {
        const db = await openDraftDb();
        const media = await allFrom('media');
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(['drafts', 'media', 'audioSessions'], 'readwrite');
            transaction.objectStore('drafts').delete(id);
            media.filter((item) => item.draft_id === id).forEach((item) => transaction.objectStore('media').delete(item.key));
            transaction.objectStore('audioSessions').getAll().onsuccess = (event) => {
                (event.target.result || []).filter((item) => item.draft_id === id).forEach((item) => transaction.objectStore('audioSessions').delete(item.key));
            };
            transaction.oncomplete = () => resolve(true);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    }

    function formatDate(timestamp) {
        try {
            return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp));
        } catch (_) {
            return 'آخر تعديل محفوظ على الجهاز';
        }
    }

    function makeLocalCard(record) {
        const payload = record.payload || {};
        const card = document.createElement('article');
        card.className = 'card draft-card draft-card--local';
        card.dataset.draftCard = record.id;

        const copy = document.createElement('div');
        copy.className = 'draft-card__copy';
        const meta = document.createElement('div');
        meta.className = 'draft-card__meta';
        const type = document.createElement('span');
        type.className = 'tag';
        type.textContent = payload.content_type === 'help_request' ? 'طلب مساعدة' : 'معلومة فنية';
        const date = document.createElement('span');
        date.className = 'muted';
        date.textContent = formatDate(record.updatedAt || Date.now());
        meta.append(type, date);

        const title = document.createElement('h2');
        title.className = 'draft-card__title';
        title.textContent = payload.title || 'مسودة بدون عنوان';
        const status = document.createElement('p');
        status.className = 'muted';
        status.textContent = (payload.details_mode === 'audio' ? 'تسجيل صوتي' : 'تفاصيل مكتوبة') + ' · محفوظة على الجهاز وتنتظر المزامنة';
        copy.append(meta, title, status);

        const actions = document.createElement('div');
        actions.className = 'draft-card__actions';
        const resume = document.createElement('a');
        resume.className = 'btn btn--primary';
        resume.href = baseUrl + '/issues/create?draft=' + encodeURIComponent(record.id);
        resume.textContent = 'استكمال المسودة';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn--ghost text-action--danger';
        remove.textContent = 'حذف';
        remove.addEventListener('click', async () => {
            if (!window.confirm('حذف المسودة المحلية وكل الملفات المحفوظة على هذا الجهاز؟')) return;
            try {
                await deleteLocalDraft(record.id);
                card.remove();
                updateEmptyState();
            } catch (_) {
                window.alert('تعذر حذف النسخة المحلية.');
            }
        });
        actions.append(resume, remove);
        card.append(copy, actions);
        return card;
    }

    function updateEmptyState() {
        const empty = page.querySelector('.empty-state');
        const hasCards = page.querySelectorAll('[data-draft-card]').length > 0;
        if (empty) empty.hidden = hasCards;
    }

    async function reconcileLocalDrafts() {
        if (!('indexedDB' in window) || !localList) return;
        const records = await allFrom('drafts');
        for (const record of records) {
            const id = String(record.id);
            if (serverIdSet.has(id)) {
                const serverCard = page.querySelector('[data-draft-card="' + CSS.escape(id) + '"]');
                const status = serverCard?.querySelector('[data-draft-sync-status]');
                if (status && record.dirty) status.textContent = 'تعديلات محلية تنتظر المزامنة';
                continue;
            }
            if (!record.dirty && Number(record.serverRevision || 0) > 0) {
                await deleteLocalDraft(id).catch(() => {});
                continue;
            }
            localList.appendChild(makeLocalCard(record));
        }
        updateEmptyState();
    }

    page.querySelectorAll('[data-delete-draft-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm('حذف المسودة وكل الملفات المرتبطة بها؟ لا يمكن التراجع عن الحذف.')) return;
            const card = form.closest('.draft-card');
            const id = card?.dataset.draftCard || '';
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.deleted) throw new Error(result.message || 'تعذر حذف المسودة.');
                if (id) await deleteLocalDraft(id).catch(() => {});
                card?.remove();
                updateEmptyState();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'تعذر حذف المسودة.');
                if (button) button.disabled = false;
            }
        });
    });

    reconcileLocalDrafts().catch(() => {});
})();
