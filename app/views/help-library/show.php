<div class="page-head"><a class="issue-back" href="<?= $e($baseUrl) ?>/help-library">← مكتبة المساعدات</a></div>
<div class="container help-library help-library__article">
    <article class="card help-public-article">
        <span class="tag">مرجع فني منشور بموافقة الطرفين</span>
        <h1><?= $e($post['title']) ?></h1>
        <div class="help-public-article__people">
            <span class="help-library-person"><?php if (!empty($post['requester_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['requester_user_id']) ?>" alt="" width="36" height="36"><?php endif; ?><?= !empty($post['requester_display_name']) ? 'صاحب الطلب: ' . $e($post['requester_display_name']) : 'صاحب الطلب: عضو مجهول' ?></span>
            <span class="help-library-person"><?php if (!empty($post['helper_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['helper_user_id']) ?>" alt="" width="36" height="36"><?php endif; ?><?= !empty($post['helper_display_name']) ? 'المساعد: ' . $e($post['helper_display_name']) : 'المساعد: عضو مجهول' ?></span>
        </div>
        <section><h2>المشكلة</h2><p dir="auto"><?= nl2br($e($post['problem_summary'])) ?></p></section>
        <section><h2>التشخيص والقياسات</h2><p dir="auto"><?= nl2br($e($post['diagnosis_summary'])) ?></p></section>
        <section><h2>الحل النهائي</h2><p dir="auto"><?= nl2br($e($post['solution_summary'])) ?></p></section>
        <p class="muted">نُشر في <?= $e($post['created_at']) ?> · لا تُعرض الرسائل الخاصة أو المرفقات الخاصة في هذا المرجع.</p>
    </article>
</div>