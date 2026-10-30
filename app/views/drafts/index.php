<?php
?>
<div class="page-head">
    <div>
        <h1 class="page-head__title">مسوداتي</h1>
        <p class="muted">كل منشور بدأت فيه ولسه ما نشرتوش، هتلاقيه هنا.</p>
    </div>
    <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/issues/create">منشور جديد</a>
</div>
<div class="container drafts-page">
    <?php if (empty($drafts)) : ?>
        <section class="card empty-state">
            <h2>مفيش مسودات حاليًا</h2>
            <p class="empty-state__text">ابدأ منشورك، وهيتحفظ تلقائيًا هنا أثناء الكتابة.</p>
        </section>
    <?php else : ?>
        <div class="drafts-list">
            <?php foreach ($drafts as $draft) : ?>
                <article class="card draft-card">
                    <div class="draft-card__copy">
                        <div class="draft-card__meta">
                            <span class="tag"><?= ($draft['content_type'] ?? '') === 'help_request' ? 'طلب مساعدة' : 'معلومة فنية' ?></span>
                            <span class="muted">آخر تعديل <?= $e($draft['updated_at']) ?></span>
                        </div>
                        <h2 class="draft-card__title"><?= $e($draft['title'] !== '' ? $draft['title'] : 'منشور بدون عنوان') ?></h2>
                        <p class="muted"><?= ($draft['details_mode'] ?? 'text') === 'audio' ? 'تسجيل صوتي' : 'تفاصيل مكتوبة' ?> · إصدار <?= (int) $draft['revision'] ?></p>
                    </div>
                    <div class="draft-card__actions">
                        <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/issues/create?draft=<?= $e(rawurlencode($draft['id'])) ?>">استكمال المسودة</a>
                        <button class="btn btn--ghost text-action--danger" type="button"
                            data-delete-draft="<?= $e($draft['id']) ?>"
                            data-csrf="<?= $e($csrfToken ?? '') ?>">حذف</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="<?= $e($baseUrl) ?>/js/drafts.js" defer></script>
