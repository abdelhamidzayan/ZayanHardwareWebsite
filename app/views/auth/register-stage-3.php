<?php
$errors = $errors ?? [];
$old = $old ?? [];
$oldSkills = is_array($old['skill_domains'] ?? null) ? $old['skill_domains'] : [];
$val = fn (string $key): string => $e($old[$key] ?? '');
?>
<section class="auth">
    <div class="card auth__card auth__card--wide">
        <p class="auth__sub">المرحلة 3 من 3 · اختيارية</p>
        <h1 class="auth__title">ملفك الفني</h1>
        <p class="auth__sub">اختار مجالات خبرتك واسم الورشة لو حابب. تقدر تتخطى المرحلة وتضيف التفاصيل بعدين.</p>
        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register/stage/3">
            <?= $csrf() ?>
            <fieldset class="field">
                <legend class="field__label">مجالات إصلاح البوردة</legend>
                <?php foreach ($skillDomains as $category) : ?>
                    <label class="check">
                        <input type="checkbox" name="skill_domains[]" value="<?= $e($category['slug']) ?>"<?= in_array($category['slug'], $oldSkills, true) ? ' checked' : '' ?>>
                        <span><?= $e($category['name']) ?></span>
                    </label>
                <?php endforeach; ?>
                <p class="field__error" aria-live="polite"><?= $e($errors['skill_domains'] ?? '') ?></p>
            </fieldset>
            <div class="field">
                <label class="field__label" for="workshop">اسم الورشة <span class="field__opt">اختياري</span></label>
                <input class="field__input" id="workshop" name="workshop" type="text" maxlength="80" value="<?= $val('workshop') ?>">
                <p class="field__error" aria-live="polite"><?= $e($errors['workshop'] ?? '') ?></p>
            </div>
            <button class="btn btn--primary btn--block" type="submit">حفظ وإنهاء التسجيل</button>
        </form>
        <form method="post" action="<?= $e($baseUrl) ?>/register/stage/3/skip" class="auth__form">
            <?= $csrf() ?>
            <button class="btn btn--block" type="submit">تخطي وإنهاء التسجيل</button>
        </form>
    </div>
</section>
