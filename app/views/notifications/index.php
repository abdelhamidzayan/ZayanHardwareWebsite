<div class="page-head">
    <div><h1 class="page-head__title">الإشعارات</h1><p class="muted">تابع الردود وتحديثات التشخيص وحالة طلباتك.</p></div>
    <?php if ($unreadCount > 0) : ?>
        <form method="post" action="<?= $e($baseUrl) ?>/notifications/read-all">
            <?= $csrf() ?>
            <button class="btn btn--secondary" type="submit">تعليم الكل كمقروء</button>
        </form>
    <?php endif; ?>
</div>
<div class="container notification-list">
    <section class="card push-settings" data-push-controls>
        <?= $csrf() ?>
        <div><h2>إشعارات الجهاز</h2><p class="muted">فعّل Push عشان توصلك تنبيهات حتى لو الموقع مش مفتوح، إذا كان المتصفح والجهاز بيدعموا ده.</p></div>
        <div class="push-settings__actions">
            <button class="btn btn--primary" type="button" data-push-enable>تفعيل إشعارات Push</button>
            <button class="btn btn--secondary" type="button" data-push-disable hidden>إيقاف إشعارات هذا الجهاز</button>
        </div>
        <p class="muted" data-push-status role="status" aria-live="polite"></p>
    </section>
    <?php if (!$notifications) : ?>
        <div class="card empty-state"><h2>مفيش إشعارات لسه</h2><p class="empty-state__text">لما يحصل تحديث يخص مشاركاتك، هتلاقيه هنا.</p></div>
    <?php else : ?>
        <?php foreach ($notifications as $notification) : ?>
            <article class="card notification-item <?= (int) $notification['is_read'] === 1 ? 'is-read' : 'is-unread' ?>">
                <div class="notification-item__body">
                    <div class="notification-item__heading">
                        <strong><?= $e($notification['title']) ?></strong>
                        <?php if ((int) $notification['is_read'] !== 1) : ?><span class="notification-item__dot" aria-label="غير مقروء"></span><?php endif; ?>
                    </div>
                    <p><?= $e($notification['body']) ?></p>
                    <span class="muted"><?= $e((string) ($notification['actor_name'] ?? 'النظام')) ?> · <?= $e($notification['created_at']) ?></span>
                </div>
                <form method="post" action="<?= $e($baseUrl) ?>/notifications/<?= (int) $notification['id'] ?>/open">
                    <?= $csrf() ?>
                    <button class="btn btn--secondary" type="submit">فتح</button>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="<?= $e($baseUrl) ?>/js/notifications.js" defer></script>
