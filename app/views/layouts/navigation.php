<?php
/**
 * app/views/layouts/navigation.php — main navigation.
 * Included by app.php (shares its scope: $e, $baseUrl, $active).
 * Mobile: bottom bar. Tablet/desktop: restyled by responsive.css (same markup).
 *
 * Optional variable:
 *   $currentUser  array|null  logged-in user, needs ['username']
 */

$currentUser = $currentUser ?? null;

$profileHref = $currentUser
    ? $baseUrl . '/profile/' . rawurlencode($currentUser['username'])
    : $baseUrl . '/login';

$items = [
    [
        'key'   => 'home',
        'label' => 'الرئيسية',
        'href'  => $baseUrl . '/feed',
        'icon'  => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
    ],
    [
        'key'   => 'search',
        'label' => 'بحث',
        'href'  => $baseUrl . '/search',
        'icon'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    ],
    [
        'key'   => 'add',
        'label' => 'إضافة',
        'href'  => $baseUrl . '/issues/create',
        'icon'  => '<path d="M12 5v14M5 12h14"/>',
        'class' => ' bottom-nav__item--add',
    ],
    [
        'key'   => 'market',
        'label' => 'السوق',
        'href'  => $baseUrl . '/market',
        'icon'  => '<path d="m4 9 1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9a2.67 2.67 0 0 0 5.33 0 2.67 2.67 0 0 0 5.34 0A2.67 2.67 0 0 0 20 9"/><path d="M10 20v-5h4v5"/>',
    ],
    [
        'key'   => 'profile',
        'label' => 'حسابي',
        'href'  => $profileHref,
        'icon'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    ],
];
?>
<nav class="bottom-nav" aria-label="التنقل الرئيسي">
    <?php foreach ($items as $item) : ?>
        <?php $isActive = $item['key'] === $active; ?>
        <a class="bottom-nav__item<?= $item['class'] ?? '' ?><?= $isActive ? ' is-active' : '' ?>"
           href="<?= $e($item['href']) ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <span class="bottom-nav__icon">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><?= $item['icon'] ?></svg>
            </span>
            <span class="bottom-nav__label"><?= $e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
