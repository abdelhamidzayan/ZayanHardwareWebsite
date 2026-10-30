'use strict';
(() => {
    document.querySelectorAll('[data-delete-draft-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm('حذف المسودة وكل الملفات المرتبطة بها؟ لا يمكن التراجع عن الحذف.')) return;
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
                form.closest('.draft-card')?.remove();
                const list = document.querySelector('.drafts-list');
                if (list && !list.children.length) location.reload();
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'تعذر حذف المسودة.');
                if (button) button.disabled = false;
            }
        });
    });
})();
