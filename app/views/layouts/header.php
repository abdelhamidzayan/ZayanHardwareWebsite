<?php
/**
 * Shared authenticated header: brand, conversations, notifications and account menu.
 * Category chips intentionally removed to keep the feed focused.
 */
$unreadCount = (int) ($unreadCount ?? 0);
$currentUser = $currentUser ?? null;
$displayName = trim((string) ($currentUser['display_name'] ?? 'حسابي'));
$username = (string) ($currentUser['username'] ?? '');
$profileHref = $username !== '' ? $baseUrl . '/profile/' . rawurlencode($username) : $baseUrl . '/login';
$nameParts = preg_split('/\\s+/u', $displayName) ?: [$displayName];
$avatarInitials = mb_strtoupper(mb_substr($nameParts[0] ?? 'ح', 0, 1) . (isset($nameParts[1]) ? mb_substr($nameParts[1], 0, 1) : ''));
$avatarSrc = !empty($currentUser['avatar_path']) ? $baseUrl . '/avatar/' . (int) $currentUser['id'] : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($currentUser['avatar_key'] ?? 'technician-blue')) . '.svg';
$headerPath = \App\Core\Request::capture()->path;
$helpRequestsActive = $headerPath === '/help-requests' || str_starts_with($headerPath, '/help-requests/');
$notificationsActive = $headerPath === '/notifications' || str_starts_with($headerPath, '/notifications/');
$accountActivePrefixes = ['/profile/', '/workbench', '/drafts', '/my-posts', '/settings', '/admin'];
$accountActive = false;
foreach ($accountActivePrefixes as $prefix) {
    if (str_starts_with($headerPath, $prefix)) {
        $accountActive = true;
        break;
    }
}
?>
<header class="app-header">
    <div class="topbar">
        <a class="brand" href="<?= $e($baseUrl) ?>/feed" aria-label="<?= $e($appName) ?> - الرئيسية">
            <span class="brand__logo" aria-hidden="true">
                <svg class="icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
            </span>
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <div class="header-actions">
        <a class="icon-btn header-page-link help-request-menu__trigger<?= $helpRequestsActive ? ' is-active' : '' ?>" href="<?= $e($baseUrl) ?>/help-requests" aria-label="المحادثات وطلبات المساعدة<?= $unreadHelpCount > 0 ? ' (' . $unreadHelpCount . ' غير مقروءة)' : '' ?>" <?= $helpRequestsActive ? 'aria-current="page"' : '' ?>>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.98 3 3 6.66 3 11.25c0 1.73.58 3.33 1.58 4.63L3.5 21l5.32-2.25c1 .37 2.08.56 3.18.56 5.02 0 9-3.66 9-8.06S17.02 3 12 3Z" fill="currentColor" stroke="none"/><circle cx="8" cy="11.5" r="1.15" fill="#fff"/><circle cx="12" cy="11.5" r="1.15" fill="#fff"/><circle cx="16" cy="11.5" r="1.15" fill="#fff"/></svg>
            <?php if ($unreadHelpCount > 0) : ?><span class="notification-btn__count"><?= $unreadHelpCount > 99 ? '99+' : $unreadHelpCount ?></span><?php endif; ?>
        </a>

        <a class="icon-btn header-page-link notification-menu__trigger<?= $notificationsActive ? ' is-active' : '' ?>" href="<?= $e($baseUrl) ?>/notifications" aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>" <?= $notificationsActive ? 'aria-current="page"' : '' ?>>
            <svg class="icon notification-btn__icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2.75a6.25 6.25 0 0 0-6.25 6.25v3.05c0 1.12-.38 2.2-1.08 3.07L3.4 16.7c-.54.67-.06 1.68.8 1.68h15.6c.86 0 1.34-1.01.8-1.68l-1.27-1.58a4.9 4.9 0 0 1-1.08-3.07V9A6.25 6.25 0 0 0 12 2.75Z"/>
                <path d="M9.5 20a2.65 2.65 0 0 0 5 0Z"/>
            </svg>
            <?php if ($unreadCount > 0) : ?><span class="notification-btn__count" aria-label="<?= $e((string) $unreadCount) ?> إشعارات غير مقروءة"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
        </a>
        <a class="account-menu__mobile-profile<?= $accountActive ? ' is-active' : '' ?>" href="<?= $e($profileHref) ?>" aria-label="الملف الشخصي">
            <img class="avatar account-menu__avatar avatar-image" src="<?= $e($avatarSrc) ?>" alt="" width="38" height="38">
        </a>
        <details class="account-menu">
            <summary class="account-menu__trigger<?= $accountActive ? ' is-active' : '' ?>" aria-label="قائمة الحساب">
                <img class="avatar account-menu__avatar avatar-image" src="<?= $e($avatarSrc) ?>" alt="" width="38" height="38">
                <span class="account-menu__name"><?= $e($displayName) ?></span>
                <svg class="icon account-menu__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </summary>
            <div class="account-menu__panel">
                <div class="account-menu__identity">
                    <img class="avatar avatar-image" src="<?= $e($avatarSrc) ?>" alt="" width="48" height="48">
                    <div class="account-menu__identity-copy">
                        <strong><?= $e($displayName) ?></strong>
                        <?php if ($username !== '') : ?><span dir="ltr">@<?= $e($username) ?></span><?php endif; ?>
                    </div>
                </div>
                <a class="account-menu__item" href="<?= $e($profileHref) ?>">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <span>الملف الشخصي</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/my-posts">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                    <span>مشاركاتي</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/workbench">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8h8M8 12h5M8 16h8"/></svg>
                    <span>طاولتي</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/help-requests">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>
                    <span>طلبات المساعدة</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/settings">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8L6.3 12l1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8H14l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1-.1 1.9Z"/></svg>
                    <span>الإعدادات</span>
                </a>
                <?php if (($currentUser['role'] ?? '') === 'admin') : ?>
                    <a class="account-menu__item" href="<?= $e($baseUrl) ?>/admin">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>لوحة الإدارة</span>
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
    </div>
</header>
