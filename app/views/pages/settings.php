<?php
$currentUser = $currentUser ?? null;
$username = (string) ($currentUser['username'] ?? '');
$profileHref = $username !== '' ? $baseUrl . '/profile/' . rawurlencode($username) : $baseUrl . '/feed';
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
    </section>
</div>
