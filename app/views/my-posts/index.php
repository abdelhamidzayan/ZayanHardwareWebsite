<?php
$typeLabels = ['help_request' => 'طلب مساعدة', 'technical_post' => 'معلومة فنية', 'discussion' => 'نقاش عام'];
$statusLabels = ['open' => 'مفتوح', 'in_progress' => 'قيد التشخيص', 'awaiting_confirmation' => 'بانتظار التأكيد', 'resolved' => 'تم الحل', 'closed_unresolved' => 'أُغلق دون حل'];
?>
<div class="page-head">
    <div><h1 class="page-head__title">مشاركاتي</h1><p class="muted">أرشيف كل المنشورات اللي نشرتها على المنصة.</p></div>
    <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/issues/create">منشور جديد</a>
</div>
<div class="container personal-posts">
    <form method="get" action="<?= $e($baseUrl) ?>/my-posts" class="card personal-posts__filters">
        <label class="field"><span class="field__label">نوع المنشور</span>
            <select class="field__input" name="type">
                <option value="">كل الأنواع</option>
                <?php foreach ($typeLabels as $value => $label) : ?><option value="<?= $e($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span class="field__label">الحالة</span>
            <select class="field__input" name="status">
                <option value="">كل الحالات</option>
                <?php foreach ($statusLabels as $value => $label) : ?><option value="<?= $e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span class="field__label">من تاريخ</span>
            <input class="field__input" type="date" name="from" value="<?= $e($selectedFromDate ?? '') ?>">
        </label>
        <label class="field"><span class="field__label">إلى تاريخ</span>
            <input class="field__input" type="date" name="to" value="<?= $e($selectedToDate ?? '') ?>">
        </label>
        <button class="btn btn--primary" type="submit">تطبيق الفلاتر</button>
    </form>
    <?php if (!$items) : ?>
        <div class="card empty-state"><h2>مفيش منشورات مطابقة</h2><p class="empty-state__text">جرّب تغيّر الفلاتر أو أنشئ منشورك الأول.</p></div>
    <?php else : ?>
        <?php foreach ($items as $item) : ?>
            <article class="card personal-post">
                <div class="personal-post__top">
                    <span class="tag"><?= $e($typeLabels[$item['content_type']] ?? 'منشور') ?></span>
                    <span class="personal-post__status"><?= $e($statusLabels[$item['status']] ?? $item['status']) ?></span>
                </div>
                <h2 class="personal-post__title"><a href="<?= $e($baseUrl) ?>/issues/<?= (int) $item['id'] ?>"><?= $e($item['title']) ?></a></h2>
                <?php if ($item['content_type'] === 'help_request' && !empty($item['device_model'])) : ?><p class="muted"><?= $e($item['device_model']) ?><?= !empty($item['category']) ? ' · ' . $e($item['category']) : '' ?></p><?php endif; ?>
                <div class="personal-post__meta"><span><?= (int) $item['reply_count'] ?> رد</span><span><?= (int) $item['diagnostic_count'] ?> تحديث تشخيص</span><span>نُشر: <?= $e($item['created_at']) ?></span></div>
                <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/issues/<?= (int) $item['id'] ?>">عرض المنشور</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
