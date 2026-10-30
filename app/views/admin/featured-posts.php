<div class="page-head">
    <div>
        <h1 class="page-head__title">إدارة منشورات الرئيسية</h1>
        <p class="muted">اختار الحالات الفنية اللي تظهر في كروت متحركة أعلى الصفحة الرئيسية.</p>
    </div>
    <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/admin/update">تحديث الموقع</a>
</div>

<div class="container admin-featured">
    <section class="admin-featured__summary">
        <div>
            <span class="admin-featured__label">المنشورات المختارة</span>
            <strong class="admin-featured__count"><?= (int) $featuredCount ?></strong>
        </div>
        <p>الترتيب عشوائي ويتغير تلقائيًا. مفيش أزرار يمين أو شمال للتحكم في الكروت.</p>
    </section>

    <?php if (!$issues) : ?>
        <div class="card empty-state">
            <h2>لسه مفيش منشورات</h2>
            <p class="empty-state__text">لما تبدأ الحالات الفنية تظهر في قاعدة البيانات، هتقدر تختار منها هنا.</p>
        </div>
    <?php else : ?>
        <?php foreach ($issues as $issue) : ?>
            <article class="card admin-featured__item">
                <div class="admin-featured__copy">
                    <div class="admin-featured__meta">
                        <span class="tag tech"><?= $e($issue['device_model']) ?></span>
                        <span class="muted">#<?= (int) $issue['id'] ?> · <?= $e($issue['author_name']) ?></span>
                    </div>
                    <h2 class="admin-featured__title"><?= $e($issue['title']) ?></h2>
                    <p class="admin-featured__description"><?= $e(mb_strimwidth(trim(strip_tags($issue['description'])), 0, 220, '…', 'UTF-8')) ?></p>
                    <div class="admin-featured__meta muted">
                        <span><?= $e($issue['category']) ?></span>
                        <span><?= (int) $issue['reply_count'] ?> رد</span>
                        <span><?= $e($issue['created_at']) ?></span>
                    </div>
                </div>
                <div class="admin-featured__actions">
                    <?php if (!empty($issue['is_featured'])) : ?>
                        <span class="admin-featured__status">مختار للرئيسية</span>
                        <form method="post" action="<?= $e($baseUrl) ?>/admin/featured-posts/<?= (int) $issue['id'] ?>">
                            <?= $csrf() ?>
                            <input type="hidden" name="action" value="unfeature">
                            <button class="btn btn--secondary" type="submit">إزالة من الرئيسية</button>
                        </form>
                    <?php else : ?>
                        <form method="post" action="<?= $e($baseUrl) ?>/admin/featured-posts/<?= (int) $issue['id'] ?>">
                            <?= $csrf() ?>
                            <input type="hidden" name="action" value="feature">
                            <button class="btn btn--primary" type="submit">اختيار للرئيسية</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn--ghost" href="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>">عرض المنشور</a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
