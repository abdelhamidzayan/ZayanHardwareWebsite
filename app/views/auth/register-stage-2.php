<?php
$errors = $errors ?? [];
$old = $old ?? [];
$val = fn (string $key): string => $e($old[$key] ?? '');
$err = fn (string $key): string => $e($errors[$key] ?? '');
$device = $old['device_specialization'] ?? '';
$level = $old['technician_level'] ?? '';
$experience = $old['experience'] ?? '';
$experienceOptions = ['lt1' => 'أقل من سنة', '1-3' => '1–3 سنوات', '3-5' => '3–5 سنوات', '5-10' => '5–10 سنوات', 'gt10' => 'أكثر من 10 سنوات'];
?>
<style>
.registration-progress{display:flex;align-items:flex-start;gap:0;margin:8px 0 28px;direction:rtl}
.registration-progress__step{flex:1;position:relative;text-align:center;color:var(--muted,#8b929b);font-size:12px}
.registration-progress__step:not(:last-child):before{content:"";position:absolute;top:15px;right:calc(50% + 21px);left:calc(-50% + 21px);height:2px;background:var(--border,#30363d)}
.registration-progress__number{display:flex;width:32px;height:32px;border-radius:50%;align-items:center;justify-content:center;margin:0 auto 8px;border:1px solid var(--border,#30363d);background:var(--surface,#15191e);font-weight:700}
.registration-progress__step.is-active{color:var(--text,#f5f5f5);font-weight:700}
.registration-progress__step.is-active .registration-progress__number{background:#c49a42;border-color:#c49a42;color:#101010}
.registration-progress__step.is-done .registration-progress__number{border-color:#c49a42;color:#c49a42}
.choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.choice-card{position:relative;display:flex;align-items:center;gap:12px;padding:16px;border:1px solid var(--border,#30363d);border-radius:12px;cursor:pointer;min-height:82px}
.choice-card:has(input:checked){border-color:#c49a42;box-shadow:0 0 0 1px #c49a42}
.choice-card input{accent-color:#c49a42}
.choice-card__icon{width:34px;height:34px;flex:0 0 34px}
.choice-card__icon svg{width:100%;height:100%}
.username-suggestions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.username-suggestion{border:1px solid var(--border,#30363d);background:var(--surface,#15191e);color:inherit;border-radius:20px;padding:6px 10px;font-size:12px;cursor:pointer;direction:ltr}
.username-suggestion:hover{border-color:#c49a42}
.test-status{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px dashed var(--border,#30363d);border-radius:10px;font-size:13px}
.test-status__dot{width:9px;height:9px;flex:0 0 9px;border-radius:50%;background:#c49a42;margin-top:5px}
@media(max-width:420px){.choice-card{padding:12px;gap:8px}.choice-card__icon{width:28px;height:28px;flex-basis:28px}}
</style>
<section class="auth">
    <div class="card auth__card auth__card--wide">
        <div class="registration-progress" aria-label="مراحل إنشاء الحساب">
            <div class="registration-progress__step is-done"><span class="registration-progress__number">✓</span><span>الحساب</span></div>
            <div class="registration-progress__step is-active" aria-current="step"><span class="registration-progress__number">2</span><span>التخصص</span></div>
            <div class="registration-progress__step"><span class="registration-progress__number">3</span><span>الملف الشخصي</span></div>
        </div>
        <h1 class="auth__title">خلّينا نتعرف عليك</h1>
        <p class="auth__sub">اختار اسم المستخدم وتخصصك في صيانة الأجهزة ومستوى خبرتك.</p>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register/stage/2" id="register-stage-two">
            <?= $csrf() ?>
            <div class="field">
                <label class="field__label" for="username">اسم المستخدم</label>
                <input class="field__input field__input--ltr" id="username" name="username" type="text"
                       value="<?= $val('username') !== '' ? $val('username') : $e($user['username'] ?? '') ?>"
                       maxlength="20" minlength="3" pattern="[a-z0-9_]{3,20}" autocomplete="username"
                       autocapitalize="none" spellcheck="false" required>
                <p class="field__hint">حد أقصى 20 حرفًا. تقدر تغيّره بعدين من ملفك الشخصي.</p>
                <div class="username-suggestions" aria-label="اقتراحات لاسم المستخدم">
                    <?php foreach (($usernameSuggestions ?? []) as $suggestion) : ?>
                        <button type="button" class="username-suggestion" data-username-suggestion="<?= $e($suggestion) ?>"><?= $e($suggestion) ?></button>
                    <?php endforeach; ?>
                </div>
                <p class="field__error" aria-live="polite"><?= $err('username') ?></p>
            </div>

            <fieldset class="field">
                <legend class="field__label">تخصصك بالنسبة للأجهزة</legend>
                <div class="choice-grid">
                    <label class="choice-card">
                        <input type="radio" name="device_specialization" value="iphone" required<?= $device === 'iphone' ? ' checked' : '' ?>>
                        <span class="choice-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 40 40"><path fill="#111" d="M25.9 7.2c1.5-1.8 1.3-3.4 1.3-3.4s-2.4.1-4 2c-1.4 1.6-1.2 3.4-1.2 3.4s2.3.2 3.9-2zM30.7 21.4c0-4.1 3.3-6.1 3.4-6.2-1.9-2.8-4.8-3.2-5.8-3.2-2.5-.3-4.9 1.5-6.2 1.5s-3.3-1.5-5.4-1.4c-2.8 0-5.4 1.7-6.9 4.2-2.9 5-.7 12.5 2.1 16.6 1.4 2 3 4.2 5.1 4.1 2-.1 2.8-1.3 5.3-1.3s3.2 1.3 5.3 1.3c2.2 0 3.5-2 4.9-4.1 1.6-2.3 2.2-4.6 2.2-4.7s-4-.0-4-6.8z"/></svg>
                        </span>
                        <span><strong>iPhone</strong><br><small>Apple iOS</small></span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="device_specialization" value="android" required<?= $device === 'android' ? ' checked' : '' ?>>
                        <span class="choice-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 40 40"><g fill="#3DDC84"><path d="M10.5 14.5h19v13.2a2.3 2.3 0 0 1-2.3 2.3H12.8a2.3 2.3 0 0 1-2.3-2.3z"/><path d="M8 15h24a12 12 0 0 0-24 0z"/><rect x="5.5" y="17" width="3" height="10" rx="1.5"/><rect x="31.5" y="17" width="3" height="10" rx="1.5"/><rect x="13" y="29" width="4" height="7" rx="2"/><rect x="23" y="29" width="4" height="7" rx="2"/><path d="m12 5 2-3 .9.5-2 3zM28 5l-2-3-.9.5 2 3z"/></g><g fill="#111"><circle cx="14.5" cy="11" r="1"/><circle cx="25.5" cy="11" r="1"/></g></svg>
                        </span>
                        <span><strong>Android</strong><br><small>Android devices</small></span>
                    </label>
                </div>
                <p class="field__error" aria-live="polite"><?= $err('device_specialization') ?></p>
            </fieldset>

            <fieldset class="field">
                <legend class="field__label">مستوى شغلك</legend>
                <div class="choice-grid">
                    <label class="choice-card">
                        <input type="radio" name="technician_level" value="fast" required<?= $level === 'fast' ? ' checked' : '' ?>>
                        <span><strong>فاست</strong><br><small>صيانة سريعة ومكونات خارجية</small></span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="technician_level" value="board" required<?= $level === 'board' ? ' checked' : '' ?>>
                        <span><strong>بورد</strong><br><small>تشخيص وإصلاح على مستوى البوردة</small></span>
                    </label>
                </div>
                <p class="field__hint">الفاست مسار بداية، والبورد مسار متخصص في إصلاح الدوائر.</p>
                <p class="field__error" aria-live="polite"><?= $err('technician_level') ?></p>
            </fieldset>

            <div class="field" id="board-experience" <?= $level !== 'board' ? 'hidden' : '' ?>>
                <label class="field__label" for="experience">سنوات خبرتك في البورد</label>
                <select class="field__input" id="experience" name="experience" <?= $level === 'board' ? 'required' : 'disabled' ?>>
                    <option value="">اختار عدد السنوات</option>
                    <?php foreach ($experienceOptions as $key => $label) : ?>
                        <option value="<?= $e($key) ?>"<?= $experience === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="test-status"><span class="test-status__dot"></span><span><strong>حالة اختبار المستوى: لم يُختبر بعد</strong><br>اختبار مستوى البورد هيساعد في توثيق خبرتك، وهتظهر نتيجة الاختبار هنا عند تفعيله.</span></div>
                <p class="field__error" aria-live="polite"><?= $err('experience') ?></p>
            </div>

            <button class="btn btn--primary btn--block" type="submit">حفظ ومتابعة</button>
        </form>
    </div>
</section>
<script>
(function () {
    var username = document.getElementById('username');
    document.querySelectorAll('[data-username-suggestion]').forEach(function (button) {
        button.addEventListener('click', function () { username.value = button.getAttribute('data-username-suggestion'); });
    });
    var experienceWrap = document.getElementById('board-experience');
    var experience = document.getElementById('experience');
    function updateLevel() {
        var selected = document.querySelector('input[name="technician_level"]:checked');
        var isBoard = selected && selected.value === 'board';
        experienceWrap.hidden = !isBoard;
        experience.disabled = !isBoard;
        experience.required = !!isBoard;
        if (!isBoard) experience.value = '';
    }
    document.querySelectorAll('input[name="technician_level"]').forEach(function (radio) {
        radio.addEventListener('change', updateLevel);
    });
    updateLevel();
})();
</script>
