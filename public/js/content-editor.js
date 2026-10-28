'use strict';
(() => {
    document.querySelectorAll('[data-content-editor]').forEach((editor) => {
        const textarea = editor.querySelector('[data-content-textarea]');
        const fileInput = editor.querySelector('[data-content-files]');
        const previews = editor.querySelector('[data-content-previews]');
        const tokenInputs = editor.querySelector('[data-content-token-inputs]');
        if (!textarea || !fileInput || !previews || !tokenInputs) return;

        let files = [];
        let nextToken = 1;

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
                });
                actions.appendChild(remove);
                card.appendChild(actions);
                previews.appendChild(card);
            });
        }

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
        });
    });
})();
