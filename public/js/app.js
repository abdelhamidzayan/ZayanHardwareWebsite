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


/* ---------- Login identifier: phone with country code or email ---------- */
(() => {
  'use strict';

  const form = document.querySelector('[data-login-identifier-form]');
  if (!form) return;

  const wrapper = form.querySelector('.login-identifier');
  const visibleInput = form.querySelector('[data-login-visible-input]');
  const hiddenInput = form.querySelector('#identifier');
  const countrySelect = form.querySelector('[data-login-country]');
  const phoneControls = form.querySelector('[data-login-phone-controls]');
  const label = form.querySelector('[data-login-identifier-label]');
  const switchButton = form.querySelector('[data-login-switch]');
  let mode = 'phone';
  let phoneValue = '';
  let emailValue = '';

  const emailIcon = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
  const phoneIcon = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h4l2 5-2.5 1.5a15 15 0 0 0 6 6L16 13l5 2v4c0 1.1-.9 2-2 2C10 21 3 14 3 5c0-1.1.9-2 2-2z"/></svg>';

  const digitsOnly = (value) => value.replace(/\D/g, '');

  const normalizedPhone = () => {
    const raw = visibleInput.value.trim();
    const digits = digitsOnly(raw);
    if (!digits) return '';
    if (raw.startsWith('+')) return '+' + digits;
    if (digits.startsWith('00')) return '+' + digits.slice(2);

    const code = digitsOnly(countrySelect.value);
    if (digits.startsWith(code) && digits.length > code.length + 6) return '+' + digits;
    return '+' + code + digits.replace(/^0+/, '');
  };

  const switchMode = () => {
    if (mode === 'phone') {
      phoneValue = visibleInput.value;
      mode = 'email';
      wrapper.classList.add('is-email');
      phoneControls.hidden = true;
      visibleInput.type = 'email';
      visibleInput.inputMode = 'email';
      visibleInput.autocomplete = 'email';
      visibleInput.placeholder = 'البريد الإلكتروني';
      visibleInput.dataset.rules = 'required email';
      visibleInput.value = emailValue;
      label.textContent = 'البريد الإلكتروني';
      switchButton.innerHTML = phoneIcon;
      switchButton.setAttribute('aria-label', 'التبديل إلى رقم الهاتف');
      switchButton.title = 'التبديل إلى رقم الهاتف';
    } else {
      emailValue = visibleInput.value;
      mode = 'phone';
      wrapper.classList.remove('is-email');
      phoneControls.hidden = false;
      visibleInput.type = 'tel';
      visibleInput.inputMode = 'tel';
      visibleInput.autocomplete = 'tel-national';
      visibleInput.placeholder = 'رقم الهاتف';
      visibleInput.dataset.rules = 'required tel';
      visibleInput.value = phoneValue;
      label.textContent = 'رقم الهاتف';
      switchButton.innerHTML = emailIcon;
      switchButton.setAttribute('aria-label', 'التبديل إلى البريد الإلكتروني');
      switchButton.title = 'التبديل إلى البريد الإلكتروني';
    }

    visibleInput.setAttribute('aria-invalid', 'false');
    const error = visibleInput.closest('.field')?.querySelector('.field__error');
    if (error) error.textContent = '';
    visibleInput.focus();
  };

  switchButton.addEventListener('click', switchMode);
  visibleInput.addEventListener('input', () => {
    if (mode === 'phone') phoneValue = visibleInput.value;
    else emailValue = visibleInput.value;
  });

  form.addEventListener('submit', () => {
    hiddenInput.value = mode === 'email' ? visibleInput.value.trim() : normalizedPhone();
  });
})();
