<?php
$errors = $errors ?? [];
$old = $old ?? [];
$val = fn (string $key): string => $e($old[$key] ?? '');
$err = fn (string $key): string => $e($errors[$key] ?? '');
$mode = ($old['contact_mode'] ?? 'phone') === 'email' ? 'email' : 'phone';
$emailParts = explode('@', (string) ($old['email'] ?? ''), 2);
?>
<style>
.registration-progress{display:flex;align-items:flex-start;gap:0;margin:8px 0 28px;direction:rtl}
.registration-progress__step{flex:1;position:relative;text-align:center;color:var(--muted,#8b929b);font-size:12px}
.registration-progress__step:not(:last-child):before{content:"";position:absolute;top:15px;right:calc(50% + 21px);left:calc(-50% + 21px);height:2px;background:var(--border,#30363d)}
.registration-progress__number{display:flex;width:32px;height:32px;border-radius:50%;align-items:center;justify-content:center;margin:0 auto 8px;border:1px solid var(--border,#30363d);background:var(--surface,#15191e);font-weight:700}
.registration-progress__step.is-active{color:var(--text,#f5f5f5);font-weight:700}
.registration-progress__step.is-active .registration-progress__number{background:#c49a42;border-color:#c49a42;color:#101010}
.registration-progress__step.is-done .registration-progress__number{border-color:#c49a42;color:#c49a42}
.contact-control{display:flex;direction:ltr;align-items:stretch;gap:8px}
.contact-control__switch{width:48px;min-width:48px;border:1px solid var(--border,#30363d);border-radius:10px;background:var(--surface,#15191e);color:inherit;display:grid;place-items:center;cursor:pointer}
.contact-control__switch svg{width:21px;height:21px;stroke:currentColor;fill:none;stroke-width:1.8}
.contact-control__body{flex:1;min-width:0;display:flex;direction:ltr;align-items:stretch;border:1px solid var(--border,#30363d);border-radius:10px;overflow:hidden}
.contact-control__body select,.contact-control__body input{min-width:0;border:0;border-radius:0;background:transparent;color:inherit;padding:12px;font:inherit}
.contact-control__body select{max-width:112px;border-right:1px solid var(--border,#30363d)}
.contact-control__body input{flex:1;width:100%;text-align:left;direction:ltr}
.contact-control__body input:focus,.contact-control__body select:focus{outline:0}
.contact-control__at{display:flex;align-items:center;padding:0 13px;border-right:1px solid var(--border,#30363d);font-weight:700;color:var(--muted,#8b929b)}
@media(max-width:420px){.contact-control__body select{max-width:96px;padding:9px 5px}.contact-control__body input{padding:10px 8px}}
</style>
<section class="auth">
    <div class="card auth__card">
        <div class="registration-progress" aria-label="مراحل إنشاء الحساب">
            <div class="registration-progress__step is-active" aria-current="step"><span class="registration-progress__number">1</span><span>الحساب</span></div>
            <div class="registration-progress__step"><span class="registration-progress__number">2</span><span>التخصص</span></div>
            <div class="registration-progress__step"><span class="registration-progress__number">3</span><span>الملف الشخصي</span></div>
        </div>
        <h1 class="auth__title">إنشاء حساب فنّي</h1>
        <p class="auth__sub">ابدأ بالبيانات الأساسية فقط. باقي التفاصيل في خطوات تقدر تتخطاها.</p>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register" novalidate data-validate id="register-stage-one">
            <?= $csrf() ?>
            <input type="hidden" name="contact_mode" id="contact_mode" value="<?= $mode ?>">

            <div class="field">
                <label class="field__label" for="display_name">الاسم الظاهر</label>
                <input class="field__input" id="display_name" name="display_name" type="text"
                       value="<?= $val('display_name') ?>" autocomplete="name" maxlength="60"
                       data-rules="required" required aria-invalid="<?= isset($errors['display_name']) ? 'true' : 'false' ?>">
                <p class="field__error" aria-live="polite"><?= $err('display_name') ?></p>
            </div>

            <div class="field">
                <label class="field__label" id="contact-label" for="phone">رقم الهاتف</label>
                <div class="contact-control">
                    <button class="contact-control__switch" type="button" id="contact-mode-switch"
                            aria-label="التبديل إلى البريد الإلكتروني" title="التبديل بين الهاتف والبريد الإلكتروني">
                        <svg id="contact-mode-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/></svg>
                    </button>
                    <div class="contact-control__body" id="phone-control" <?= $mode === 'email' ? 'hidden' : '' ?>>
                        <select id="country_code" name="country_code" aria-label="كود الدولة">
                            <option value="+20"<?= ($old['country_code'] ?? '+20') === '+20' ? ' selected' : '' ?>>🇪🇬 +20</option>
                            <option value="+966"<?= ($old['country_code'] ?? '+20') === '+966' ? ' selected' : '' ?>>🇸🇦 +966</option>
                            <option value="+971"<?= ($old['country_code'] ?? '+20') === '+971' ? ' selected' : '' ?>>🇦🇪 +971</option>
                            <option value="+965"<?= ($old['country_code'] ?? '+20') === '+965' ? ' selected' : '' ?>>🇰🇼 +965</option>
                            <option value="+974"<?= ($old['country_code'] ?? '+20') === '+974' ? ' selected' : '' ?>>🇶🇦 +974</option>
                            <option value="+962"<?= ($old['country_code'] ?? '+20') === '+962' ? ' selected' : '' ?>>🇯🇴 +962</option>
                            <option value="+1"<?= ($old['country_code'] ?? '+20') === '+1' ? ' selected' : '' ?>>🇺🇸 +1</option>
                            <option value="+44"<?= ($old['country_code'] ?? '+20') === '+44' ? ' selected' : '' ?>>🇬🇧 +44</option>
                        </select>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                               placeholder="010 1234 5678" value="<?= $mode === 'phone' ? $val('phone') : '' ?>"
                               aria-label="رقم الهاتف" <?= $mode === 'phone' ? 'required' : 'disabled' ?>>
                    </div>
                    <div class="contact-control__body" id="email-control" <?= $mode !== 'email' ? 'hidden' : '' ?>>
                        <input id="email_local" type="text" inputmode="email" autocomplete="off"
                               placeholder="name" value="<?= $mode === 'email' ? $e($emailParts[0] ?? '') : '' ?>"
                               aria-label="الجزء قبل علامة @ " <?= $mode === 'email' ? 'required' : 'disabled' ?>>
                        <span class="contact-control__at" aria-hidden="true">@</span>
                        <input id="email_domain" type="text" inputmode="email" autocomplete="off"
                               placeholder="example.com" value="<?= $mode === 'email' ? $e($emailParts[1] ?? '') : '' ?>"
                               aria-label="نطاق البريد الإلكتروني" <?= $mode === 'email' ? 'required' : 'disabled' ?>>
                        <input id="email" name="email" type="hidden" value="<?= $mode === 'email' ? $val('email') : '' ?>">
                    </div>
                </div>
                <p class="field__hint" id="contact-hint">هنضيف كود الدولة تلقائيًا. مثال: 01033771537 في مصر يتحفظ +201033771537.</p>
                <p class="field__error" aria-live="polite"><?= $err('phone') ?: $err('email') ?: $err('contact') ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="password">كلمة المرور</label>
                <div class="field__password">
                    <input class="field__input field__input--ltr" id="password" name="password" type="password"
                           autocomplete="new-password" minlength="10" maxlength="128" data-rules="required min:10" required>
                    <button class="field__toggle" type="button" data-toggle-password hidden
                            aria-label="إظهار كلمة المرور" aria-pressed="false">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <p class="field__hint">10 أحرف على الأقل.</p>
                <p class="field__error" aria-live="polite"><?= $err('password') ?></p>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="terms" value="1" data-rules="required" required<?= ($old['terms'] ?? '') === '1' ? ' checked' : '' ?>>
                    <span>أوافق على قواعد المنصة وسياسة الخصوصية.</span>
                </label>
                <p class="field__error" aria-live="polite"><?= $err('terms') ?></p>
            </div>

            <button class="btn btn--primary btn--block" type="submit">إنشاء الحساب والمتابعة</button>
        </form>
        <p class="auth__switch">لديك حساب؟ <a href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a></p>
    </div>
</section>
<script>
(function () {
    var mode = document.getElementById('contact_mode');
    var switcher = document.getElementById('contact-mode-switch');
    var icon = document.getElementById('contact-mode-icon');
    var phoneControl = document.getElementById('phone-control');
    var emailControl = document.getElementById('email-control');
    var phone = document.getElementById('phone');
    var email = document.getElementById('email');
    var emailLocal = document.getElementById('email_local');
    var emailDomain = document.getElementById('email_domain');
    var label = document.getElementById('contact-label');
    var hint = document.getElementById('contact-hint');
    function setMode(next) {
        mode.value = next;
        var useEmail = next === 'email';
        phoneControl.hidden = useEmail;
        emailControl.hidden = !useEmail;
        phone.disabled = useEmail;
        phone.required = !useEmail;
        emailLocal.disabled = !useEmail;
        emailLocal.required = useEmail;
        emailDomain.disabled = !useEmail;
        emailDomain.required = useEmail;
        label.textContent = useEmail ? 'البريد الإلكتروني' : 'رقم الهاتف';
        switcher.setAttribute('aria-label', useEmail ? 'التبديل إلى رقم الهاتف' : 'التبديل إلى البريد الإلكتروني');
        icon.innerHTML = useEmail
            ? '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>'
            : '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/>';
        hint.textContent = useEmail ? 'اكتب بريدك الإلكتروني كاملًا؛ علامة @ جزء من تنسيق الحقل.' : 'هنضيف كود الدولة تلقائيًا. مثال: 01033771537 في مصر يتحفظ +201033771537.';
    }
    switcher.addEventListener('click', function () { setMode(mode.value === 'phone' ? 'email' : 'phone'); });
    setMode(mode.value);

    var form = document.getElementById('register-stage-one');
    form.addEventListener('submit', function () {
        if (mode.value === 'email') {
            email.value = emailLocal.value.trim() + '@' + emailDomain.value.trim();
            return;
        }
        var raw = phone.value.trim().replace(/[\s()\-.]/g, '');
        var code = document.getElementById('country_code').value;
        var digits = raw.replace(/\D/g, '');
        if (raw.indexOf('+') === 0) phone.value = '+' + digits;
        else if (digits.indexOf('00') === 0) phone.value = '+' + digits.slice(2);
        else {
            var country = code.replace(/\D/g, '');
            if (digits.indexOf(country) === 0 && digits.length > country.length + 6) phone.value = '+' + digits;
            else phone.value = code + digits.replace(/^0+/, '');
        }
    });
})();
</script>
