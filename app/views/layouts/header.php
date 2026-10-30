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
$avatarSrc = !empty($currentUser['avatar_path']) ? $baseUrl . '/avatar/' . (int) $currentUser['id'] : $baseUrl . '/assets/avatars/' . rawurlencode((string) ($currentUser['avatar_key'] ?? 'technician-blue')) . '.svg';
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
        <details class="help-request-menu">
            <summary class="icon-btn help-request-menu__trigger" aria-label="المحادثات وطلبات المساعدة<?= $unreadHelpCount > 0 ? ' (' . $unreadHelpCount . ' غير مقروءة)' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/><path d="M8 12h8M12 8v8"/></svg>
                <?php if ($unreadHelpCount > 0) : ?><span class="notification-btn__count"><?= $unreadHelpCount > 99 ? '99+' : $unreadHelpCount ?></span><?php endif; ?>
            </summary>
            <div class="help-request-menu__panel">
                <div class="notification-menu__head"><strong>طلبات المساعدة</strong><span><?= $unreadHelpCount ?> محادثة غير مقروءة</span></div>
                <?php if (empty($headerHelpRequests)) : ?>
                    <div class="notification-menu__empty"><strong>لا توجد طلبات بعد</strong><p>طلبات المساعدة الشخصية ستظهر هنا.</p></div>
                <?php else : ?>
                    <div class="notification-menu__list">
                        <?php foreach ($headerHelpRequests as $headerRequest) : ?>
                            <?php
                            $isRequestOwner = (int) $headerRequest['requester_id'] === (int) ($currentUser['id'] ?? 0);
                            $otherName = $isRequestOwner ? $headerRequest['helper_name'] : $headerRequest['requester_name'];
                            $otherAvatarPath = $isRequestOwner ? $headerRequest['helper_avatar_path'] : $headerRequest['requester_avatar_path'];
                            $otherAvatarKey = $isRequestOwner ? $headerRequest['helper_avatar_key'] : $headerRequest['requester_avatar_key'];
                            $otherUserId = $isRequestOwner ? $headerRequest['helper_id'] : $headerRequest['requester_id'];
                            $requestStatus = match ($headerRequest['status']) { 'pending' => 'في انتظار القبول', 'active' => 'محادثة نشطة', 'closed' => 'مغلق', 'declined' => 'تم الاعتذار', default => 'طلب مساعدة' };
                            ?>
                            <a class="help-request-menu__item" href="<?= $e($baseUrl) ?>/help-requests/<?= (int) $headerRequest['id'] ?>">
                                <img class="avatar avatar-image" src="<?= $e($baseUrl . (!empty($otherAvatarPath) ? '/avatar/' . (int) $otherUserId : '/assets/avatars/' . rawurlencode((string) ($otherAvatarKey ?? 'technician-blue')) . '.svg')) ?>" alt="" width="36" height="36">
                                <span class="help-request-menu__item-copy"><strong><?= $e($otherName) ?></strong><span><?= $e($headerRequest['subject']) ?></span><small><?= $e($requestStatus) ?> · <?= $e($headerRequest['last_message_at']) ?></small><?php if (!empty($headerRequest['has_unread'])) : ?><em class="help-request-menu__new">جديد</em><?php endif; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="notification-menu__all" href="<?= $e($baseUrl) ?>/help-requests">عرض كل طلبات المساعدة</a>
            </div>
        </details>

        <details class="notification-menu">
            <summary class="icon-btn notification-menu__trigger" aria-label="الإشعارات<?= $unreadCount > 0 ? ' (' . $unreadCount . ' غير مقروء)' : '' ?>">
                <svg class="icon notification-btn__icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2.75a6.25 6.25 0 0 0-6.25 6.25v3.05c0 1.12-.38 2.2-1.08 3.07L3.4 16.7c-.54.67-.06 1.68.8 1.68h15.6c.86 0 1.34-1.01.8-1.68l-1.27-1.58a4.9 4.9 0 0 1-1.08-3.07V9A6.25 6.25 0 0 0 12 2.75Z"/>
                    <path d="M9.5 20a2.65 2.65 0 0 0 5 0Z"/>
                </svg>
                <?php if ($unreadCount > 0) : ?>
                    <span class="notification-btn__count" aria-label="<?= $e((string) $unreadCount) ?> إشعارات غير مقروءة"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                <?php endif; ?>
            </summary>
            <div class="notification-menu__panel" aria-label="أحدث الإشعارات">
                <div class="notification-menu__head">
                    <strong>الإشعارات</strong>
                    <?php if ($unreadCount > 0) : ?><span><?= $unreadCount > 99 ? '99+' : $unreadCount ?> غير مقروء</span><?php endif; ?>
                </div>
                <?php if (empty($headerNotifications)) : ?>
                    <div class="notification-menu__empty">
                        <span class="notification-menu__empty-icon" aria-hidden="true">✓</span>
                        <strong>أنت على اطلاع</strong>
                        <p>لا توجد إشعارات جديدة حاليًا.</p>
                    </div>
                <?php else : ?>
                    <div class="notification-menu__list">
                        <?php foreach ($headerNotifications as $headerNotification) : ?>
                            <form class="notification-menu__form" method="post" action="<?= $e($baseUrl) ?>/notifications/<?= (int) $headerNotification['id'] ?>/open">
                                <?= $csrf() ?>
                                <button class="notification-menu__item <?= (int) $headerNotification['is_read'] === 1 ? 'is-read' : 'is-unread' ?>" type="submit">
                                    <?php if (!empty($headerNotification['actor_user_id'])) : ?><img class="avatar avatar--xs avatar-image notification-menu__avatar" src="<?= $e($baseUrl . (!empty($headerNotification['actor_avatar_path']) ? '/avatar/' . (int) $headerNotification['actor_user_id'] : '/assets/avatars/' . rawurlencode((string) ($headerNotification['actor_avatar_key'] ?? 'technician-blue')) . '.svg')) ?>" alt="" width="32" height="32"><?php else : ?><span class="notification-menu__item-mark" aria-hidden="true"></span><?php endif; ?>
                                    <span class="notification-menu__item-copy">
                                        <strong><?= $e($headerNotification['title']) ?></strong>
                                        <span><?= $e($headerNotification['body']) ?></span>
                                        <small><?= $e((string) ($headerNotification['actor_name'] ?? 'النظام')) ?> · <?= $e($headerNotification['created_at']) ?></small>
                                    </span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="notification-menu__all" href="<?= $e($baseUrl) ?>/notifications">عرض كل الإشعارات</a>
            </div>
        </details>
        <details class="account-menu">
            <summary class="account-menu__trigger" aria-label="قائمة الحساب">
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
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/workbench">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8h8M8 12h5M8 16h8"/></svg>
                    <span>طاولتي</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/my-posts">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                    <span>مشاركاتي</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/help-requests">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>
                    <span>طلبات المساعدة الشخصية</span>
                </a>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/help-library">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22V5.5Z"/><path d="M4 6h16M8 10h8M8 14h6"/></svg>
                    <span>مكتبة المساعدات</span>
                </a>
                <details class="account-menu__group">
                    <summary class="account-menu__item account-menu__group-trigger">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.2-1.8 2.7M12 17h.01"/></svg>
                        <span>مساعدة</span>
                        <svg class="icon account-menu__group-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </summary>
                    <div class="account-menu__submenu">
                        <a class="account-menu__item" href="<?= $e($baseUrl) ?>/help">
                            <span>مركز المساعدة</span>
                        </a>
                        <a class="account-menu__item" href="<?= $e($baseUrl) ?>/help#report-error">
                            <span>بلاغ عن خطأ</span>
                        </a>
                        <a class="account-menu__item" href="<?= $e($baseUrl) ?>/policies">
                            <span>الخصوصية والقوانين</span>
                        </a>
                        <button class="account-menu__item" type="button" data-pwa-open-guide>
                            <span>تثبيت الموقع</span>
                        </button>
                    </div>
                </details>
                <a class="account-menu__item" href="<?= $e($baseUrl) ?>/settings">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8L6.3 12l1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8H14l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1-.1 1.9Z"/></svg>
                    <span>الإعدادات</span>
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
    </div>
</header>
