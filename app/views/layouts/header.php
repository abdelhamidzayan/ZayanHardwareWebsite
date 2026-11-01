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
            <span class="brand__name"><?= $e($appName) ?></span>
        </a>

        <div class="header-actions">
        <a class="icon-btn header-mobile-only" href="<?= $e($baseUrl) ?>/help-requests" aria-label="المحادثات وطلبات المساعدة">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.25c-5.02 0-9 3.56-9 8s3.98 8 9 8c1.1 0 2.18-.19 3.18-.56L20.5 21l-1.08-5.12A7.4 7.4 0 0 0 21 11.25c0-4.44-3.98-8-9-8Z"/><circle cx="8" cy="11.25" r=".8" fill="currentColor" stroke="none"/><circle cx="12" cy="11.25" r=".8" fill="currentColor" stroke="none"/><circle cx="16" cy="11.25" r=".8" fill="currentColor" stroke="none"/></svg>
            <?php if ($unreadHelpCount > 0) : ?><span class="notification-btn__count"><?= $unreadHelpCount > 99 ? '99+' : $unreadHelpCount ?></span><?php endif; ?>
        </a>
        <details class="help-request-menu header-desktop-only">
            <summary class="icon-btn help-request-menu__trigger<?= $helpRequestsActive ? ' is-active' : '' ?>" aria-label="الرسائل والمحادثات<?= $unreadHelpCount > 0 ? ' (' . $unreadHelpCount . ' غير مقروءة)' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.25c-5.02 0-9 3.56-9 8s3.98 8 9 8c1.1 0 2.18-.19 3.18-.56L20.5 21l-1.08-5.12A7.4 7.4 0 0 0 21 11.25c0-4.44-3.98-8-9-8Z"/><circle cx="8" cy="11.25" r=".8" fill="currentColor" stroke="none"/><circle cx="12" cy="11.25" r=".8" fill="currentColor" stroke="none"/><circle cx="16" cy="11.25" r=".8" fill="currentColor" stroke="none"/></svg>
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
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>
            <?php if ($unreadCount > 0) : ?><span class="notification-btn__count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
        </a>
        <details class="notification-menu header-desktop-only">
            <summary class="icon-btn notification-menu__trigger<?= $notificationsActive ? ' is-active' : '' ?>" aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>
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
