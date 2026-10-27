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
<body class="guest">

<header class="guest-header">
    <div class="guest-header__inner">
        <a class="brand" href="<?= $e($baseUrl) ?>/">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <nav class="guest-header__actions" aria-label="روابط الموقع">
            <a class="guest-header__help" href="<?= $e($baseUrl) ?>/help"<?= (($title ?? '') === 'المساعدة والدعم') ? ' aria-current="page"' : '' ?>>المساعدة</a>
        </nav>
    </div>
</header>

<?php require __DIR__ . '/flash.php'; ?>

<main id="main" class="guest-main">
    <?= $content ?? '' ?>
</main>

<footer class="guest-footer">
    <p><?= $e($appName) ?> — منصة للفنّيين المتخصصين في هاردوير الهواتف المحمولة.</p>
</footer>

<script src="<?= $e($baseUrl) ?>/js/app.js" defer></script>
</body>
</html>
