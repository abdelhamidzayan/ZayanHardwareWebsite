<?php
/**
 * Shared authenticated header: brand, search, notifications and account menu.
 * Category chips intentionally removed to keep the feed focused.
 */
$unreadCount = (int) ($unreadCount ?? 0);
$searchQuery = $searchQuery ?? '';
$currentUser = $currentUser ?? null;
$displayName = trim((string) ($currentUser['display_name'] ?? 'حسابي'));
$username = (string) ($currentUser['username'] ?? '');
$profileHref = $username !== '' ? $baseUrl . '/profile/' . rawurlencode($username) : $baseUrl . '/login';
$nameParts = preg_split('/\\s+/u', $displayName) ?: [$displayName];
$avatarInitials = mb_strtoupper(mb_substr($nameParts[0] ?? 'ح', 0, 1) . (isset($nameParts[1]) ? mb_substr($nameParts[1], 0, 1) : ''));
?>
<header class="app-header">
    <div class="topbar">
        <a class="brand" href="<?= $e($baseUrl) ?>/feed" aria-label="<?= $e($appName) ?> - الرئيسية">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <form class="searchbar topbar__search" action="<?= $e($baseUrl) ?>/search" method="get" role="search">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="searchbar__input" type="search" name="q" maxlength="100"
                   value="<?= $e($searchQuery) ?>"
                   placeholder="ابحث عن عطل أو موديل"
                   aria-label="بحث">
        </form>

        <a class="icon-btn" href="<?= $e($baseUrl) ?>/notifications"
           aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <?php if ($unreadCount > 0) : ?><span class="icon-btn__dot" aria-hidden="true"></span><?php endif; ?>
        </a>

        <details class="account-menu">
            <summary class="account-menu__trigger" aria-label="قائمة الحساب">
                <span class="avatar account-menu__avatar" aria-hidden="true"><?= $e($avatarInitials) ?></span>
                <span class="account-menu__name"><?= $e($displayName) ?></span>
                <svg class="icon account-menu__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </summary>
            <div class="account-menu__panel">
                <div class="account-menu__identity">
                    <span class="avatar" aria-hidden="true"><?= $e($avatarInitials) ?></span>
                    <div class="account-menu__identity-copy">
                        <strong><?= $e($displayName) ?></strong>
                        <?php if ($username !== '') : ?><span dir="ltr">@<?= $e($username) ?></span><?php endif; ?>
                    </div>
                </div>
                <a class="account-menu__item" href="<?= $e($profileHref) ?>">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    <span>طاولتي ومشاركاتي</span>
                </a>
                <a class="account-menu__item account-menu__item--policy" href="<?= $e($baseUrl) ?>/policies">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 12h7M8 16h7"/></svg>
                    <span>سياسات الموقع والخصوصية</span>
                </a>
                <?php if (($currentUser['role'] ?? '') === 'admin') : ?>
                    <a class="account-menu__item" href="<?= $e($baseUrl) ?>/admin">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>الإدارة</span>
                    </a>
                <?php endif; ?>
                <form class="account-menu__logout" method="post" action="<?= $e($baseUrl) ?>/logout">
                    <?= $csrf() ?>
                    <button class="account-menu__item account-menu__item--danger" type="submit">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                        <span>تسجيل الخروج</span>
                    </button>
                </form>
            </div>
        </details>
    </div>
</header>
