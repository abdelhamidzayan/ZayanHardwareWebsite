<?php
/**
 * app/views/layouts/guest.php — public pages: login, registration, help and policies.
 */
$authPage = $authPage ?? false;
$guestPath = \App\Core\Request::capture()->path;
$guestLoginActive = $guestPath === '/login';
$guestHelpActive = $guestPath === '/help';
$guestPoliciesActive = $guestPath === '/policies';
$guestHelpMenuActive = $guestHelpActive || $guestPoliciesActive;
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
    <?php require __DIR__ . '/head.php'; ?>
</head>
<body class="guest" data-pwa-base="<?= $e($baseUrl) ?>">

<header class="guest-header">
    <div class="guest-header__inner">
        <a class="brand" href="<?= $e($baseUrl) ?>/login" aria-label="<?= $e($appName) ?> - تسجيل الدخول">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <nav class="guest-header__actions" aria-label="روابط الموقع">
            <a
                class="guest-header__link guest-header__login<?= $guestLoginActive ? ' is-active' : '' ?>"
                href="<?= $e($baseUrl) ?>/login"
                aria-label="تسجيل الدخول"
                <?= $guestLoginActive ? 'aria-current="page"' : '' ?>
            >
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                <span class="guest-header__label">تسجيل الدخول</span>
            </a>

            <details class="guest-nav-menu<?= $guestHelpMenuActive ? ' is-active' : '' ?>">
                <summary
                    class="guest-header__link guest-nav-menu__trigger"
                    aria-label="المساعدة والسياسات"
                    <?= $guestHelpMenuActive ? 'aria-current="page"' : '' ?>
                >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.2-1.8 2.7M12 17h.01"/></svg>
                    <span class="guest-header__label">المساعدة</span>
                    <svg class="icon guest-nav-menu__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </summary>

                <div class="guest-nav-menu__panel" aria-label="روابط المساعدة والسياسات">
                    <a
                        class="guest-nav-menu__item<?= $guestHelpActive ? ' is-active' : '' ?>"
                        href="<?= $e($baseUrl) ?>/help"
                        <?= $guestHelpActive ? 'aria-current="page"' : '' ?>
                    >
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.2-1.8 2.7M12 17h.01"/></svg>
                        <span>مركز المساعدة</span>
                    </a>
                    <a
                        class="guest-nav-menu__item<?= $guestPoliciesActive ? ' is-active' : '' ?>"
                        href="<?= $e($baseUrl) ?>/policies"
                        <?= $guestPoliciesActive ? 'aria-current="page"' : '' ?>
                    >
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.5 3.1 7.8 7.5 9.5 4.4-1.7 7.5-5 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>السياسات والخصوصية</span>
                    </a>
                </div>
            </details>
        </nav>
    </div>
</header>

<?php require __DIR__ . '/flash.php'; ?>

<main id="main" class="guest-main">
    <?= $content ?? '' ?>
</main>

<footer class="guest-footer">
    <p><?= $e($appName) ?> — منصة للفنّيين المتخصصين في هاردوير الهواتف المحمولة.</p>
    <nav class="guest-footer__links" aria-label="السياسات والمعلومات">
        <a href="<?= $e($baseUrl) ?>/policies">سياسات الموقع والخصوصية</a>
    </nav>
</footer>

<script src="<?= $e($baseUrl) ?>/js/app.js" defer></script>
<?php foreach (($scripts ?? []) as $script) : ?>
    <script src="<?= $e($baseUrl) ?>/js/<?= $e($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
