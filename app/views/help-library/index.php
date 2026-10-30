<div class="page-head"><div><h1 class="page-head__title">مكتبة المساعدات الفنية</h1><p class="muted">ملخصات أعطال وتشخيصات وحلول وافق أصحابها على مشاركتها.</p></div></div>
<div class="container help-library">
    <?php if (!$posts) : ?>
        <div class="card empty-state"><h2>المكتبة لسه بتبدأ</h2><p class="empty-state__text">المساعدات التي يوافق أصحابها على نشرها ستظهر هنا.</p></div>
    <?php else : ?>
        <?php foreach ($posts as $post) : ?>
            <article class="card help-library-card">
                <div class="help-library-card__top"><span class="tag">مساعدة منشورة بموافقة الطرفين</span><span class="muted"><?= $e($post['created_at']) ?></span></div>
                <h2><a href="<?= $e($baseUrl) ?>/help-library/<?= (int) $post['id'] ?>"><?= $e($post['title']) ?></a></h2>
                <p><?= $e(mb_strimwidth((string) $post['problem_summary'], 0, 220, '…', 'UTF-8')) ?></p>
                <div class="help-library-card__people">
                    <?php if (!empty($post['requester_display_name'])) : ?><span>صاحب الطلب: <?= $e($post['requester_display_name']) ?></span><?php else : ?><span>صاحب الطلب: عضو مجهول</span><?php endif; ?>
                    <?php if (!empty($post['helper_display_name'])) : ?><span>المساعد: <?= $e($post['helper_display_name']) ?></span><?php else : ?><span>المساعد: عضو مجهول</span><?php endif; ?>
                </div>
                <a class="text-action text-action--primary" href="<?= $e($baseUrl) ?>/help-library/<?= (int) $post['id'] ?>">قراءة التشخيص والحل</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>