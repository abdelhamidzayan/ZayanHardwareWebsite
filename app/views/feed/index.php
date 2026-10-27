<?php require __DIR__ . '/../partials/featured-home-carousel.php'; ?>

<div class="page-head">
    <h2 class="page-head__title">على الطاولة</h2>
</div>

<div class="container">
    <?php if (!$issues) : ?>
        <div class="empty-state">
            <h2>لا توجد حالات في هذا القسم بعد</h2>
            <p class="empty-state__text">جرّب قسمًا آخر أو عُد إلى كل الحالات.</p>
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/feed">عرض كل الحالات</a>
        </div>
    <?php else : ?>
        <?php foreach ($issues as $issue) : ?>
            <?php require __DIR__ . '/../partials/issue-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
