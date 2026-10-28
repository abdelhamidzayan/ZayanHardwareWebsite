<?php
$statusLabels = [
    'open' => 'مفتوح',
    'in_progress' => 'قيد التشخيص',
    'awaiting_confirmation' => 'بانتظار تأكيد الإصلاح',
    'resolved' => 'تم الحل',
    'closed_unresolved' => 'أُغلق دون حل',
];
?>
<div class="page-head">
    <div><h1 class="page-head__title">طاولتي</h1><p class="muted">طلبات المساعدة اللي نشرتها، وردود الفنيين، وتحديثات التشخيص.</p></div>
    <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/issues/create">طلب مساعدة جديد</a>
</div>
<div class="container personal-posts">
    <?php if (!$items) : ?>
        <div class="card empty-state"><h2>طاولتك لسه فاضية</h2><p class="empty-state__text">لما تنشر طلب مساعدة، هتتابع حالته وردود الفنيين من هنا.</p><a class="btn btn--primary" href="<?= $e($baseUrl) ?>/issues/create">إنشاء طلب مساعدة</a></div>
    <?php else : ?>
        <?php foreach ($items as $item) : ?>
            <article class="card personal-post">
                <div class="personal-post__top">
                    <span class="tag tech"><?= $e((string) ($item['device_model'] ?? '')) ?></span>
                    <span class="personal-post__status"><?= $e($statusLabels[$item['status']] ?? $item['status']) ?></span>
                </div>
                <h2 class="personal-post__title"><a href="<?= $e($baseUrl) ?>/issues/<?= (int) $item['id'] ?>"><?= $e($item['title']) ?></a></h2>
                <div class="personal-post__meta">
                    <span><?= (int) $item['reply_count'] ?> رد</span>
                    <span><?= (int) $item['diagnostic_count'] ?> تحديث تشخيص</span>
                    <span>آخر تحديث: <?= $e($item['updated_at']) ?></span>
                </div>
                <?php if ($item['status'] === 'awaiting_confirmation') : ?><p class="personal-post__notice">فيه رد معتمد مستني تأكيدك لنجاح الإصلاح.</p><?php endif; ?>
                <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/issues/<?= (int) $item['id'] ?>">متابعة الطلب</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
