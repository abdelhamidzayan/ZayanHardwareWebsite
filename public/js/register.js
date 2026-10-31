(() => {
  'use strict';

  const form = document.querySelector('[data-register-wizard]');
  if (!form) return;

  const root = form.closest('.wizard');
  const steps = [...form.querySelectorAll('[data-step]')];
  const stepperItems = [...root.querySelectorAll('[data-stepper]')];
  const initialStep = Number(form.dataset.initialStep || 1);
  const displayName = form.querySelector('#display_name');
  const contactType = form.querySelector('[data-contact-type]');
  const phoneFields = form.querySelector('[data-phone-fields]');
  const emailFields = form.querySelector('[data-email-fields]');
  const phoneInput = form.querySelector('[data-phone-input]');
  const emailLocal = form.querySelector('[data-email-local]');
  const emailDomain = form.querySelector('[data-email-domain]');
  const countryValue = form.querySelector('[data-country-value]');
  const countryToggle = form.querySelector('[data-country-toggle]');
  const countryList = form.querySelector('[data-country-list]');
  const countryFlag = form.querySelector('[data-country-flag]');
  const countryDial = form.querySelector('[data-country-dial]');
  const contactSwitch = form.querySelector('[data-contact-switch]');
  const contactIcon = form.querySelector('[data-contact-icon]');
  const contactLabel = form.querySelector('[data-contact-label]');
  const contactHint = form.querySelector('[data-contact-hint]');
  const password = form.querySelector('[data-password-input]');
  const termsAcceptance = form.querySelector('[data-terms-acceptance]');
  const username = form.querySelector('[data-username-input]');
  const usernameStatus = form.querySelector('[data-username-status]');
  const suggestionBox = form.querySelector('[data-username-suggestions]');
  const boardExperienceWrap = form.querySelector('[data-board-experience]');
  const boardExperience = form.querySelector('[data-board-select]');
  const profileFields = [...form.querySelectorAll('[data-profile-field]')];
  const profileSubmit = form.querySelector('[data-profile-submit]');
  const profileAction = form.querySelector('[data-profile-action]');
  const endpoint = form.action.replace(/\/register\/?$/, '/register/usernames');
  let activeStep = Math.min(3, Math.max(1, initialStep));
  let checkTimer = null;
  let checkSequence = 0;
  let suggestionOffset = 0;
  let suggestedNames = [];
  let usernameTouched = false;
  let autoSuggestedUsername = '';
  const draftStorageKey = 'zayan-hardware:register-draft:v1';
  if (form.dataset.clearDraft === 'true') {
    try {
      sessionStorage.removeItem(draftStorageKey);
    } catch (_) {
      // Storage may be unavailable; the form still starts from server-provided defaults.
    }
  }
  const draftMaxAge = 2 * 60 * 60 * 1000;
  let restoringDraft = false;

  const readDraft = () => {
    try {
      const raw = sessionStorage.getItem(draftStorageKey);
      if (!raw) return null;
      const draft = JSON.parse(raw);
      if (!draft || Date.now() - Number(draft.savedAt || 0) > draftMaxAge) {
        sessionStorage.removeItem(draftStorageKey);
        return null;
      }
      return draft;
    } catch (_) {
      return null;
    }
  };

  const restoreDraft = () => {
    const draft = readDraft();
    if (!draft || !Array.isArray(draft.fields)) return;
    restoringDraft = true;
    const usedFields = new Set();
    draft.fields.forEach((saved) => {
      if (!saved.name || saved.type === 'password' || saved.name === '_token' || saved.name === 'wizard_action') return;
      const candidates = Array.from(form.elements).filter((field) =>
        !usedFields.has(field) && field.name === saved.name && field.type === saved.type
      );
      const field = (saved.type === 'checkbox' || saved.type === 'radio')
        ? candidates.find((candidate) => candidate.value === saved.fieldValue) || candidates[0]
        : candidates[0];
      if (!field) return;
      usedFields.add(field);
      if (field.type === 'checkbox' || field.type === 'radio') field.checked = Boolean(saved.checked);
      else if (typeof saved.value === 'string') field.value = saved.value;
    });
    const hasServerErrors = Array.from(form.querySelectorAll('[data-error-for]'))
      .some((output) => output.textContent.trim() !== '');
    if (!hasServerErrors && Number.isInteger(draft.activeStep)) {
      activeStep = Math.min(3, Math.max(1, draft.activeStep));
    }
    usernameTouched = Boolean(draft.usernameTouched);
    autoSuggestedUsername = typeof draft.autoSuggestedUsername === 'string' ? draft.autoSuggestedUsername : '';
    restoringDraft = false;
  };

  const saveDraft = () => {
    if (restoringDraft) return;
    try {
      const fields = Array.from(form.elements).map((field) => ({
        name: field.name || '',
        type: field.type || '',
        value: field.type === 'password' || field.name === '_token' || field.name === 'wizard_action'
          ? ''
          : (field.type === 'checkbox' || field.type === 'radio' ? '' : String(field.value ?? '')),
        checked: field.type === 'checkbox' || field.type === 'radio' ? Boolean(field.checked) : false,
        fieldValue: field.type === 'checkbox' || field.type === 'radio' ? String(field.value ?? '') : '',
      }));
      sessionStorage.setItem(draftStorageKey, JSON.stringify({
        savedAt: Date.now(),
        activeStep,
        usernameTouched,
        autoSuggestedUsername,
        fields,
      }));
    } catch (_) {
      // If storage is unavailable, the form remains usable normally.
    }
  };

  restoreDraft();

  const showIsoFlags = /Win/i.test((navigator.platform || '') + ' ' + (navigator.userAgent || ''));
  if (showIsoFlags) {
    countryFlag.textContent = countryValue.value;
    form.querySelectorAll('.country-list__flag').forEach((node) => { node.textContent = node.closest('[data-country]')?.dataset.country || node.textContent; });
  }

  const toLatinDigits = (value) => value.replace(/[٠-٩۰-۹]/g, (digit) => {
    const arabic = '٠١٢٣٤٥٦٧٨٩';
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const index = arabic.indexOf(digit);
    return String(index >= 0 ? index : persian.indexOf(digit));
  });
  const digitsOnly = (value) => toLatinDigits(value).replace(/\D/g, '');
  const reserved = new Set(['admin', 'administrator', 'moderator', 'support', 'root', 'system', 'staff', 'zayan', 'hardware', 'hardwaretech']);
  const validUsername = (value) => /^[a-z0-9_]{3,20}$/.test(value) && !reserved.has(value);
  const fieldError = (key) => form.querySelector('[data-error-for="' + key + '"]');
  const setFieldError = (key, message) => {
    const output = fieldError(key);
    if (output) output.textContent = message;
    const field = key === 'contact'
      ? form.querySelector('[data-phone-fields], [data-email-fields]')?.closest('.field')
      : form.querySelector('#' + key)?.closest('.field') || form.querySelector('[name="' + key + '"]')?.closest('.field');
    const input = key === 'contact'
      ? (contactType.value === 'email' ? emailLocal : phoneInput)
      : field?.querySelector('input, select');
    if (input) input.setAttribute('aria-invalid', message ? 'true' : 'false');
  };

  const setStep = (step) => {
    activeStep = step;
    saveDraft();
    root.classList.add('wizard--on');
    steps.forEach((section) => {
      const current = Number(section.dataset.step) === step;
      section.hidden = !current;
      section.setAttribute('aria-hidden', current ? 'false' : 'true');
    });
    stepperItems.forEach((item) => {
      const number = Number(item.dataset.stepper);
      item.classList.toggle('is-active', number === step);
      item.classList.toggle('is-done', number < step);
      if (number === step) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
  };

  const showContactMode = (mode) => {
    const emailMode = mode === 'email';
    contactType.value = emailMode ? 'email' : 'phone';
    phoneFields.hidden = emailMode;
    emailFields.hidden = !emailMode;
    phoneInput.disabled = emailMode;
    phoneInput.required = !emailMode;
    emailLocal.disabled = !emailMode;
    emailLocal.required = emailMode;
    emailDomain.disabled = !emailMode;
    emailDomain.required = emailMode;
    contactLabel.textContent = emailMode ? 'البريد الإلكتروني' : 'رقم الهاتف';
    contactHint.textContent = emailMode
      ? 'يمكنك لصق البريد الإلكتروني كاملًا في الخانة الأولى.'
      : 'مثال: 01033771537 في مصر يُحفظ +201033771537.';
    contactSwitch.setAttribute('aria-label', emailMode ? 'التبديل إلى رقم الهاتف' : 'التبديل إلى البريد الإلكتروني');
    contactSwitch.setAttribute('aria-pressed', String(emailMode));
    contactSwitch.title = emailMode ? 'التبديل إلى رقم الهاتف' : 'التبديل إلى البريد الإلكتروني';
    contactIcon.innerHTML = emailMode
      ? '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>'
      : '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/>';
  };

  const closeCountryList = () => {
    countryList.hidden = true;
    countryToggle.setAttribute('aria-expanded', 'false');
  };

  const chooseCountry = (button) => {
    countryValue.value = button.dataset.country;
    countryFlag.textContent = showIsoFlags ? button.dataset.country : (button.dataset.flag || button.dataset.country);
    countryDial.textContent = '+' + button.dataset.dial;
    countryList.querySelectorAll('[data-country]').forEach((item) => {
      item.setAttribute('aria-selected', item === button ? 'true' : 'false');
    });
    closeCountryList();
    saveDraft();
    phoneInput.focus();
  };

  const syncCountryControl = () => {
    const selected = countryList.querySelector('[data-country="' + CSS.escape(countryValue.value) + '"]');
    if (!selected) return;
    countryFlag.textContent = showIsoFlags ? selected.dataset.country : (selected.dataset.flag || selected.dataset.country);
    countryDial.textContent = '+' + selected.dataset.dial;
    countryList.querySelectorAll('[data-country]').forEach((item) => item.setAttribute('aria-selected', item === selected ? 'true' : 'false'));
  };

  countryToggle.addEventListener('click', () => {
    const open = countryList.hidden;
    countryList.hidden = !open;
    countryToggle.setAttribute('aria-expanded', String(open));
  });
  countryList.querySelectorAll('[data-country]').forEach((button) => {
    button.addEventListener('click', () => chooseCountry(button));
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.wizard-contact__phone')) closeCountryList();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeCountryList();
  });

  contactSwitch.addEventListener('click', (event) => {
    event.preventDefault();
    const nextMode = contactType.value === 'phone' ? 'email' : 'phone';
    showContactMode(nextMode);
    setFieldError('contact', '');
    closeCountryList();
    saveDraft();
    (nextMode === 'phone' ? phoneInput : emailLocal).focus();
  });

  emailLocal.addEventListener('input', () => {
    const value = emailLocal.value.trim();
    const at = value.indexOf('@');
    if (at > -1) {
      emailLocal.value = value.slice(0, at);
      const pastedDomain = value.slice(at + 1);
      if (pastedDomain) emailDomain.value = pastedDomain;
      emailDomain.focus();
      emailDomain.setSelectionRange(emailDomain.value.length, emailDomain.value.length);
    }
  });

  phoneInput.addEventListener('blur', () => {
    const raw = phoneInput.value.trim();
    if (!raw || raw.startsWith('+') || toLatinDigits(raw).startsWith('00')) return;
    const digits = digitsOnly(raw);
    if (digits) phoneInput.value = digits.replace(/^0+/, '');
  });

  form.querySelector('[data-password-toggle]').addEventListener('click', (event) => {
    const button = event.currentTarget;
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(show));
    button.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
  });

  const validateStepOne = () => {
    let valid = true;
    const name = displayName.value.trim().replace(/\s+/g, ' ');
    displayName.value = name;
    if (name.length < 2 || name.length > 60) {
      setFieldError('display_name', 'الاسم من 2 إلى 60 حرفًا');
      valid = false;
    } else setFieldError('display_name', '');

    if (contactType.value === 'phone') {
      const dial = countryList.querySelector('[data-country="' + CSS.escape(countryValue.value) + '"]')?.dataset.dial || '20';
      const digits = digitsOnly(phoneInput.value);
      let national = digits;
      if (national.startsWith('00')) national = national.slice(2);
      if (national.startsWith(dial) && national.length > dial.length + 6) national = national.slice(dial.length);
      national = national.replace(/^0+/, '');
      const phoneValid = dial === '20' ? /^1[0125]\d{8}$/.test(national) : /^\d{6,12}$/.test(national);
      if (!phoneValid) {
        setFieldError('contact', 'أدخل رقم هاتف صحيحًا');
        valid = false;
      } else setFieldError('contact', '');
    } else {
      const first = emailLocal.value.trim();
      const domain = emailDomain.value.trim();
      const address = first.includes('@') ? first : first + '@' + domain;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address) || address.length > 190) {
        setFieldError('contact', 'أدخل بريدًا إلكترونيًا صحيحًا');
        valid = false;
      } else setFieldError('contact', '');
    }

    if (password.value.length < 10) {
      setFieldError('password', 'كلمة المرور يجب أن تكون 10 أحرف على الأقل');
      valid = false;
    } else if (new TextEncoder().encode(password.value).length > 72) {
      setFieldError('password', 'كلمة المرور طويلة جدًا (الحد 72 بايت)');
      valid = false;
    } else setFieldError('password', '');

    if (!termsAcceptance || !termsAcceptance.checked) {
      setFieldError('terms_acceptance', 'يجب الموافقة على قواعد المنصة وسياسة الخصوصية.');
      valid = false;
    } else setFieldError('terms_acceptance', '');

    if (!valid) {
      const firstInvalid = form.querySelector('[aria-invalid="true"]')
        || (!termsAcceptance?.checked ? termsAcceptance : null);
      firstInvalid?.focus();
      firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
  };

  const requestAvailable = async (names) => {
    const params = new URLSearchParams();
    names.slice(0, 8).forEach((name) => params.append('names[]', name));
    const response = await fetch(endpoint + '?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!response.ok) return [];
    const data = await response.json();
    return Array.isArray(data.available) ? data.available : [];
  };

  const transliterate = (value) => {
    const known = {
      'عبد الرحمن':'abdelrahman','عبدالرحمن':'abdelrahman','عبد الله':'abdullah','عبدالله':'abdullah',
      'عبد العزيز':'abdulaziz','عبدالعزيز':'abdulaziz','عبد الملك':'abdulmalik','عبدالملك':'abdulmalik',
      'محمد':'mohamed','محمود':'mahmoud','مصطفى':'mostafa','مصطفي':'mostafa',
      'أحمد':'ahmed','احمد':'ahmed','إبراهيم':'ibrahim','ابراهيم':'ibrahim',
      'إسماعيل':'ismail','اسماعيل':'ismail','إسلام':'islam','اسلام':'islam',
      'علي':'ali','على':'ali','حسن':'hassan','حسين':'hussein','يوسف':'youssef',
      'عمر':'omar','عمرو':'amr','خالد':'khaled','طارق':'tarek','كريم':'karim',
      'سعيد':'saeed','زياد':'ziad','ياسين':'yassin','مروان':'marwan','نور':'nour',
      'هشام':'hesham','عادل':'adel','رامي':'ramy','زكريا':'zakaria','سارة':'sara',
      'ساره':'sara','منى':'mona','مريم':'maryam','آية':'aya','ايه':'aya',
      'ياسمين':'yasmin','فاطمة':'fatma','فاطمه':'fatma','نورهان':'nourhan',
      'دينا':'dina','ندى':'nada','ملك':'malak','حبيبة':'habiba','حبيبه':'habiba'
    };
    let result = String(value || '').toLowerCase();
    Object.keys(known).sort((a, b) => b.length - a.length).forEach((key) => {
      result = result.split(key).join(known[key]);
    });

    const letters = {
      'ا':'a','أ':'a','إ':'e','آ':'aa','ٱ':'a','ب':'b','ت':'t','ث':'th',
      'ج':'g','ح':'h','خ':'kh','د':'d','ذ':'z','ر':'r','ز':'z','س':'s',
      'ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f',
      'ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','ة':'a','و':'w',
      'ؤ':'w','ي':'y','ى':'a','ئ':'y','ء':'','پ':'p','چ':'ch','گ':'g'
    };
    result = result.replace(/[ء-يٱپچگ]/g, (letter) => letters[letter] ?? '');
    result = result.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return result
      .replace(/[^a-z0-9_\s.-]/g, ' ')
      .trim()
      .replace(/[.\s-]+/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '');
  };

  const makeCandidates = () => {
    const fullName = transliterate(displayName.value);
    const nameParts = fullName.split('_').filter(Boolean);
    const first = (nameParts[0] || '').replace(/[^a-z0-9]/g, '');
    const last = (nameParts.slice(1).join('_') || '').replace(/[^a-z0-9_]/g, '');

    // Never generate generic "tech_tech" names when the display name cannot be converted.
    if (first.length < 2) return [];
    const localSeed = contactType.value === 'email'
      ? transliterate(emailLocal.value.split('@')[0] || '')
      : '';
    const emailBase = (localSeed.split('_')[0] || '').replace(/[^a-z0-9]/g, '');
    const seeds = [first, emailBase].filter((seed) => seed.length >= 2);
    const candidates = [];
    const add = (value) => {
      value = value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 20);
      if (validUsername(value) && !candidates.includes(value)) candidates.push(value);
    };
    add(first + '_' + last);
    add(first + last);
    add(first + '27');
    add(first + '_204');
    add(first + '_fix');
    add('tech_' + first);
    add(first + '_hw');
    add(first + '_' + last + '27');
    seeds.forEach((seed) => {
      add(seed + String(Math.floor(10 + Math.random() * 90)));
      add(seed + '_hw');
    });
    while (candidates.length < 8) add('tech' + String(Math.floor(1000 + Math.random() * 9000)));
    return candidates.slice(0, 8);
  };

  const renderSuggestions = async (refresh) => {
    if (refresh) suggestionOffset += 1;
    const candidates = makeCandidates();
    if (!candidates.length) {
      suggestedNames = [];
      suggestionBox.replaceChildren();
      if (autoSuggestedUsername && username.value === autoSuggestedUsername && !usernameTouched) {
        username.value = '';
        autoSuggestedUsername = '';
        usernameStatus.textContent = '';
        usernameStatus.classList.remove('is-error', 'is-available');
        saveDraft();
      }
      return;
    }
    const rotated = candidates.slice(suggestionOffset % candidates.length).concat(candidates.slice(0, suggestionOffset % candidates.length));
    suggestedNames = await requestAvailable(rotated);
    suggestionBox.replaceChildren();
    suggestedNames.slice(0, 3).forEach((name) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'wizard-suggestion';
      button.textContent = '@' + name;
      button.addEventListener('click', () => {
        usernameTouched = true;
        autoSuggestedUsername = '';
        username.value = name;
        username.dispatchEvent(new Event('input', { bubbles: true }));
        checkUsername();
      });
      suggestionBox.append(button);
    });
    if (!usernameTouched && suggestedNames.length) {
      username.value = suggestedNames[0];
      autoSuggestedUsername = suggestedNames[0];
      saveDraft();
      checkUsername();
    }
  };

  const checkUsername = async () => {
    const value = username.value.trim().toLowerCase();
    username.value = value;
    const sequence = ++checkSequence;
    if (!validUsername(value)) {
      usernameStatus.textContent = value ? 'اسم المستخدم غير متاح' : '';
      usernameStatus.classList.toggle('is-error', Boolean(value));
      usernameStatus.classList.remove('is-available');
      return false;
    }
    usernameStatus.textContent = 'جارٍ التحقق…';
    usernameStatus.classList.remove('is-error', 'is-available');
    try {
      const available = await requestAvailable([value]);
      if (sequence !== checkSequence) return false;
      const isAvailable = available.includes(value);
      usernameStatus.textContent = isAvailable ? 'متاح' : 'اسم المستخدم غير متاح';
      usernameStatus.classList.toggle('is-available', isAvailable);
      usernameStatus.classList.toggle('is-error', !isAvailable);
      return isAvailable;
    } catch (_) {
      usernameStatus.textContent = 'تعذر التحقق الآن';
      usernameStatus.classList.add('is-error');
      return false;
    }
  };

  const validateStepTwo = async () => {
    const name = username.value.trim().toLowerCase();
    if (!validUsername(name)) {
      usernameStatus.textContent = 'اسم المستخدم من 3 إلى 20 حرفًا، إنجليزية صغيرة وأرقام و _';
      usernameStatus.classList.add('is-error');
      username.focus();
      return false;
    }
    const available = await checkUsername();
    if (!available) {
      username.focus();
      return false;
    }

    const focuses = form.querySelectorAll('input[name="device_focus[]"]:checked');
    if (!focuses.length) {
      setFieldError('device_focus', 'اختر مجال تخصصك، ويمكنك اختيار المجالين معًا');
      form.querySelector('input[name="device_focus[]"]')?.focus();
      return false;
    }
    setFieldError('device_focus', '');

    const level = form.querySelector('input[name="tech_level"]:checked');
    if (!level) {
      setFieldError('tech_level', 'اختر مستواك الفني');
      form.querySelector('input[name="tech_level"]')?.focus();
      return false;
    }
    setFieldError('tech_level', '');

    if (level.value === 'board' && !boardExperience.value) {
      setFieldError('board_experience', 'حدد سنوات الخبرة');
      boardExperience.focus();
      return false;
    }
    setFieldError('board_experience', '');
    return true;
  };

  const goNext = async () => {
    if (activeStep === 1) {
      if (!validateStepOne()) return;
      setStep(2);
      if (!username.value.trim()) await renderSuggestions(false);
      else checkUsername();
      return;
    }
    if (activeStep === 2 && await validateStepTwo()) setStep(3);
  };

  root.querySelectorAll('[data-next]').forEach((button) => button.addEventListener('click', goNext));
  root.querySelectorAll('[data-back]').forEach((button) => button.addEventListener('click', () => setStep(Math.max(1, activeStep - 1))));

  username.addEventListener('input', () => {
    if (username.value !== autoSuggestedUsername) usernameTouched = true;
    checkSequence += 1;
    clearTimeout(checkTimer);
    usernameStatus.textContent = '';
    usernameStatus.classList.remove('is-error', 'is-available');
    checkTimer = setTimeout(checkUsername, 500);
  });
  displayName.addEventListener('input', () => {
    if (!usernameTouched) {
      clearTimeout(checkTimer);
      checkTimer = setTimeout(() => renderSuggestions(false), 500);
    }
  });
  emailLocal.addEventListener('input', () => {
    if (!usernameTouched) {
      clearTimeout(checkTimer);
      checkTimer = setTimeout(() => renderSuggestions(false), 500);
    }
  });

  form.querySelectorAll('input[name="tech_level"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      const isBoard = radio.checked && radio.value === 'board';
      boardExperienceWrap.hidden = !isBoard;
      boardExperience.disabled = !isBoard;
      boardExperience.required = isBoard;
      if (!isBoard) {
        boardExperience.value = '';
        setFieldError('board_experience', '');
      }
    });
  });

  form.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.isComposing || event.target.matches('textarea, select, button')) return;
    if (activeStep < 3) {
      event.preventDefault();
      goNext();
    }
  });

  const updateProfileSubmit = () => {
    const hasProfileData = profileFields.some((field) => field.value.trim() !== '');
    profileSubmit.textContent = hasProfileData ? 'إنهاء' : 'تخطي';
    profileSubmit.value = hasProfileData ? 'finish' : 'skip';
    profileAction.value = hasProfileData ? 'finish' : 'skip';
  };

  profileFields.forEach((field) => {
    field.addEventListener('input', updateProfileSubmit);
    field.addEventListener('change', updateProfileSubmit);
  });

  form.addEventListener('submit', (event) => {
    if (activeStep !== 3) {
      event.preventDefault();
      goNext();
      return;
    }
    if (!validateStepOne()) {
      event.preventDefault();
      setStep(1);
      return;
    }
    if (!validUsername(username.value.trim().toLowerCase())) {
      event.preventDefault();
      setStep(2);
      username.focus();
      return;
    }
    // Keep the draft until the server confirms registration; validation errors may return to this form.
  });

  showContactMode(contactType.value);
  syncCountryControl();
  updateProfileSubmit();
  setStep(activeStep);
  form.addEventListener('input', saveDraft);
  form.addEventListener('change', saveDraft);
  if (activeStep === 2) {
    renderSuggestions(false);
    if (username.value.trim()) checkUsername();
  }
})();
