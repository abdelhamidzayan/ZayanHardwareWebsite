'use strict';
(() => {
    document.querySelectorAll('[data-content-editor]').forEach((editor) => {
        const textarea = editor.querySelector('[data-content-textarea]');
        const fileInput = editor.querySelector('[data-content-files]');
        const previews = editor.querySelector('[data-content-previews]');
        const tokenInputs = editor.querySelector('[data-content-token-inputs]');
        const livePreview = editor.querySelector('[data-content-live-preview]');
        const livePreviewBody = editor.querySelector('[data-content-live-preview-body]');
        if (!textarea || !fileInput || !previews || !tokenInputs) return;

        let files = [];
        let nextToken = 1;
        let existingMedia = {};
        try {
            existingMedia = JSON.parse(editor.dataset.existingMedia || '{}');
        } catch (_) {
            existingMedia = {};
        }

        function appendText(container, value) {
            const lines = value.replace(/\r\n?/g, '\n').split('\n');
            lines.forEach((line, index) => {
                if (index > 0) container.appendChild(document.createElement('br'));
                if (line) container.appendChild(document.createTextNode(line));
            });
        }

        function renderLivePreview() {
            if (!livePreview || !livePreviewBody) return;
            livePreview.hidden = false;
            livePreviewBody.replaceChildren();

            const value = textarea.value;
            const tokenPattern = /\[\[image:([A-Za-z0-9_-]{1,80})\]\]/g;
            let cursor = 0;
            let match;
            while ((match = tokenPattern.exec(value)) !== null) {
                appendText(livePreviewBody, value.slice(cursor, match.index));
                const token = match[1];
                const selected = files.find((item) => item.token === token);
                const media = selected
                    ? { url: selected.url, type: selected.file.type.startsWith('image/') ? 'image' : 'pdf' }
                    : existingMedia[token];

                if (media && media.url) {
                    if (media.type === 'image') {
                        const image = document.createElement('img');
                        image.className = 'content-editor__inline-image';
                        image.src = media.url;
                        image.alt = 'معاينة صورة فنية داخل النص';
                        image.loading = 'lazy';
                        livePreviewBody.appendChild(image);
                    } else {
                        const link = document.createElement('a');
                        link.className = 'content-editor__inline-document';
                        link.href = media.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.textContent = 'عرض المخطط PDF';
                        livePreviewBody.appendChild(link);
                    }
                } else {
                    const unavailable = document.createElement('span');
                    unavailable.className = 'content-editor__unavailable';
                    unavailable.textContent = '[مرفق غير متاح]';
                    livePreviewBody.appendChild(unavailable);
                }
                cursor = tokenPattern.lastIndex;
            }
            appendText(livePreviewBody, value.slice(cursor));
        }

        function makeToken() {
            return 'media-' + Date.now().toString(36) + '-' + (nextToken++).toString(36);
        }
        function swapTokens(tokenA, tokenB) {
            const temporary = 'media-swap-temporary-token';
            textarea.value = textarea.value.split('[[image:' + tokenA + ']]').join('[[image:' + temporary + ']]');
            textarea.value = textarea.value.split('[[image:' + tokenB + ']]').join('[[image:' + tokenA + ']]');
            textarea.value = textarea.value.split('[[image:' + temporary + ']]').join('[[image:' + tokenB + ']]');
        }
        function insertAtCursor(token) {
            const marker = '[[image:' + token + ']]';
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? start;
            textarea.setRangeText(marker, start, end, 'end');
            textarea.focus();
            renderLivePreview();
        }
        function syncFiles() {
            const transfer = new DataTransfer();
            files.forEach((item) => transfer.items.add(item.file));
            fileInput.files = transfer.files;
            tokenInputs.replaceChildren();
            files.forEach((item) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'attachment_tokens[]';
                hidden.value = item.token;
                tokenInputs.appendChild(hidden);
            });
        }
        function renderPreviews() {
            previews.replaceChildren();
            files.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'content-editor__preview';
                if (item.file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = item.url;
                    img.alt = item.file.name;
                    card.appendChild(img);
                } else {
                    const fileIcon = document.createElement('span');
                    fileIcon.className = 'content-editor__document';
                    fileIcon.textContent = 'PDF';
                    card.appendChild(fileIcon);
                }
                const name = document.createElement('span');
                name.className = 'content-editor__filename';
                name.textContent = item.file.name;
                card.appendChild(name);

                const actions = document.createElement('div');
                actions.className = 'content-editor__preview-actions';
                const insert = document.createElement('button');
                insert.type = 'button';
                insert.className = 'text-action text-action--primary';
                insert.textContent = 'إدراج عند المؤشر';
                insert.addEventListener('click', () => insertAtCursor(item.token));
                actions.appendChild(insert);

                if (index > 0) {
                    const up = document.createElement('button');
                    up.type = 'button';
                    up.className = 'text-action';
                    up.textContent = 'تقديم';
                    up.addEventListener('click', () => {
                        const previous = files[index - 1];
                        swapTokens(item.token, previous.token);
                        files[index - 1] = item;
                        files[index] = previous;
                        syncFiles();
                        renderPreviews();
                        renderLivePreview();
                    });
                    actions.appendChild(up);
                }
                if (index < files.length - 1) {
                    const down = document.createElement('button');
                    down.type = 'button';
                    down.className = 'text-action';
                    down.textContent = 'تأخير';
                    down.addEventListener('click', () => {
                        const next = files[index + 1];
                        swapTokens(item.token, next.token);
                        files[index + 1] = item;
                        files[index] = next;
                        syncFiles();
                        renderPreviews();
                        renderLivePreview();
                    });
                    actions.appendChild(down);
                }
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-action text-action--danger';
                remove.textContent = 'حذف';
                remove.addEventListener('click', () => {
                    textarea.value = textarea.value.split('[[image:' + item.token + ']]').join('');
                    URL.revokeObjectURL(item.url);
                    files = files.filter((entry) => entry.token !== item.token);
                    syncFiles();
                    renderPreviews();
                    renderLivePreview();
                });
                actions.appendChild(remove);
                card.appendChild(actions);
                previews.appendChild(card);
            });
        }

        textarea.addEventListener('input', renderLivePreview);

        fileInput.addEventListener('change', () => {
            const incoming = Array.from(fileInput.files || []);
            const available = Math.max(0, 8 - files.length);
            incoming.slice(0, available).forEach((file) => {
                files.push({ file, token: makeToken(), url: URL.createObjectURL(file) });
            });
            if (incoming.length > available) {
                const note = document.createElement('p');
                note.className = 'muted';
                note.textContent = 'الحد الأقصى 8 ملفات.';
                previews.appendChild(note);
            }
            syncFiles();
            renderPreviews();
            renderLivePreview();
        });

        renderLivePreview();
    });
})();
