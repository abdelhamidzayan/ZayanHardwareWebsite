<?php
$errors = $errors ?? [];
$old    = $old ?? [];

$val     = fn (string $key): string => $e($old[$key] ?? '');
$err     = fn (string $key): string => $e($errors[$key] ?? '');
$invalid = fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true"' : '';

$experience = [
    'lt1'  => 'أقل من سنة',
    '1-3'  => 'من 1 إلى 3 سنوات',
    '3-5'  => 'من 3 إلى 5 سنوات',
    '5-10' => 'من 5 إلى 10 سنوات',
    'gt10' => 'أكثر من 10 سنوات',
];
?>
<section class="auth">
    <div class="card auth__card auth__card--wide">
        <div>
            <p class="auth__sub">المرحلة 1 من 3 · بيانات الحساب الأساسية</p>
            <h1 class="auth__title">إنشاء حساب فنّي</h1>
            <p class="auth__sub">ابدأ بحسابك، وبعدها يمكنك استكمال ملفك الفني أو تخطي الخطوات الاختيارية.</p>
        </div>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register" novalidate data-validate>
            <?= $csrf() ?>

            <div class="field">
                <label class="field__label" for="display_name">الاسم الظاهر</label>
                <input class="field__input" id="display_name" name="display_name" type="text"
                       value="<?= $val('display_name') ?>" autocomplete="name" maxlength="60"
                       data-rules="required"<?= $invalid('display_name') ?>>
                <p class="field__error" aria-live="polite"><?= $err('display_name') ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="username">اسم المستخدم المقترح</label>
                <input class="field__input field__input--ltr" id="username" name="username" type="text"
                       value="<?= $val('username') ?>" autocomplete="username" autocapitalize="none"
                       spellcheck="false" maxlength="30" data-rules="required username"<?= $invalid('username') ?>>
                <p class="field__hint">اقترحناه من اسمك الظاهر، وتقدر تعدّله. استخدم الإنجليزية الصغيرة والأرقام و _ فقط.</p>
                <p class="field__error" aria-live="polite"><?= $err('username') ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="email">البريد الإلكتروني</label>
                <input class="field__input field__input--ltr" id="email" name="email" type="email"
                       value="<?= $val('email') ?>" autocomplete="email" autocapitalize="none"
                       spellcheck="false" data-rules="email"<?= $invalid('email') ?>>
                <p class="field__error" aria-live="polite"><?= $err('email') ?></p>
                <p class="field__hint">يمكنك تركه فارغًا إذا أدخلت رقم الهاتف.</p>
            </div>

            <div class="field">
                <label class="field__label" for="password">كلمة المرور</label>
                <div class="field__password">
                    <input class="field__input field__input--ltr" id="password" name="password" type="password"
                           autocomplete="new-password" data-rules="required min:10"<?= $invalid('password') ?>>
                    <button class="field__toggle" type="button" data-toggle-password hidden
                            aria-label="إظهار كلمة المرور" aria-pressed="false">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <p class="field__hint">10 أحرف على الأقل.</p>
                <p class="field__error" aria-live="polite"><?= $err('password') ?></p>
            </div>

<fieldset class="field">
                <legend class="field__label">مجالات إصلاح البوردة</legend>
                <?php $oldSkills = is_array($old['skill_domains'] ?? null) ? $old['skill_domains'] : []; ?>
                <?php foreach ($skillDomains as $category) : ?>
                    <label class="check">
                        <input type="checkbox" name="skill_domains[]" value="<?= $e($category['slug']) ?>"<?= in_array($category['slug'], $oldSkills, true) ? ' checked' : '' ?>>
                        <span><?= $e($category['name']) ?></span>
                    </label>
                <?php endforeach; ?>
                <p class="field__hint">يمكن اختيار أكثر من مجال، ويمكن تعديل ملفك الفني لاحقًا.</p>
                <p class="field__error" aria-live="polite"><?= $err('skill_domains') ?></p>
            </fieldset>

<div class="field">
                <label class="field__label" for="phone">رقم الهاتف</label>
                <input class="field__input field__input--ltr" id="phone" name="phone" type="tel"
                       value="<?= $val('phone') ?>" autocomplete="tel" data-rules="tel"<?= $invalid('phone') ?>>
                <p class="field__error" aria-live="polite"><?= $err('phone') ?></p>
                <p class="field__hint">أدخل البريد الإلكتروني أو رقم الهاتف على الأقل.</p>
                <p class="field__error" aria-live="polite"><?= $err('contact') ?></p>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="terms" value="1" data-rules="required"<?= ($old['terms'] ?? '') === '1' ? ' checked' : '' ?>>
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
    var displayName = document.getElementById('display_name');
    var username = document.getElementById('username');
    if (!displayName || !username) return;
    var edited = username.value.trim() !== '';
    username.addEventListener('input', function () { edited = true; });
    var arabic = {'ا':'a','أ':'a','إ':'e','آ':'a','ب':'b','ت':'t','ث':'th','ج':'g','ح':'h','خ':'kh','د':'d','ذ':'z','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m','ن':'n','ه':'h','ة':'a','و':'w','ي':'y','ى':'a','ء':'','ؤ':'w','ئ':'y'};
    function suggest(value) {
        var result = '';
        for (var i = 0; i < value.length; i++) {
            var ch = value[i].toLowerCase();
            result += arabic[ch] !== undefined ? arabic[ch] : ch;
        }
        result = result.normalize ? result.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : result;
        return result.replace(/[^a-z0-9_\s-]/g, '').trim().replace(/[\s-]+/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '').slice(0, 30);
    }
    displayName.addEventListener('input', function () {
        if (!edited) username.value = suggest(displayName.value);
    });
    if (!username.value.trim() && displayName.value.trim()) username.value = suggest(displayName.value);
})();
</script>

