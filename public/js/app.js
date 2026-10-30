/* app.js — shared behavior: audio player, password toggle, form validation. */
(() => {
  'use strict';

  /* ---------- Audio player ---------- */
  const players = new WeakMap();
  let playingRoot = null;

  const fmt = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
  };

  const setPlaying = (root, isPlaying) => {
    root.classList.toggle('is-playing', isPlaying);
    const btn = root.querySelector('.audio__btn');
    if (btn) {
      btn.setAttribute('aria-label', isPlaying ? 'إيقاف مؤقت' : 'تشغيل الملاحظة الصوتية');
    }
  };

  const getPlayer = (root) => {
    let player = players.get(root);
    if (player) return player;

    const el = new Audio(root.dataset.src);
    el.preload = 'metadata';
    const time = root.querySelector('.audio__time');
    const bars = root.querySelectorAll('.audio__wave rect');

    const paint = () => {
      const ratio = el.duration ? el.currentTime / el.duration : 0;
      const played = Math.round(ratio * bars.length);
      bars.forEach((bar, i) => bar.classList.toggle('is-played', i < played));
    };

    el.addEventListener('loadedmetadata', () => { time.textContent = fmt(el.duration); });
    el.addEventListener('timeupdate', () => { time.textContent = fmt(el.currentTime); paint(); });
    el.addEventListener('ended', () => {
      el.currentTime = 0;
      paint();
      time.textContent = fmt(el.duration);
      setPlaying(root, false);
      playingRoot = null;
    });
    el.addEventListener('error', () => { setPlaying(root, false); time.textContent = '—'; });

    player = { el };
    players.set(root, player);
    return player;
  };

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('.audio__btn');
    if (!btn || btn.disabled) return;

    const root = btn.closest('[data-audio]');
    if (!root || !root.dataset.src) return;

    const { el } = getPlayer(root);

    if (playingRoot && playingRoot !== root) {
      players.get(playingRoot)?.el.pause();
      setPlaying(playingRoot, false);
      playingRoot = null;
    }

    if (el.paused) {
      el.play()
        .then(() => { setPlaying(root, true); playingRoot = root; })
        .catch(() => setPlaying(root, false));
    } else {
      el.pause();
      setPlaying(root, false);
      playingRoot = null;
    }
  });

  /* ---------- Password show / hide ---------- */
  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.hidden = false;
    btn.addEventListener('click', () => {
      const input = btn.closest('.field__password').querySelector('input');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
    });
  });

  /* ---------- Form validation (server validates again later) ---------- */
  const rules = {
    required: (v, el) => (el.type === 'checkbox' ? el.checked : v.trim() !== '') || 'هذا الحقل مطلوب',
    email: (v) => v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'أدخل بريدًا إلكترونيًا صحيحًا',
    username: (v) => v === '' || /^[a-z0-9_]{3,30}$/.test(v) || 'من 3 إلى 30 حرفًا: إنجليزية صغيرة وأرقام و _',
    min: (v, el, n) => v === '' || v.length >= Number(n) || `لا تقل عن ${n} أحرف`,
    tel: (v) => v === '' || /^\+?[0-9\s-]{7,20}$/.test(v) || 'أدخل رقم هاتف صحيحًا',
  };

  const check = (input) => {
    const list = (input.dataset.rules || '').split(' ').filter(Boolean);
    for (const item of list) {
      const [name, arg] = item.split(':');
      if (!rules[name]) continue;
      const result = rules[name](input.value, input, arg);
      if (result !== true) return result;
    }
    return '';
  };

  const showError = (input, message) => {
    const out = input.closest('.field')?.querySelector('.field__error');
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (out) out.textContent = message;
  };

  document.querySelectorAll('form[data-validate]').forEach((form) => {
    const inputs = [...form.querySelectorAll('[data-rules]')];

    inputs.forEach((input) => {
      input.addEventListener('blur', () => showError(input, check(input)));
      input.addEventListener('input', () => {
        if (input.getAttribute('aria-invalid') === 'true') showError(input, check(input));
      });
    });

    form.addEventListener('submit', (event) => {
      let first = null;
      inputs.forEach((input) => {
        const message = check(input);
        showError(input, message);
        if (message && !first) first = input;
      });
      if (first) {
        event.preventDefault();
        first.focus();
      }
    });
  });
})();


/* ---------- Login identifier: phone, email, or username in one field ---------- */
(() => {
  'use strict';

  const form = document.querySelector('[data-login-identifier-form]');
  const pendingLoginKey = 'zayan-hardware-login-pending';
  if (!form) {
    try { sessionStorage.removeItem(pendingLoginKey); } catch (_) {}
    return;
  }

  const visibleInput = form.querySelector('[data-login-visible-input]');
  const hiddenInput = form.querySelector('#identifier');
  const contact = form.querySelector('[data-login-contact]');
  const phoneControls = form.querySelector('[data-login-phone-controls]');
  const countryToggle = form.querySelector('[data-login-country-toggle]');
  const countryList = form.querySelector('[data-login-country-list]');
  const countryFlag = form.querySelector('[data-login-country-flag]');
  const countryDial = form.querySelector('[data-login-country-dial]');
  const contactSwitch = form.querySelector('[data-login-contact-switch]');
  const contactIcon = form.querySelector('[data-login-contact-icon]');
  const label = form.querySelector('[data-login-identifier-label]');
  let manualMode = 'phone';

  const clearInvalidCredentialsNotice = () => {
    document.querySelectorAll('.flash--error').forEach((notice) => {
      const message = notice.querySelector('p')?.textContent?.trim();
      if (message === 'بيانات الدخول غير صحيحة.') {
        const list = notice.closest('.flash-list');
        notice.remove();
        if (list && !list.querySelector('.flash')) list.remove();
      }
    });
  };

  const toLatinDigits = (value) => value.replace(/[٠-٩۰-۹]/g, (digit) => {
    const arabic = '٠١٢٣٤٥٦٧٨٩';
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const index = arabic.indexOf(digit);
    return String(index >= 0 ? index : persian.indexOf(digit));
  });
  const digitsOnly = (value) => toLatinDigits(value).replace(/\D/g, '');
  const isEmail = (value) => value.includes('@');
  const isPhone = (value) => {
    const trimmed = value.trim();
    const digits = digitsOnly(trimmed);
    return digits.length >= 7 && /^[+0-9٠-٩۰-۹().\s-]+$/.test(trimmed);
  };
  const looksLikePhone = (value) => {
    const trimmed = value.trim();
    return digitsOnly(trimmed).length > 0 && /^[+0-9٠-٩۰-۹().\s-]+$/.test(trimmed);
  };

  const currentMode = () => {
    const value = visibleInput.value.trim();
    if (isEmail(value)) return 'email';
    if (value && !looksLikePhone(value)) return 'username';
    return manualMode;
  };

  const updateMode = () => {
    const value = visibleInput.value.trim();
    const mode = currentMode();
    const phoneMode = mode === 'phone';
    phoneControls.hidden = false;
    contact.classList.toggle('login-contact--email', mode === 'email');
    contact.classList.toggle('login-contact--username', mode === 'username');
    visibleInput.inputMode = mode === 'email' ? 'email' : (phoneMode ? 'tel' : 'text');
    visibleInput.autocomplete = mode === 'email' ? 'email' : (phoneMode ? 'tel-national' : 'username');
    visibleInput.placeholder = mode === 'email' ? 'name@example.com' : (mode === 'username' ? 'اسم المستخدم' : 'رقم الهاتف');
    label.textContent = mode === 'email' ? 'البريد الإلكتروني' : (mode === 'username' ? 'اسم المستخدم' : 'رقم الهاتف أو البريد الإلكتروني أو اسم المستخدم');
    contactSwitch.setAttribute('aria-label', phoneMode ? 'التبديل إلى البريد الإلكتروني' : 'التبديل إلى رقم الهاتف');
    contactSwitch.title = phoneMode ? 'التبديل إلى البريد الإلكتروني' : 'التبديل إلى رقم الهاتف';
    contactIcon.innerHTML = phoneMode
      ? '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/>'
      : '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>';
  };

  const closeCountryList = () => {
    countryList.hidden = true;
    countryToggle.setAttribute('aria-expanded', 'false');
  };

  countryToggle.addEventListener('click', () => {
    const open = countryList.hidden;
    countryList.hidden = !open;
    countryToggle.setAttribute('aria-expanded', String(open));
  });

  countryList.querySelectorAll('[data-login-country]').forEach((button) => {
    button.addEventListener('click', () => {
      countryFlag.textContent = button.dataset.flag || button.dataset.country;
      countryDial.textContent = '+' + button.dataset.dial;
      countryList.querySelectorAll('[data-login-country]').forEach((item) => {
        item.setAttribute('aria-selected', item === button ? 'true' : 'false');
      });
      countryToggle.dataset.country = button.dataset.country;
      countryToggle.dataset.dial = button.dataset.dial;
      closeCountryList();
      visibleInput.focus();
    });
  });

  contactSwitch.addEventListener('click', () => {
    manualMode = currentMode() === 'phone' ? 'email' : 'phone';
    visibleInput.value = '';
    updateMode();
    closeCountryList();
    visibleInput.focus();
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-login-phone-controls]')) closeCountryList();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeCountryList();
  });

  visibleInput.addEventListener('input', () => {
    updateMode();
    clearInvalidCredentialsNotice();
  });
  form.addEventListener('input', clearInvalidCredentialsNotice);

  try {
    const pending = sessionStorage.getItem(pendingLoginKey);
    if (pending) {
      sessionStorage.removeItem(pendingLoginKey);
      const previous = JSON.parse(pending);
      visibleInput.value = typeof previous.value === 'string' ? previous.value : '';
      if (previous.country) {
        const selected = countryList.querySelector('[data-login-country="' + CSS.escape(previous.country) + '"]');
        if (selected) selected.click();
      }
      updateMode();
    }
  } catch (_) {
    try { sessionStorage.removeItem(pendingLoginKey); } catch (_) {}
  }

  updateMode();
  form.addEventListener('submit', () => {
    const value = visibleInput.value.trim();
    const mode = currentMode();
    if (mode === 'email' || value.includes('@')) {
      hiddenInput.value = value.toLowerCase();
    } else if (isPhone(value)) {
      const digits = digitsOnly(value);
      if (value.startsWith('+')) {
        hiddenInput.value = '+' + digits;
      } else if (digits.startsWith('00')) {
        hiddenInput.value = '+' + digits.slice(2);
      } else {
        const code = digitsOnly(countryToggle.dataset.dial || '20');
        if (digits.startsWith(code) && digits.length > code.length + 6) {
          hiddenInput.value = '+' + digits;
        } else {
          hiddenInput.value = '+' + code + digits.replace(/^0+/, '');
        }
      }
    } else {
      hiddenInput.value = value.toLowerCase();
    }

    try {
      const selected = countryToggle.dataset.country || 'EG';
      sessionStorage.setItem(pendingLoginKey, JSON.stringify({ value, country: selected }));
    } catch (_) {}
  });
})();


/* ---------- Automatic featured home posts: no manual arrows ---------- */
(() => {
  'use strict';

  const carousel = document.querySelector('[data-featured-carousel]');
  if (!carousel) return;

  const slides = Array.from(carousel.querySelectorAll('[data-featured-slide]'));
  const progress = carousel.querySelector('[data-featured-progress]');
  if (slides.length < 2) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const intervalMs = 7000;
  let current = slides.findIndex((slide) => slide.classList.contains('is-active'));
  let timer = null;
  let paused = false;

  const show = (index) => {
    slides.forEach((slide, i) => {
      const active = i === index;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      slide.setAttribute('tabindex', active ? '0' : '-1');
    });
    current = index;
    if (progress) {
      progress.style.animation = 'none';
      void progress.offsetWidth;
      progress.style.animation = '';
    }
  };

  const stop = () => {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
    carousel.classList.remove('is-running');
  };

  const start = () => {
    stop();
    if (paused || reducedMotion || document.hidden) return;
    carousel.classList.add('is-running');
    timer = window.setInterval(() => show((current + 1) % slides.length), intervalMs);
  };

  carousel.addEventListener('mouseenter', () => { paused = true; stop(); });
  carousel.addEventListener('mouseleave', () => { paused = false; start(); });
  carousel.addEventListener('focusin', () => { paused = true; stop(); });
  carousel.addEventListener('focusout', (event) => {
    if (!carousel.contains(event.relatedTarget)) { paused = false; start(); }
  });
  document.addEventListener('visibilitychange', start);

  // The server randomizes the selected posts on each page load; rotation remains automatic.
  show(Math.max(0, current));
  start();
})();


/* ---------- Account dropdown: close on outside click or Escape ---------- */
(() => {
  'use strict';

  const menus = document.querySelectorAll('.account-menu, .notification-menu, .help-request-menu');
  if (!menus.length) return;

  document.addEventListener('click', (event) => {
    menus.forEach((menu) => {
      if (!menu.contains(event.target)) menu.open = false;
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    menus.forEach((menu) => {
      if (!menu.open) return;
      menu.open = false;
      menu.querySelector('summary')?.focus();
    });
  });
})();


/* ---------- Progressive Web App: safe service-worker registration ---------- */
(() => {
  'use strict';
  if (!('serviceWorker' in navigator)) return;

  const appScript = document.querySelector('script[src*="/js/app.js"]');
  if (!appScript) return;

  const scriptUrl = new URL(appScript.src, window.location.href);
  const suffix = '/js/app.js';
  const basePath = scriptUrl.pathname.endsWith(suffix)
    ? scriptUrl.pathname.slice(0, -suffix.length).replace(/\/$/, '')
    : '';
  const workerUrl = `${basePath}/service-worker.js`;
  const scope = `${basePath}/`;

  // Service workers require HTTPS in production (localhost is allowed for development).
  if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') return;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register(workerUrl, { scope })
      .catch((error) => console.warn('Zayan Hardware offline support could not start:', error));
  }, { once: true });
})();
