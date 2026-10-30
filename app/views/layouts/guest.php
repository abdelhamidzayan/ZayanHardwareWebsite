<?php
/**
 * app/views/layouts/guest.php — public pages: landing, login, register.
 *
 * Passed by the controller:  $content (set by View), $title, $authPage
 * Passed by View::shared():  $e, $appName, $baseUrl, $lang, $dir, $flash
 */
$authPage = $authPage ?? false;
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
    <?php require __DIR__ . '/head.php'; ?>
</head>
<body class="guest" data-pwa-base="<?= $e($baseUrl) ?>">

<header class="guest-header">
    <div class="guest-header__inner">
        <a class="brand" href="<?= $e($baseUrl) ?>/">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <nav class="guest-header__actions" aria-label="روابط الموقع">
            <?php if (!$authPage) : ?>
                <a class="btn btn--primary guest-header__link guest-header__login" href="<?= $e($baseUrl) ?>/login">دخول</a>
            <?php endif; ?>
            <a class="guest-header__link" href="<?= $e($baseUrl) ?>/help-library">مكتبة المساعدات</a>
            <a class="guest-header__link guest-header__help" href="<?= $e($baseUrl) ?>/help"<?= (($title ?? '') === 'المساعدة والدعم') ? ' aria-current="page"' : '' ?>>المساعدة</a>
            <a class="guest-header__link guest-header__policy-link" href="<?= $e($baseUrl) ?>/policies"<?= (($title ?? '') === 'سياسات الموقع') ? ' aria-current="page"' : '' ?>>السياسات</a>
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
