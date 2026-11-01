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
        <?php
        $headerAvatar = static function (array $person) use ($baseUrl): string {
            return !empty($person['avatar_path'])
                ? $baseUrl . '/avatar/' . (int) ($person['actor_user_id'] ?? $person['user_id'] ?? 0)
                : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($person['avatar_key'] ?? 'technician-blue')) . '.svg';
        };
        ?>
        <a class="icon-btn header-mobile-only" href="<?= $e($baseUrl) ?>/help-requests" aria-label="المحادثات وطلبات المساعدة">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.98 3 3 6.66 3 11.25c0 1.73.58 3.33 1.58 4.63L3.5 21l5.32-2.25c1 .37 2.08.56 3.18.56 5.02 0 9-3.66 9-8.06S17.02 3 12 3Z" fill="currentColor" stroke="none"/><circle cx="8" cy="11.5" r="1.15" fill="#fff"/><circle cx="12" cy="11.5" r="1.15" fill="#fff"/><circle cx="16" cy="11.5" r="1.15" fill="#fff"/></svg>
            <?php if ($unreadHelpCount > 0) : ?><span class="notification-btn__count"><?= $unreadHelpCount > 99 ? '99+' : $unreadHelpCount ?></span><?php endif; ?>
        </a>
        <details class="help-request-menu header-desktop-only">
            <summary class="icon-btn help-request-menu__trigger<?= $helpRequestsActive ? ' is-active' : '' ?>" aria-label="الرسائل والمحادثات<?= $unreadHelpCount > 0 ? ' (' . $unreadHelpCount . ' غير مقروءة)' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.98 3 3 6.66 3 11.25c0 1.73.58 3.33 1.58 4.63L3.5 21l5.32-2.25c1 .37 2.08.56 3.18.56 5.02 0 9-3.66 9-8.06S17.02 3 12 3Z" fill="currentColor" stroke="none"/><circle cx="8" cy="11.5" r="1.15" fill="#fff"/><circle cx="12" cy="11.5" r="1.15" fill="#fff"/><circle cx="16" cy="11.5" r="1.15" fill="#fff"/></svg>
                <?php if ($unreadHelpCount > 0) : ?><span class="notification-btn__count"><?= $unreadHelpCount > 99 ? '99+' : $unreadHelpCount ?></span><?php endif; ?>
            </summary>
            <div class="help-request-menu__panel header-dropdown-panel">
                <div class="header-dropdown__head"><strong>الرسائل والمحادثات</strong><a href="<?= $e($baseUrl) ?>/help-requests">عرض الكل</a></div>
                <?php if (!$headerHelpRequests) : ?>
                    <p class="header-dropdown__empty">لا توجد محادثات حتى الآن.</p>
                <?php else : ?>
                    <?php foreach (array_slice($headerHelpRequests, 0, 5) as $request) :
                        $isRequester = (int) $request['requester_id'] === (int) ($currentUser['id'] ?? 0);
                        $personName = $isRequester ? (string) $request['helper_name'] : (string) $request['requester_name'];
                        $personAvatar = $isRequester
                            ? (!empty($request['helper_avatar_path']) ? $baseUrl . '/avatar/' . (int) $request['helper_id'] : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($request['helper_avatar_key'] ?? 'technician-blue')) . '.svg')
                            : (!empty($request['requester_avatar_path']) ? $baseUrl . '/avatar/' . (int) $request['requester_id'] : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($request['requester_avatar_key'] ?? 'technician-blue')) . '.svg');
                    ?>
                        <a class="header-dropdown__item<?= !empty($request['has_unread']) ? ' is-unread' : '' ?>" href="<?= $e($baseUrl) ?>/help-requests/<?= (int) $request['id'] ?>">
                            <img class="avatar avatar--xs avatar-image" src="<?= $e($personAvatar) ?>" alt="" width="32" height="32">
                            <span class="header-dropdown__copy"><strong><?= $e($personName) ?></strong><span><?= $e($request['subject']) ?></span><small><?= !empty($request['has_unread']) ? 'رسالة أو تحديث جديد' : $e($request['status']) ?></small></span>
                            <?php if (!empty($request['has_unread'])) : ?><i class="header-dropdown__dot" aria-label="غير مقروء"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a class="header-dropdown__footer" href="<?= $e($baseUrl) ?>/help-requests">فتح صندوق الرسائل</a>
            </div>
        </details>

        <a class="icon-btn header-mobile-only<?= $notificationsActive ? ' is-active' : '' ?>" href="<?= $e($baseUrl) ?>/notifications" aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.75a6.25 6.25 0 0 0-6.25 6.25v3.05c0 1.12-.38 2.2-1.08 3.07L3.4 16.7c-.54.67-.06 1.68.8 1.68h15.6c.86 0 1.34-1.01.8-1.68l-1.27-1.58a4.9 4.9 0 0 1-1.08-3.07V9A6.25 6.25 0 0 0 12 2.75Z"/><path d="M9.5 20a2.65 2.65 0 0 0 5 0Z"/></svg>
            <?php if ($unreadCount > 0) : ?><span class="notification-btn__count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
        </a>
        <details class="notification-menu header-desktop-only">
            <summary class="icon-btn notification-menu__trigger<?= $notificationsActive ? ' is-active' : '' ?>" aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.75a6.25 6.25 0 0 0-6.25 6.25v3.05c0 1.12-.38 2.2-1.08 3.07L3.4 16.7c-.54.67-.06 1.68.8 1.68h15.6c.86 0 1.34-1.01.8-1.68l-1.27-1.58a4.9 4.9 0 0 1-1.08-3.07V9A6.25 6.25 0 0 0 12 2.75Z"/><path d="M9.5 20a2.65 2.65 0 0 0 5 0Z"/></svg>
                <?php if ($unreadCount > 0) : ?><span class="notification-btn__count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
            </summary>
            <div class="notification-menu__panel header-dropdown-panel">
                <div class="header-dropdown__head"><strong>الإشعارات</strong><a href="<?= $e($baseUrl) ?>/notifications">عرض الكل</a></div>
                <?php if (!$headerNotifications) : ?>
                    <p class="header-dropdown__empty">لا توجد إشعارات جديدة.</p>
                <?php else : ?>
                    <?php foreach (array_slice($headerNotifications, 0, 5) as $notification) :
                        $notificationPath = (string) ($notification['target_path'] ?? '/notifications');
                        if ($notificationPath === '' || $notificationPath[0] !== '/') $notificationPath = '/notifications';
                    ?>
                        <a class="header-dropdown__item<?= empty($notification['is_read']) ? ' is-unread' : '' ?>" href="<?= $e($baseUrl . $notificationPath) ?>">
                            <img class="avatar avatar--xs avatar-image" src="<?= $e(!empty($notification['actor_avatar_path']) ? $baseUrl . '/avatar/' . (int) ($notification['actor_user_id'] ?? 0) : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($notification['actor_avatar_key'] ?? 'technician-blue')) . '.svg') ?>" alt="" width="32" height="32">
                            <span class="header-dropdown__copy"><strong><?= $e($notification['title']) ?></strong><span><?= $e($notification['body']) ?></span><small><?= $e($notification['created_at']) ?></small></span>
                            <?php if (empty($notification['is_read'])) : ?><i class="header-dropdown__dot" aria-label="غير مقروء"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a class="header-dropdown__footer" href="<?= $e($baseUrl) ?>/notifications">عرض كل الإشعارات</a>
            </div>
        </details>

        <a class="account-menu__mobile-profile<?= $accountActive ? ' is-active' : '' ?>" href="<?= $e($profileHref) ?>" aria-label="الملف الشخصي">
            <img class="avatar account-menu__avatar avatar-image" src="<?= $e($avatarSrc) ?>" alt="" width="38" height="38">
        </a>
        </div>
    </div>
</header>
