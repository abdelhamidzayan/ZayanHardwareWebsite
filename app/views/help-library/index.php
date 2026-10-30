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
                    <span class="help-library-person"><?php if (!empty($post['requester_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['requester_user_id']) ?>" alt="" width="32" height="32"><?php endif; ?>صاحب الطلب: <?= !empty($post['requester_display_name']) ? $e($post['requester_display_name']) : 'عضو مجهول' ?></span>
                    <span class="help-library-person"><?php if (!empty($post['helper_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['helper_user_id']) ?>" alt="" width="32" height="32"><?php endif; ?>المساعد: <?= !empty($post['helper_display_name']) ? $e($post['helper_display_name']) : 'عضو مجهول' ?></span>
                </div>
                <a class="text-action text-action--primary" href="<?= $e($baseUrl) ?>/help-library/<?= (int) $post['id'] ?>">قراءة التشخيص والحل</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>