'use strict';
(() => {
    const controls = document.querySelector('[data-push-controls]');
    if (!controls) return;

    const enableButton = controls.querySelector('[data-push-enable]');
    const disableButton = controls.querySelector('[data-push-disable]');
    const status = controls.querySelector('[data-push-status]');
    const token = controls.querySelector('[name="_token"]');
    const base = document.body.dataset.pwaBase || '';

    function setStatus(message) {
        if (status) status.textContent = message;
    }

    function decodeBase64Url(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        return Uint8Array.from(raw, (char) => char.charCodeAt(0));
    }

    async function post(path, values) {
        const body = new URLSearchParams({ ...values, _token: token ? token.value : '' });
        const response = await fetch(base + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || 'تعذر حفظ إعداد الإشعارات.');
        return data;
    }

    async function syncButtons() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            setStatus('المتصفح الحالي لا يدعم إشعارات Push.');
            if (enableButton) enableButton.disabled = true;
            if (disableButton) disableButton.disabled = true;
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (enableButton) enableButton.hidden = Boolean(subscription);
            if (disableButton) disableButton.hidden = !subscription;
        } catch (_) {
            setStatus('تعذر الوصول إلى إعدادات الإشعارات في هذا الجهاز.');
        }
    }

    if (enableButton) enableButton.addEventListener('click', async () => {
        enableButton.disabled = true;
        try {
            if (Notification.permission === 'denied') {
                throw new Error('الإشعارات محظورة من إعدادات المتصفح. فعّلها من إعدادات الموقع أولًا.');
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('لم يتم منح إذن الإشعارات.');
            const keyResponse = await fetch(base + '/push/public-key', { credentials: 'same-origin', cache: 'no-store' });
            const keyData = await keyResponse.json();
            if (!keyResponse.ok || !keyData.publicKey) throw new Error('إعدادات Push غير مكتملة على الخادم.');
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: decodeBase64Url(keyData.publicKey)
            });
            const data = subscription.toJSON();
            await post('/push/subscribe', {
                endpoint: data.endpoint,
                p256dh: data.keys.p256dh,
                auth: data.keys.auth
            });
            setStatus('تم تفعيل إشعارات هذا الجهاز.');
        } catch (error) {
            setStatus(error.message || 'تعذر تفعيل الإشعارات.');
        } finally {
            enableButton.disabled = false;
            syncButtons();
        }
    });

    if (disableButton) disableButton.addEventListener('click', async () => {
        disableButton.disabled = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await post('/push/unsubscribe', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            setStatus('تم إيقاف إشعارات Push على هذا الجهاز.');
        } catch (error) {
            setStatus(error.message || 'تعذر إيقاف الإشعارات.');
        } finally {
            disableButton.disabled = false;
            syncButtons();
        }
    });

    syncButtons();
})();
