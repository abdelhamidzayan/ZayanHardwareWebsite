<?php
$contentType = $issue['content_type'] ?? 'help_request';
$isHelpRequest = $contentType === 'help_request';
$typeLabel = match ($contentType) { 'technical_post' => 'معلومة فنية', 'discussion' => 'نقاش عام', default => 'طلب مساعدة' };
$resolved = $issue['status'] === 'resolved';
$closed = $issue['status'] !== 'open';
$canReply = $currentUser !== null && !$closed;
$specializations = [
    'power' => 'Power', 'charging' => 'Charging', 'network' => 'Network',
    'display' => 'Display', 'schematics' => 'Schematics', 'audio' => 'Audio',
    'camera' => 'Camera', 'short_circuit' => 'Short Circuit',
];
?>
<div class="page-head issue-page-head">
    <a class="issue-back" href="<?= $e($baseUrl) ?>/feed">← الرجوع للمنشورات</a>
    <span class="issue-state <?= $isHelpRequest && $resolved ? 'issue-state--resolved' : ($isHelpRequest && $closed ? 'issue-state--closed' : '') ?>">
        <?= $isHelpRequest ? ($resolved ? 'تم الحل' : ($closed ? 'أُغلق دون حل' : 'نقاش مفتوح')) : $e($typeLabel) ?>
    </span>
</div>

<div class="container issue-page">
    <section class="card issue-original">
        <div class="issue-original__author">
            <span class="avatar"><?= $e(mb_strtoupper(mb_substr($issue['author_name'], 0, 1))) ?></span>
            <div class="issue-original__identity">
                <strong><?= $e($issue['author_name']) ?></strong>
                <span class="muted"><?= $e($specializations[$issue['specialization'] ?? ''] ?? 'فني هاردوير') ?> · <?= $e($issue['created_at']) ?></span>
            </div>
            <?php if (in_array($issue['author_role'], ['admin', 'moderator'], true)) : ?><span class="badge">إشراف المنصة</span><?php endif; ?>
        </div>
        <?php if ($isHelpRequest) : ?>
            <div class="issue-tags">
                <?php if (!empty($issue['device_model'])) : ?><span class="tag tech"><?= $e($issue['device_model']) ?></span><?php endif; ?>
                <?php if (!empty($issue['category'])) : ?><span class="tag"><?= $e($specializations[$issue['category']] ?? $issue['category']) ?></span><?php endif; ?>
            </div>
        <?php else : ?>
            <div class="issue-tags"><span class="tag"><?= $e($typeLabel) ?></span></div>
        <?php endif; ?>
        <h1 class="issue-title" dir="auto"><?= $e($issue['title']) ?></h1>
        <p class="issue-description" dir="auto"><?= nl2br($e($issue['description'])) ?></p>
        <?php if (!empty($issue['measurements'])) : ?>
            <div class="issue-measurements"><strong>القياسات والملاحظات</strong><p dir="auto"><?= nl2br($e($issue['measurements'])) ?></p></div>
        <?php endif; ?>
        <?php if ($attachments) : ?>
            <div class="issue-attachments">
                <?php foreach ($attachments as $file) : ?>
                    <?php
                    $attachmentUrl = $baseUrl . '/attachments/' . (int) $file['id'];
                    $mimeType = (string) $file['mime_type'];
                    ?>
                    <?php if (str_starts_with($mimeType, 'image/')) : ?>
                        <a href="<?= $e($attachmentUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= $e($attachmentUrl) ?>" alt="مرفق فني" loading="lazy">
                        </a>
                    <?php elseif (str_starts_with($mimeType, 'audio/')) : ?>
                        <audio controls preload="none" src="<?= $e($attachmentUrl) ?>">
                            المتصفح لا يدعم تشغيل الصوت.
                        </audio>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($issue['updated_at'] !== $issue['created_at']) : ?>
            <div class="issue-latest-update">
                <span class="issue-latest-update__label">آخر تحديث <span class="badge">معدّل</span></span>
                <span class="muted"><?= $e($issue['updated_at']) ?></span>
                <p>تم تحديث بيانات التشخيص. راجع الوصف والقياسات أعلاه للاطلاع على آخر ما تم تجربته.</p>
            </div>
        <?php endif; ?>

        <?php if ($isOwner && !$closed) : ?>
            <details class="issue-edit">
                <summary>تعديل المنشور وإضافة آخر تجربة</summary>
                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/edit" class="issue-form">
                    <?= $csrf() ?>
                    <label class="field"><span class="field__label">عنوان العطل</span><input class="field__input" name="title" minlength="10" maxlength="180" required value="<?= $e($issue['title']) ?>"></label>
                    <label class="field"><span class="field__label">وصف العطل وآخر تجربة</span><textarea class="field__input" name="description" minlength="20" maxlength="10000" rows="5" required><?= $e($issue['description']) ?></textarea></label>
                    <label class="field"><span class="field__label">القياسات والملاحظات</span><textarea class="field__input" name="measurements" maxlength="5000" rows="3"><?= $e((string) $issue['measurements']) ?></textarea></label>
                    <button class="btn btn--primary" type="submit">حفظ التعديل</button>
                </form>
            </details>
            <?php if ($history) : ?>
                <details class="issue-history"><summary>سجل التعديلات (<?= count($history) ?>)</summary>
                    <?php foreach ($history as $version) : ?>
                        <article class="issue-history__item"><strong><?= $e($version['previous_title']) ?></strong><span class="muted"><?= $e($version['created_at']) ?> · <?= $e($version['editor_name']) ?></span><p><?= nl2br($e($version['previous_description'])) ?></p><?php if ($version['previous_measurements']) : ?><p class="muted"><?= nl2br($e($version['previous_measurements'])) ?></p><?php endif; ?></article>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </section>


    <?php if ($issue['latest_result_id'] !== null) : ?>
        <section class="card issue-result">
            <h2>النتيجة النهائية</h2>
            <span class="issue-result__status"><?= $e(match ($issue['latest_result_review']) { 'pending' => 'بانتظار مراجعة الإدارة', 'approved' => 'تم اعتماد النتيجة', 'rejected' => 'لم يتم اعتماد النتيجة', default => 'نتيجة مسجلة' }) ?></span>
            <p><strong>تم الإصلاح؟</strong> <?= (int) $issue['latest_was_repaired'] === 1 ? 'نعم' : 'لا' ?></p>
            <p><strong>السبب النهائي:</strong><br><?= nl2br($e((string) $issue['latest_final_cause'])) ?></p>
            <?php if ($issue['latest_repair_action']) : ?><p><strong>إجراء الإصلاح:</strong><br><?= nl2br($e((string) $issue['latest_repair_action'])) ?></p><?php endif; ?>
            <?php if ($currentUser && in_array($currentUser['role'] ?? '', ['admin', 'moderator'], true) && $issue['latest_result_review'] === 'pending') : ?>
                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/review-result" class="issue-form">
                    <?= $csrf() ?><input type="hidden" name="result_id" value="<?= (int) $issue['latest_result_id'] ?>">
                    <div class="issue-result__actions">
                        <button class="btn btn--primary" name="decision" value="approve" type="submit">اعتماد النتيجة (+5 سمعة لصاحبها)</button>
                        <button class="btn btn--secondary" name="decision" value="reject" type="submit">رفض النتيجة</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($issue['accepted_reply_id']) : ?>
        <?php foreach ($replies as $reply) : if ((int) $reply['id'] !== (int) $issue['accepted_reply_id']) continue; ?>
            <section class="card accepted-solution">
                <div class="accepted-solution__heading"><span class="accepted-check">✓</span><strong>الحل المعتمد</strong></div>
                <p><?= nl2br($e($reply['body'])) ?></p>
                <span class="muted"><?= $e($reply['display_name']) ?> · <?= $e($reply['created_at']) ?></span>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <section class="discussion" id="discussion">
        <div class="discussion__head">
            <div><h2>مساعدات الفنيين</h2><p class="muted"><?= (int) $issue['reply_count'] ?> مشاركة فنية</p></div>
            <details class="sort-menu">
                <summary aria-label="ترتيب المشاركات" title="ترتيب المشاركات">☷ <span>ترتيب</span></summary>
                <div class="sort-menu__options">
                    <?php foreach (['relevant' => 'الأكثر صلة', 'experts' => 'الخبراء أولًا', 'reputation' => 'أعلى سمعة', 'helpful' => 'الأكثر إفادة', 'newest' => 'الأحدث أولًا'] as $key => $label) : ?>
                        <a class="<?= $sort === $key ? 'is-active' : '' ?>" href="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>?sort=<?= $e($key) ?>#discussion"><?= $e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>

        <?php if (!$replies) : ?>
            <div class="card empty-state"><h3>لسه مفيش مساعدات فنية</h3><p class="empty-state__text">شارك بقياس أو اختبار واضح يساعد صاحب العطل يوصل للسبب.</p></div>
        <?php else : ?>
            <?php foreach ($replies as $reply) : ?>
                <article class="card reply-card <?= (int) $reply['id'] === (int) $issue['accepted_reply_id'] ? 'reply-card--accepted' : '' ?>">
                    <header class="reply-card__head">
                        <span class="avatar"><?= $e(mb_strtoupper(mb_substr($reply['display_name'], 0, 1))) ?></span>
                        <div class="reply-card__identity"><strong><?= $e($reply['display_name']) ?></strong><span class="muted"><?= $e($specializations[$reply['specialization'] ?? ''] ?? 'فني هاردوير') ?> · السمعة <?= (int) $reply['reputation_points'] ?> نقطة · <?= $e($reply['created_at']) ?></span></div>
                        <?php if (in_array($reply['role'], ['admin', 'moderator'], true)) : ?><span class="badge">خبير معتمد</span><?php endif; ?>
                    </header>
                    <div class="reply-card__body" dir="auto"><?= nl2br($e($reply['body'])) ?></div>
                    <footer class="reply-card__actions">
                        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/vote">
                            <?= $csrf() ?><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                            <button class="text-action" type="submit" <?= !$currentUser || (int) $currentUser['id'] === (int) $reply['user_id'] ? 'disabled title="سجّل الدخول لتقييم رد فني آخر"' : '' ?>>♡ مفيد (<?= (int) $reply['helpful_count'] ?>)</button>
                        </form>
                        <?php if ($isOwner && !$closed && (int) $reply['id'] !== (int) $issue['accepted_reply_id']) : ?>
                            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/accept">
                                <?= $csrf() ?><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                                <button class="text-action text-action--primary" type="submit">اعتماد الحل</button>
                            </form>
                        <?php elseif ((int) $reply['id'] === (int) $issue['accepted_reply_id']) : ?><span class="accepted-inline">✓ الحل المعتمد</span><?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canReply) : ?>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reply" class="card issue-form reply-composer">
                <h3>شارك بمساعدة فنية</h3>
                <?= $csrf() ?>
                <label class="field"><span class="field__label">الحل أو خطوات التشخيص</span><textarea class="field__input" name="body" minlength="2" maxlength="5000" rows="4" required placeholder="اكتب القياس، الاختبار، أو سبب العطل المحتمل..."></textarea></label>
                <button class="btn btn--primary" type="submit">نشر الرد</button>
            </form>
        <?php elseif (!$currentUser && !$closed) : ?>
            <div class="card login-prompt"><p>سجّل الدخول للمشاركة في حل العطل.</p><a class="btn btn--primary" href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a></div>
        <?php else : ?>
            <div class="card closed-note"><?= $resolved ? 'تم إغلاق النقاش بعد توثيق الحل.' : 'تم إغلاق النقاش دون حل نهائي.' ?></div>
        <?php endif; ?>
    </section>


    <?php if ($isHelpRequest && $isOwner && $closed && $issue['latest_result_review'] !== null && $issue['latest_result_review'] !== 'pending') : ?>
        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reopen" class="card reopen-issue">
            <?= $csrf() ?>
            <p class="muted">تمت مراجعة النتيجة السابقة. يمكنك فتح النقاش مجددًا لإضافة معلومات تشخيص جديدة.</p>
            <button class="btn btn--secondary" type="submit">إعادة فتح النقاش</button>
        </form>
    <?php endif; ?>

    <?php if ($isHelpRequest && $isOwner && !$closed) : ?>
        <details class="card close-issue">
            <summary>إنهاء النقاش وتقديم النتيجة</summary>
            <p class="muted">لو وصلت للسبب النهائي بنفسك أو لم يتم إصلاح العطل، وثّق النتيجة ليستفيد الفنيون الآخرون. أي نقاط محتملة تخضع للمراجعة ولا تُضاف تلقائيًا.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/close" class="issue-form">
                <?= $csrf() ?>
                <label class="field"><span class="field__label">هل تم إصلاح العطل؟</span><select class="field__input" name="was_repaired" required><option value="1">نعم، تم الإصلاح</option><option value="0">لا، لم يتم الإصلاح</option></select></label>
                <label class="field"><span class="field__label">السبب النهائي للعطل</span><textarea class="field__input" name="final_cause" minlength="10" maxlength="5000" rows="3" required></textarea></label>
                <label class="field"><span class="field__label">إجراء الإصلاح</span><textarea class="field__input" name="repair_action" minlength="5" maxlength="5000" rows="3" placeholder="مطلوب لو تم إصلاح العطل"></textarea></label>
                <label class="field"><span class="field__label">أي رد ساعدك؟ (اختياري)</span><select class="field__input" name="helpful_reply_id"><option value="">لم يتم اختيار رد</option><?php foreach ($replies as $reply) : ?><option value="<?= (int) $reply['id'] ?>"><?= $e(mb_substr($reply['display_name'] . ': ' . $reply['body'], 0, 90)) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn--primary" type="submit">إرسال النتيجة وإغلاق النقاش</button>
            </form>
        </details>
    <?php endif; ?>
</div>
