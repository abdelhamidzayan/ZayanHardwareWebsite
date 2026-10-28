/* One-time, non-blocking PWA install guide for authenticated users. */
(() => {
  'use strict';

  const body = document.body;
  const userId = body?.dataset.pwaUserId;
  if (!userId) return;

  const base = body.dataset.pwaBase || '';
  const storageKey = 'zayan-hardware:pwa-guide:v1:' + userId;
  const sessionKey = storageKey + ':later';
  const laterCountKey = storageKey + ':later-count';
  let installPrompt = null;
  let dialog = null;
  let previousFocus = null;

  const isInstalled = () =>
    window.matchMedia?.('(display-mode: standalone)').matches ||
    window.navigator.standalone === true ||
    document.referrer.startsWith('android-app://');

  const getSeen = () => {
    try { return localStorage.getItem(storageKey) === '1'; }
    catch (_) {
      try { return sessionStorage.getItem(storageKey) === '1'; }
      catch (__) { return false; }
    }
  };

  const markSeen = () => {
    try { localStorage.setItem(storageKey, '1'); }
    catch (_) {
      try { sessionStorage.setItem(storageKey, '1'); } catch (__) {}
    }
  };

  const getSnoozed = () => {
    try { return sessionStorage.getItem(sessionKey) === '1'; }
    catch (_) { return false; }
  };

  const snoozeGuide = () => {
    try { sessionStorage.setItem(sessionKey, '1'); } catch (_) {}
    closeGuide();
  };

  // Two explicit "Not now" choices permanently dismiss the guide on this browser.
  const dismissLater = () => {
    let count = 0;
    try {
      count = Number.parseInt(localStorage.getItem(laterCountKey) || '0', 10) || 0;
      count += 1;
      localStorage.setItem(laterCountKey, String(count));
    } catch (_) {
      try {
        count = Number.parseInt(sessionStorage.getItem(laterCountKey) || '0', 10) || 0;
        count += 1;
        sessionStorage.setItem(laterCountKey, String(count));
      } catch (__) {}
    }

    if (count >= 2) markSeen();
    snoozeGuide();
  };

  const platformGuide = () => {
    const ua = navigator.userAgent || '';
    const isIOS = /iPhone|iPad|iPod/i.test(ua) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /Android/i.test(ua);
    const isSafari = /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
    const isMacSafari = /Macintosh/i.test(ua) && isSafari;

    if (isIOS && isSafari) {
      return {
        label: 'iPhone و iPad',
        text: 'افتح الموقع في Safari، ثم اضغط زر المشاركة',
        steps: ['اضغط على مشاركة (Share) أسفل الشاشة أو أعلى المتصفح.', 'اختر «إضافة إلى الشاشة الرئيسية» (Add to Home Screen).', 'اضغط «إضافة» (Add). هتلاقي Zayan Hardware على الشاشة الرئيسية.']
      };
    }
    if (isIOS) {
      return {
        label: 'iPhone و iPad',
        text: 'التثبيت على أجهزة Apple يحتاج Safari.',
        steps: ['افتح رابط الموقع في Safari بدل المتصفح الحالي.', 'اضغط مشاركة (Share)، ثم «إضافة إلى الشاشة الرئيسية».', 'اضغط «إضافة» (Add).']
      };
    }
    if (isAndroid) {
      return {
        label: 'Android',
        text: 'تقدر تثبّت الموقع من قائمة المتصفح.',
        steps: ['افتح قائمة المتصفح ⋮ أعلى الشاشة.', 'اختر «تثبيت التطبيق» (Install app) أو «إضافة إلى الشاشة الرئيسية».', 'أكد الاختيار؛ هيظهر التطبيق وسط تطبيقاتك.']
      };
    }
    if (isMacSafari) {
      return {
        label: 'Mac',
        text: 'في إصدارات Safari الحديثة، تقدر تضيف الموقع إلى Dock.',
        steps: ['من شريط القوائم اختر File (ملف).', 'اختر Add to Dock (إضافة إلى Dock) إن كان الخيار متاحًا.', 'لو الخيار غير موجود، افتح الموقع في Chrome أو Edge واستخدم خيار التثبيت.']
      };
    }
    return {
      label: 'كمبيوتر أو لابتوب',
      text: 'استخدم Chrome أو Microsoft Edge للحصول على أفضل تجربة تثبيت.',
      steps: ['ابحث عن أيقونة التثبيت بجوار شريط العنوان، إن ظهرت.', 'أو افتح قائمة المتصفح ⋮ ثم ابحث عن «تثبيت Zayan Hardware» أو «Install app».', 'أكد التثبيت؛ هتقدر تفتح المنصة من قائمة التطبيقات أو سطح المكتب حسب جهازك.']
    };
  };

  const closeGuide = () => {
    if (!dialog) return;
    dialog.remove();
    dialog = null;
    document.removeEventListener('keydown', onKeydown);
    if (previousFocus?.isConnected) previousFocus.focus();
  };

  const dismissGuide = () => {
    snoozeGuide();
  };

  const skipPermanently = () => {
    markSeen();
    closeGuide();
  };

  const onKeydown = (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      snoozeGuide();
      return;
    }
    if (event.key !== 'Tab' || !dialog) return;
    const focusable = [...dialog.querySelectorAll('button:not([hidden])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  const openGuide = () => {
    if (dialog || getSeen() || getSnoozed() || isInstalled()) return;
    previousFocus = document.activeElement;

    const guide = platformGuide();
    dialog = document.createElement('div');
    dialog.className = 'pwa-guide-overlay';
    dialog.innerHTML = `
      <section class="pwa-guide" role="dialog" aria-modal="true" aria-labelledby="pwa-guide-title" aria-describedby="pwa-guide-description" dir="rtl">
        <button class="pwa-guide__close" type="button" aria-label="إغلاق الدليل" data-pwa-close>×</button>
        <div class="pwa-guide__brandmark" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none"><rect x="5" y="5" width="38" height="38" rx="12" fill="currentColor" opacity=".12"/><rect x="14" y="14" width="20" height="20" rx="3" stroke="currentColor" stroke-width="2.5"/><rect x="20" y="20" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="2"/><path d="M19 9v5m10-5v5m-10 20v5m10-5v5M9 19h5m-5 10h5m20-10h5m-5 10h5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </div>
        <span class="pwa-guide__eyebrow">تجربة أسرع للفنيين</span>
        <h2 id="pwa-guide-title">خلّي Zayan Hardware على جهازك</h2>
        <p class="pwa-guide__intro" id="pwa-guide-description">ثبّت المنصة وافتحها من أيقونة على جهازك بدل ما تدور على رابط الموقع كل مرة.</p>
        <div class="pwa-guide__instructions">
          <div class="pwa-guide__device"><span class="pwa-guide__device-dot"></span><strong>${guide.label}</strong></div>
          <p>${guide.text}</p>
          <ol>${guide.steps.map(step => '<li>' + step + '</li>').join('')}</ol>
        </div>
        <p class="pwa-guide__note">التثبيت اختياري، والموقع هيشتغل عادي من غيره.</p>
        <div class="pwa-guide__actions">
          <button class="pwa-guide__install" type="button" data-pwa-install hidden>تثبيت التطبيق الآن</button>
          <button class="pwa-guide__done" type="button" data-pwa-done>مش دلوقتي</button>
          <button class="pwa-guide__skip" type="button" data-pwa-skip>تخطي نهائيًا</button>
        </div>
      </section>`;

    document.body.appendChild(dialog);
    document.addEventListener('keydown', onKeydown);
    dialog.querySelectorAll('[data-pwa-close]').forEach(button => button.addEventListener('click', dismissGuide));
    dialog.querySelector('[data-pwa-done]').addEventListener('click', dismissLater);
    dialog.querySelector('[data-pwa-skip]').addEventListener('click', skipPermanently);
    dialog.addEventListener('click', event => {
      if (event.target === dialog) snoozeGuide();
    });

    const installButton = dialog.querySelector('[data-pwa-install]');
    const doneButton = dialog.querySelector('[data-pwa-done]');
    const setInstallAvailable = () => {
      if (!installPrompt || !installButton) return;
      installButton.hidden = false;
      doneButton.textContent = 'مش دلوقتي';
    };

    installButton.addEventListener('click', async () => {
      if (!installPrompt) return;
      const promptEvent = installPrompt;
      installPrompt = null;
      let accepted = false;
      installButton.disabled = true;
      installButton.textContent = 'جاري فتح التثبيت…';
      try {
        await promptEvent.prompt();
        const choice = await promptEvent.userChoice;
        accepted = choice?.outcome === 'accepted';
      } catch (_) {
        // The manual instructions remain available if the browser prompt fails.
      } finally {
        if (accepted) {
          markSeen();
          closeGuide();
        } else {
          snoozeGuide();
        }
      }
    });

    if (installPrompt) setInstallAvailable();
    requestAnimationFrame(() => dialog?.querySelector('[data-pwa-install]:not([hidden])')?.focus() || dialog?.querySelector('[data-pwa-done]')?.focus());
  };

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    if (dialog) {
      const button = dialog.querySelector('[data-pwa-install]');
      if (button) {
        button.hidden = false;
        const done = dialog.querySelector('[data-pwa-done]');
        if (done) done.textContent = 'مش دلوقتي';
      }
    }
  });

  window.addEventListener('appinstalled', () => {
    markSeen();
    closeGuide();
  });

  if (!getSeen() && !getSnoozed() && !isInstalled()) {
    window.setTimeout(openGuide, 900);
  }
})();
