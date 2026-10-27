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
