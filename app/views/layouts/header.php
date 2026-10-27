<?php
/**
 * app/views/layouts/header.php — brand, bell, search, category chips.
 * Included by app.php (shares its scope: $e, $baseUrl, $appName).
 *
 * Optional variables:
 *   $categories      array  [['name' => 'Power', 'slug' => 'power'], ...]
 *   $activeCategory  string slug of the selected chip
 *   $unreadCount     int    unread notifications
 *   $searchQuery     string current search text
 */

$categories     = $categories ?? [];
$activeCategory = $activeCategory ?? '';
$unreadCount    = (int) ($unreadCount ?? 0);
$searchQuery    = $searchQuery ?? '';
?>
<header class="app-header">

    <div class="topbar">
        <a class="brand" href="<?= $e($baseUrl) ?>/feed">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <a class="icon-btn" href="<?= $e($baseUrl) ?>/notifications"
           aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <?php if ($unreadCount > 0) : ?><span class="icon-btn__dot" aria-hidden="true"></span><?php endif; ?>
        </a>
    </div>

    <form class="searchbar" action="<?= $e($baseUrl) ?>/search" method="get" role="search">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="searchbar__input" type="search" name="q" maxlength="100"
               value="<?= $e($searchQuery) ?>"
               placeholder="ابحث عن عطل أو موديل"
               aria-label="بحث">
        <a class="searchbar__filter" href="<?= $e($baseUrl) ?>/search" aria-label="فلاتر البحث">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11M20 6h1M3 12h3M10 12h11M3 18h9M18 18h3"/><circle cx="17" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="15" cy="18" r="2"/></svg>
        </a>
    </form>

    <?php if ($categories) : ?>
        <nav class="chips" aria-label="الأقسام">
            <?php foreach ($categories as $category) : ?>
                <a class="chip<?= $category['slug'] === $activeCategory ? ' is-active' : '' ?>"
                   href="<?= $e($baseUrl) ?>/feed?category=<?= $e(rawurlencode($category['slug'])) ?>"
                   <?= $category['slug'] === $activeCategory ? 'aria-current="true"' : '' ?>>
                    <?= $e($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

</header>
