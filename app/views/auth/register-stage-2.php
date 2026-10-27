<?php
$errors = $errors ?? [];
$old = $old ?? [];
$oldDevices = is_array($old['device_specializations'] ?? null) ? $old['device_specializations'] : [];
?>
<section class="auth">
    <div class="card auth__card">
        <p class="auth__sub">المرحلة 2 من 3 · اختيارية</p>
        <h1 class="auth__title">بتشتغل على إيه؟</h1>
        <p class="auth__sub">حدد أنواع الأجهزة اللي بتتعامل معاها عشان نخصص لك تجربة أنسب. تقدر تتخطى المرحلة دي.</p>
        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register/stage/2">
            <?= $csrf() ?>
            <fieldset class="field">
                <legend class="field__label">الأجهزة</legend>
                <label class="check"><input type="checkbox" name="device_specializations[]" value="iphone"<?= in_array('iphone', $oldDevices, true) ? ' checked' : '' ?>><span>iPhone</span></label>
                <label class="check"><input type="checkbox" name="device_specializations[]" value="android"<?= in_array('android', $oldDevices, true) ? ' checked' : '' ?>><span>Android</span></label>
                <p class="field__error" aria-live="polite"><?= $e($errors['device_specializations'] ?? '') ?></p>
            </fieldset>
            <button class="btn btn--primary btn--block" type="submit">حفظ والمتابعة</button>
        </form>
        <form method="post" action="<?= $e($baseUrl) ?>/register/stage/2/skip" class="auth__form">
            <?= $csrf() ?>
            <button class="btn btn--block" type="submit">تخطي الآن</button>
        </form>
        <p class="auth__sub">تقدر تضيف بياناتك الفنية لاحقًا من ملفك الشخصي.</p>
    </div>
</section>
