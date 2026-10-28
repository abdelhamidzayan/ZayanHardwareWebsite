<div class="page-head">
    <h2 class="page-head__title">آخر المنشورات</h2>
</div>

<div class="container feed-list">
    <?php if (!$issues) : ?>
        <div class="empty-state">
            <h2>لسه مفيش منشورات منشورة</h2>
            <p class="empty-state__text">لما الفنيين ينشروا طلبات مساعدة أو معلومات فنية أو نقاشات، هتظهر هنا.</p>
        </div>
    <?php else : ?>
        <?php foreach ($issues as $issue) : ?>
            <?php require __DIR__ . '/../partials/issue-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
