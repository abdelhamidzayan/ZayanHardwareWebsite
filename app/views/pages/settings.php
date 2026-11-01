<?php
$currentUser = $currentUser ?? null;
$username = (string) ($currentUser['username'] ?? '');
$profileHref = $username !== '' ? $baseUrl . '/profile/' . rawurlencode($username) : $baseUrl . '/feed';
$avatarSrc = !empty($currentUser['avatar_path']) ? $baseUrl . '/avatar/' . (int) $currentUser['id'] : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($currentUser['avatar_key'] ?? 'technician-blue')) . '.svg';
?>
<div class="help-page settings-page">
    <section class="help-hero" aria-labelledby="settings-title">
        <span class="help-hero__eyebrow">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8L6.3 12l1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8H14l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1-.1 1.9Z"/></svg>
            حسابك
        </span>
        <h1 class="help-hero__title" id="settings-title">الإعدادات</h1>
        <p class="help-hero__text">اختصارات لإدارة ملفك والوصول إلى المساعدة والخصوصية وخيارات الموقع.</p>
    </section>

    <section class="help-section" aria-labelledby="settings-account-title">
        <div class="help-section__head"><div><h2 class="help-section__title" id="settings-account-title">الحساب والملف الشخصي</h2><p class="help-section__intro">راجع ملفك ومعلوماتك الظاهرة للأعضاء.</p></div></div>
        <div class="settings-list">
            <a class="settings-row" href="<?= $e($profileHref) ?>">
                <span class="settings-row__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
                <span class="settings-row__copy"><strong>ملفي الشخصي</strong><span>عرض ملفك ومشاركاتك</span></span>
                <svg class="icon settings-row__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>
    </section>

    <section class="help-section" aria-labelledby="settings-avatar-title">
        <div class="help-section__head"><div><h2 class="help-section__title" id="settings-avatar-title">الصورة الشخصية</h2><p class="help-section__intro">ارفع صورتك واقصّها، أو اختر Avatar جاهزًا يناسب هويتك الفنية.</p></div></div>
        <form class="card avatar-settings" method="post" action="<?= $e($baseUrl) ?>/profile/avatar" enctype="multipart/form-data" data-avatar-form>
            <?= $csrf() ?>
            <div class="avatar-settings__current">
                <img class="avatar avatar--lg avatar-image" src="<?= $e($avatarSrc) ?>" alt="صورتك الشخصية" width="80" height="80" data-avatar-current>
                <div><strong><?= $e($currentUser['display_name'] ?? 'حسابي') ?></strong><p class="muted">ستظهر الصورة في ملفك والمنشورات والتعليقات والإشعارات.</p></div>
            </div>
            <div class="avatar-settings__choices" role="group" aria-label="اختيار Avatar">
                <?php foreach (['technician-blue','technician-cyan','technician-violet','technician-green','technician-orange','technician-red'] as $avatarKey) : ?>
                    <button class="avatar-choice<?= ($currentUser['avatar_key'] ?? 'technician-blue') === $avatarKey && empty($currentUser['avatar_path']) ? ' is-selected' : '' ?>" type="button" data-avatar-choice="<?= $e($avatarKey) ?>" aria-pressed="<?= ($currentUser['avatar_key'] ?? 'technician-blue') === $avatarKey && empty($currentUser['avatar_path']) ? 'true' : 'false' ?>">
                        <img src="<?= $e($baseUrl . '/assets/avatars/' . $avatarKey . '.svg') ?>" alt="Avatar">
                    </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="avatar_key" value="<?= $e((string) ($currentUser['avatar_key'] ?? 'technician-blue')) ?>" data-avatar-key>
            <div class="avatar-settings__upload">
                <label class="btn btn--secondary" for="avatar-file">رفع صورة شخصية</label>
                <input id="avatar-file" class="sr-only" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" data-avatar-file>
                <span class="field__hint">JPG أو PNG أو WEBP · حتى 5 ميجابايت. هنفتح أداة قص قبل الحفظ.</span>
            </div>
            <div class="avatar-crop" data-avatar-crop hidden>
                <div class="avatar-crop__head"><strong>قص الصورة الشخصية</strong><button class="text-action" type="button" data-avatar-crop-cancel>إلغاء</button></div>
                <div class="avatar-crop__viewport" data-avatar-viewport><img alt="معاينة قص الصورة" data-avatar-crop-image></div>
                <label class="field"><span class="field__label">تكبير الصورة</span><input type="range" min="1" max="3" step="0.01" value="1" data-avatar-zoom></label>
                <p class="field__hint">اسحب الصورة داخل الإطار لاختيار الجزء الظاهر.</p>
                <button class="btn btn--secondary btn--block" type="button" data-avatar-crop-apply>اعتماد القص</button>
            </div>
            <p class="field__error" data-avatar-error role="status" aria-live="polite"></p>
            <button class="btn btn--primary btn--block" type="submit">حفظ الصورة الشخصية</button>
        </form>
    </section>

    <section class="help-section" aria-labelledby="settings-help-requests-title">
        <div class="help-section__head"><div><h2 class="help-section__title" id="settings-help-requests-title">طلبات المساعدة الشخصية</h2><p class="help-section__intro">أنت المتحكم في استقبال طلبات المساعدة من الأعضاء.</p></div></div>
        <form class="card help-availability" method="post" action="<?= $e($baseUrl) ?>/settings/help-availability">
            <?= $csrf() ?>
            <label class="check">
                <input type="checkbox" name="accept_help_requests" value="1" <?= (int) ($currentUser['accept_help_requests'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span><strong>استقبال طلبات المساعدة الشخصية</strong><small class="muted">عند إيقاف الاختيار، لن يظهر زر طلب المساعدة في ملفك العام.</small></span>
            </label>
            <button class="btn btn--secondary" type="submit">حفظ التفضيل</button>
        </form>
    </section>

    <section class="help-section" aria-labelledby="settings-help-title">
        <div class="help-section__head"><div><h2 class="help-section__title" id="settings-help-title">المساعدة والخصوصية</h2><p class="help-section__intro">كل المعلومات المهمة في مكان واحد.</p></div></div>
        <div class="settings-list">
            <a class="settings-row" href="<?= $e($baseUrl) ?>/help">
                <span class="settings-row__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.2-1.8 2.7M12 17h.01"/></svg></span>
                <span class="settings-row__copy"><strong>مركز المساعدة</strong><span>إرشادات الاستخدام وحل المشكلات</span></span>
                <svg class="icon settings-row__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a class="settings-row" href="<?= $e($baseUrl) ?>/help#report-error">
                <span class="settings-row__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
                <span class="settings-row__copy"><strong>بلاغ عن خطأ</strong><span>الوصول إلى إرشادات الإبلاغ عن مشكلة</span></span>
                <svg class="icon settings-row__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a class="settings-row" href="<?= $e($baseUrl) ?>/policies">
                <span class="settings-row__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 12h7M8 16h7"/></svg></span>
                <span class="settings-row__copy"><strong>الخصوصية والقوانين</strong><span>سياسات الموقع وقواعد المجتمع</span></span>
                <svg class="icon settings-row__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <button class="settings-row" type="button" data-pwa-open-guide>
                <span class="settings-row__icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M12 18h.01M9 6h6"/></svg></span>
                <span class="settings-row__copy"><strong>تثبيت الموقع</strong><span>طريقة إضافة Zayan Hardware إلى جهازك</span></span>
                <svg class="icon settings-row__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>

    <section class="help-section settings-logout" aria-labelledby="settings-logout-title">
        <div class="help-section__head">
            <div><h2 class="help-section__title" id="settings-logout-title">جلسة الحساب</h2><p class="help-section__intro">أنه جلسة تسجيل الدخول على هذا الجهاز بأمان. ستحتاج إلى تسجيل الدخول مرة أخرى لاستخدام حسابك.</p></div>
        </div>
        <form class="card settings-logout__card" method="post" action="<?= $e($baseUrl) ?>/logout">
            <?= $csrf() ?>
            <div><strong>تسجيل الخروج</strong><p class="muted">لن يتم حذف ملفك الشخصي أو منشوراتك أو أي بيانات محفوظة.</p></div>
            <button class="btn btn--secondary settings-logout__button" type="submit">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                تسجيل الخروج من الحساب
            </button>
        </form>
    </section>
    </section>
</div>
