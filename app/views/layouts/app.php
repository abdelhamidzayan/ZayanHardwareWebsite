<?php
/**
 * app/views/layouts/app.php — app shell for logged-in pages (header, nav, content).
 *
 * Passed by the controller:  $content (set by View), $title, $active, $showHeader, $aside
 * Passed by View::shared():  $e, $appName, $baseUrl, $lang, $dir, $flash
 */
$active     = $active ?? 'home';
$showHeader = $showHeader ?? true;
$currentUser = $currentUser ?? \App\Services\AuthService::user();
if (!isset($unreadCount) && is_array($currentUser) && isset($currentUser['id'])) {
    $unreadCount = \App\Models\Notification::unreadCount((int) $currentUser['id']);
}
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
    <?php require __DIR__ . '/head.php'; ?>
</head>
<body data-pwa-user-id="<?= $e((string) ($currentUser['id'] ?? '')) ?>" data-pwa-base="<?= $e($baseUrl) ?>">
<div class="app">

    <?php if ($showHeader) { require __DIR__ . '/header.php'; } ?>

    <?php require __DIR__ . '/flash.php'; ?>

    <div class="app__body">
        <?php require __DIR__ . '/navigation.php'; ?>

        <main id="main" class="app__main">
            <?= $content ?? '' ?>
        </main>

        <?php if (!empty($aside)) : ?>
            <aside class="app__aside"><?= $aside ?></aside>
        <?php endif; ?>
    </div>

</div>
<script src="<?= $e($baseUrl) ?>/js/content-editor.js" defer></script>
<script src="<?= $e($baseUrl) ?>/js/app.js" defer></script>
<script src="<?= $e($baseUrl) ?>/js/pwa-guide.js" defer></script>
</body>
</html>
