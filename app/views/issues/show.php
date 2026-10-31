<?php
$contentType = $issue['content_type'] ?? 'help_request';
$isHelpRequest = $contentType === 'help_request';
$typeLabel = match ($contentType) { 'technical_post', 'discussion' => 'معلومة فنية', default => 'طلب مساعدة' };
$steps = json_decode((string) ($issue['steps_json'] ?? ''), true);
$steps = is_array($steps) ? array_values(array_filter($steps, static fn ($step): bool => is_string($step) && trim($step) !== '')) : [];
$deviceLabel = trim((string) ($issue['manufacturer_name'] ?? '') . ' ' . (string) ($issue['model_code'] ?? $issue['model_commercial_name'] ?? $issue['device_model'] ?? ''));
$resolved = $issue['status'] === 'resolved';
$awaitingConfirmation = $issue['status'] === 'awaiting_confirmation';
$closed = in_array($issue['status'], ['resolved', 'closed_unresolved'], true);
$issueAvatarSrc = !empty($issue['author_avatar_path']) ? '/avatar/' . (int) $issue['user_id'] : '/assets/avatars/' . rawurlencode((string) ($issue['author_avatar_key'] ?? 'technician-blue')) . '.svg';
$canReply = $currentUser !== null && in_array($issue['status'], ['open', 'in_progress'], true);
$specializations = [
    'power' => 'Power', 'charging' => 'Charging', 'network' => 'Network',
    'display' => 'Display', 'schematics' => 'Schematics', 'audio' => 'Audio',
    'camera' => 'Camera', 'short_circuit' => 'Short Circuit',
];
$topLevelReplies = [];
$childrenByParent = [];
foreach ($replies as $reply) {
    if (empty($reply['parent_reply_id'])) {
        $topLevelReplies[] = $reply;
    } else {
        $childrenByParent[(int) $reply['parent_reply_id']][] = $reply;
    }
}
?>
<div class="page-head issue-page-head">
    <a class="issue-back" href="<?= $e($baseUrl) ?>/feed">← الرجوع للمنشورات</a>
    <span class="issue-state <?= $isHelpRequest && $resolved ? 'issue-state--resolved' : ($isHelpRequest && $closed ? 'issue-state--closed' : '') ?>">
        <?= $isHelpRequest ? ($resolved ? 'تم الحل' : ($closed ? 'أُغلق دون حل' : ($awaitingConfirmation ? 'بانتظار تأكيد الإصلاح' : ($issue['status'] === 'in_progress' ? 'قيد التشخيص' : 'نقاش مفتوح')))) : $e($typeLabel) ?>
    </span>
</div>

<div class="container issue-page">
    <section class="card issue-original">
        <div class="issue-original__author">
            <a href="<?= $e($baseUrl) ?>/profile/<?= $e(rawurlencode((string) $issue['username'])) ?>"><img class="avatar avatar-image" src="<?= $e($baseUrl . $issueAvatarSrc) ?>" alt="" width="48" height="48"></a>
            <div class="issue-original__identity">
                <strong><?= $e($issue['author_name']) ?></strong>
                <span class="muted"><?= $e($specializations[$issue['specialization'] ?? ''] ?? 'فني هاردوير') ?> · <?= $e($issue['created_at']) ?></span>
            </div>
            <?php if (in_array($issue['author_role'], ['admin', 'moderator'], true)) : ?><span class="badge">إشراف المنصة</span><?php endif; ?>
        </div>
        <div class="issue-tags">
            <span class="tag"><?= $e($typeLabel) ?></span>
            <?php if ($deviceLabel !== '') : ?><span class="tag tech"><?= $e($deviceLabel) ?></span><?php endif; ?>
            <?php if ($isHelpRequest && !empty($issue['category'])) : ?><span class="tag"><?= $e($specializations[$issue['category']] ?? $issue['category']) ?></span><?php endif; ?>
        </div>
        <h1 class="issue-title" dir="auto"><?= $e($issue['title']) ?></h1>
        <?php if (($issue['details_mode'] ?? 'text') === 'audio' && !empty($issue['audio_path'])) : ?>
            <div class="issue-audio">
                <audio controls preload="none" src="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/audio"
                    data-audio-trim data-trim-start="<?= (int) ($issue['audio_trim_start_ms'] ?? 0) ?>"
                    data-trim-end="<?= (int) ($issue['audio_trim_end_ms'] ?? $issue['audio_duration_ms'] ?? 0) ?>">
                    المتصفح لا يدعم تشغيل الصوت.
                </audio>
                <span class="muted">تسجيل صوتي · <?= $e(gmdate('i:s', (int) round(max(0, (int) ($issue['audio_duration_ms'] ?? 0)) / 1000))) ?></span>
            </div>
        <?php elseif (!empty($issue['description'])) : ?>
            <div class="issue-description" dir="auto"><?= \App\Services\ContentEditorService::renderInlineContent((string) $issue['description'], $attachments, $baseUrl) ?></div>
        <?php endif; ?>
        <?php if ($steps) : ?>
            <section class="issue-tested-steps">
                <h2>الخطوات والفحوصات المجربة</h2>
                <ol>
                    <?php foreach ($steps as $step) : ?>
                        <li><span><?= $e($step) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
        <?php if (!empty($issue['measurements'])) : ?>
            <div class="issue-measurements"><strong>القياسات والملاحظات</strong><p dir="auto"><?= nl2br($e($issue['measurements'])) ?></p></div>
        <?php endif; ?>
        <?php if ($attachments) : ?>
            <section class="issue-attachments">
                <h2>المرفقات</h2>
                <div class="issue-attachments__grid" data-image-gallery="issue-<?= (int) $issue['id'] ?>">
                    <?php foreach ($attachments as $file) : ?>
                        <?php if ((int) ($file['is_inline'] ?? 0) === 1) continue; ?>
                        <?php
                        $attachmentUrl = $baseUrl . '/attachments/' . (int) $file['id'];
                        $mimeType = (string) $file['mime_type'];
                        ?>
                        <?php if (str_starts_with($mimeType, 'image/')) : ?>
                            <a href="<?= $e($attachmentUrl) ?>" class="attachment-image-link" data-image-viewer data-image-gallery-item data-image-src="<?= $e($attachmentUrl) ?>" aria-label="فتح الصورة">
                                <img src="<?= $e($attachmentUrl) ?>" alt="مرفق فني" loading="lazy">
                            </a>
                        <?php elseif (str_starts_with($mimeType, 'video/')) : ?>
                            <video class="issue-attachment-video" controls preload="metadata" playsinline src="<?= $e($attachmentUrl) ?>">المتصفح لا يدعم تشغيل الفيديو.</video>
                        <?php elseif (str_starts_with($mimeType, 'audio/')) : ?>
                            <audio controls preload="none" src="<?= $e($attachmentUrl) ?>"></audio>
                        <?php elseif ($mimeType === 'application/pdf') : ?>
                            <a class="issue-attachment-file" href="<?= $e($attachmentUrl) ?>" target="_blank" rel="noopener">عرض ملف PDF</a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if ($issue['updated_at'] !== $issue['created_at']) : ?>
            <div class="issue-latest-update">
                <span class="issue-latest-update__label">آخر تحديث <span class="badge">معدّل</span></span>
                <span class="muted"><?= $e($issue['updated_at']) ?></span>
                <p>تم تحديث بيانات التشخيص. راجع الوصف والقياسات أعلاه للاطلاع على آخر ما تم تجربته.</p>
            </div>
        <?php endif; ?>

        <?php if ($isOwner && in_array($issue['status'], ['open', 'in_progress'], true)) : ?>
            <details class="issue-edit">
                <summary>تعديل المنشور وإضافة آخر تجربة</summary>
                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/edit" class="issue-form" enctype="multipart/form-data">
                    <?= $csrf() ?>
                    <label class="field"><span class="field__label">عنوان العطل</span><input class="field__input" name="title" minlength="10" maxlength="180" required value="<?= $e($issue['title']) ?>"></label>
                    <?php
                    $editorFieldName = 'description';
                    $editorLabel = 'وصف العطل وآخر تجربة';
                    $editorPlaceholder = 'اكتب وصف العطل وأحدث تجربة...';
                    $editorValue = (string) $issue['description'];
                    $editorMaxLength = 10000;
                    $editorRows = 5;
                    $editorRequired = true;
                    require __DIR__ . '/../partials/content-editor.php';
                    ?>
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

    <?php if ($isHelpRequest) : ?>
        <section class="card diagnostic-updates" id="diagnostic-updates">
            <div class="discussion__head">
                <div><h2>تحديثات التشخيص</h2><p class="muted">سجل زمني منفصل للتجارب والقياسات الجديدة</p></div>
            </div>
            <?php if (empty($diagnosticUpdates)) : ?>
                <p class="muted">لسه مفيش تحديثات تشخيص منفصلة.</p>
            <?php else : ?>
                <ol class="diagnostic-updates__list">
                    <?php foreach ($diagnosticUpdates as $update) : ?>
                        <li class="diagnostic-updates__item">
                            <div><strong><?= $e($update['display_name']) ?></strong><span class="muted"><?= $e($update['created_at']) ?><?= !empty($update['updated_at']) ? ' · تم التعديل' : '' ?></span></div>
                            <div class="diagnostic-updates__body" dir="auto"><?= \App\Services\ContentEditorService::renderInlineContent((string) $update['body'], $attachments, $baseUrl) ?></div>
                            <?php if ($isOwner && in_array($issue['status'], ['open', 'in_progress', 'awaiting_confirmation'], true)) : ?>
                                <div class="diagnostic-updates__actions">
                                    <details><summary class="text-action">تعديل التحديث</summary>
                                        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/diagnostic-updates/<?= (int) $update['id'] ?>/edit" class="issue-form" enctype="multipart/form-data">
                                            <?= $csrf() ?><?php
                                            $editorFieldName = 'body'; $editorLabel = 'تعديل تحديث التشخيص'; $editorPlaceholder = 'حدّث القياسات أو نتيجة التجربة...';
                                            $editorValue = (string) $update['body']; $editorMaxLength = 5000; $editorRows = 3; $editorRequired = true;
                                            require __DIR__ . '/../partials/content-editor.php';
                                            ?>
                                            <button class="btn btn--secondary" type="submit">حفظ التحديث</button>
                                        </form>
                                    </details>
                                    <details><summary class="text-action">حذف التحديث</summary>
                                        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/diagnostic-updates/<?= (int) $update['id'] ?>/delete">
                                            <?= $csrf() ?><button class="text-action text-action--danger" type="submit">تأكيد الحذف</button>
                                        </form>
                                    </details>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ($isOwner && in_array($issue['status'], ['open', 'in_progress', 'awaiting_confirmation'], true)) : ?>
                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/diagnostic-updates" class="issue-form" enctype="multipart/form-data">
                    <?= $csrf() ?>
                    <?php
                    $editorFieldName = 'body';
                    $editorLabel = 'إضافة تجربة أو قياس جديد';
                    $editorPlaceholder = 'اكتب القياس الجديد أو التغيير الذي ظهر بعد آخر تجربة...';
                    $editorValue = '';
                    $editorMaxLength = 5000;
                    $editorRows = 3;
                    $editorRequired = true;
                    require __DIR__ . '/../partials/content-editor.php';
                    ?>
                    <button class="btn btn--secondary" type="submit">إضافة تحديث التشخيص</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

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
                        <button class="btn btn--primary" name="decision" value="approve" type="submit"><?= (int) $issue['latest_was_repaired'] === 1 ? 'اعتماد النتيجة (+5 سمعة)' : 'اعتماد النتيجة' ?></button>
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
                <div class="accepted-solution__body"><?= \App\Services\ContentEditorService::renderInlineContent((string) $reply['body'], $attachments, $baseUrl) ?></div>
                <span class="muted"><?= $e($reply['display_name']) ?> · <?= $e($reply['created_at']) ?></span>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($isHelpRequest && $isOwner && $awaitingConfirmation) : ?>
        <section class="card repair-confirmation">
            <h2>هل الإصلاح نجح فعلًا؟</h2>
            <p class="muted">تم اعتماد رد فني. لن يظهر الطلب كـ «تم الحل» إلا بعد تأكيدك نجاح الإصلاح وتوثيق السبب والإجراء.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/confirm-repair" class="issue-form">
                <?= $csrf() ?>
                <label class="field"><span class="field__label">السبب النهائي للعطل</span><textarea class="field__input" name="final_cause" minlength="10" maxlength="5000" rows="3" required></textarea></label>
                <label class="field"><span class="field__label">إجراء الإصلاح الذي نجح</span><textarea class="field__input" name="repair_action" minlength="5" maxlength="5000" rows="3" required></textarea></label>
                <button class="btn btn--primary" type="submit">تأكيد نجاح الإصلاح</button>
            </form>
            <details class="repair-confirmation__reject">
                <summary>الإصلاح لم ينجح</summary>
                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reject-repair" class="issue-form">
                    <?= $csrf() ?>
                    <label class="field"><span class="field__label">إيه اللي لسه حاصل؟ (اختياري)</span><textarea class="field__input" name="reason" maxlength="2000" rows="2"></textarea></label>
                    <button class="btn btn--secondary" type="submit">الرجوع للتشخيص</button>
                </form>
            </details>
        </section>
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
            <?php foreach ($topLevelReplies as $reply) : ?>
                <article id="reply-<?= (int) $reply['id'] ?>" class="card reply-card <?= (int) $reply['id'] === (int) $issue['accepted_reply_id'] ? 'reply-card--accepted' : '' ?>">
                    <?php if (!empty($reply['deleted_at'])) : ?>
                        <header class="reply-card__head">
                            <div class="reply-card__identity">
                                <strong>تعليق محذوف</strong>
                                <span class="muted">تم الاحتفاظ بالردود المرتبطة به للحفاظ على سياق النقاش.</span>
                            </div>
                        </header>
                        <div class="reply-card__body"><p class="muted">نص التعليق الأصلي لم يعد معروضًا.</p></div>
                    <?php else : ?>
                    <header class="reply-card__head">
                        <a href="<?= $e($baseUrl) ?>/profile/<?= $e(rawurlencode((string) $reply['username'])) ?>"><img class="avatar avatar-image" src="<?= $e($baseUrl . (!empty($reply['avatar_path']) ? '/avatar/' . (int) $reply['user_id'] : '/assets/avatars/' . rawurlencode((string) ($reply['avatar_key'] ?? 'technician-blue')) . '.svg')) ?>" alt="" width="40" height="40" loading="lazy"></a>
                        <div class="reply-card__identity"><strong><a href="<?= $e($baseUrl) ?>/profile/<?= $e(rawurlencode((string) $reply['username'])) ?>"><?= $e($reply['display_name']) ?></a></strong><span class="muted"><?= $e($specializations[$reply['specialization'] ?? ''] ?? 'فني هاردوير') ?> · السمعة <?= (int) $reply['reputation_points'] ?> نقطة · <?= $e($reply['created_at']) ?></span></div>
                        <?php if (in_array($reply['role'], ['admin', 'moderator'], true)) : ?><span class="badge">خبير معتمد</span><?php endif; ?>
                    </header>
                    <div class="reply-card__body" dir="auto"><?= \App\Services\ContentEditorService::renderInlineContent((string) $reply['body'], $attachments, $baseUrl) ?></div>
                    <footer class="reply-card__actions">
                        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/vote">
                            <?= $csrf() ?><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                            <button class="text-action" type="submit" <?= !$currentUser || (int) $currentUser['id'] === (int) $reply['user_id'] ? 'disabled title="سجّل الدخول لتقييم رد فني آخر"' : '' ?>>♡ مفيد (<?= (int) $reply['helpful_count'] ?>)</button>
                        </form>
                        <?php if ($isHelpRequest && $isOwner && in_array($issue['status'], ['open', 'in_progress'], true) && (int) $reply['id'] !== (int) $issue['accepted_reply_id']) : ?>
                            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/accept">
                                <?= $csrf() ?><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>">
                                <button class="text-action text-action--primary" type="submit">اعتماد الحل</button>
                            </form>
                        <?php elseif ((int) $reply['id'] === (int) $issue['accepted_reply_id']) : ?><span class="accepted-inline">✓ الحل المعتمد</span><?php endif; ?>
                        <?php if (in_array($issue['status'], ['open', 'in_progress'], true) && (int) $currentUser['id'] === (int) $reply['user_id']) : ?>
                            <details class="reply-edit"><summary class="text-action">تعديل</summary>
                                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/replies/<?= (int) $reply['id'] ?>/edit" class="issue-form" enctype="multipart/form-data">
                                    <?= $csrf() ?><?php
                                    $editorFieldName = 'body'; $editorLabel = 'تعديل التعليق'; $editorPlaceholder = 'اكتب تعليقك...';
                                    $editorValue = (string) $reply['body']; $editorMaxLength = 5000; $editorRows = 3; $editorRequired = true;
                                    require __DIR__ . '/../partials/content-editor.php';
                                    ?>
                                    <button class="btn btn--secondary" type="submit">حفظ التعديل</button>
                                </form>
                            </details>
                            <details class="reply-delete"><summary class="text-action">حذف</summary>
                                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/replies/<?= (int) $reply['id'] ?>/delete">
                                    <?= $csrf() ?><button class="text-action text-action--danger" type="submit">تأكيد حذف التعليق</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <?php if ($canReply) : ?>
                            <details class="reply-to-comment"><summary class="text-action">رد على التعليق</summary>
                                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reply" class="issue-form" enctype="multipart/form-data">
                                    <?= $csrf() ?><input type="hidden" name="parent_reply_id" value="<?= (int) $reply['id'] ?>">
                                    <?php
                                    $editorFieldName = 'body'; $editorLabel = 'رد على التعليق'; $editorPlaceholder = 'اكتب ردك على تعليق الفني...';
                                    $editorValue = ''; $editorMaxLength = 5000; $editorRows = 2; $editorRequired = true; $editorCompact = true;
                                    require __DIR__ . '/../partials/content-editor.php';
                                    ?>
                                    <button class="btn btn--secondary" type="submit">نشر الرد</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </footer>

                    <?php endif; ?>
                    <?php if (!empty($childrenByParent[(int) $reply['id']])) : ?>
                        <div class="reply-card__children" aria-label="الردود على التعليق">
                            <?php foreach ($childrenByParent[(int) $reply['id']] as $child) : ?>
                                <article id="reply-<?= (int) $child['id'] ?>" class="reply-card reply-card--nested <?= (int) $child['id'] === (int) $issue['accepted_reply_id'] ? 'reply-card--accepted' : '' ?>">
                                    <header class="reply-card__head">
                                        <a href="<?= $e($baseUrl) ?>/profile/<?= $e(rawurlencode((string) $child['username'])) ?>"><img class="avatar avatar-image" src="<?= $e($baseUrl . (!empty($child['avatar_path']) ? '/avatar/' . (int) $child['user_id'] : '/assets/avatars/' . rawurlencode((string) ($child['avatar_key'] ?? 'technician-blue')) . '.svg')) ?>" alt="" width="36" height="36" loading="lazy"></a>
                                        <div class="reply-card__identity"><strong><a href="<?= $e($baseUrl) ?>/profile/<?= $e(rawurlencode((string) $child['username'])) ?>"><?= $e($child['display_name']) ?></a></strong><span class="muted"><?= $e($child['created_at']) ?><?php if (!empty($child['parent_deleted_at'])) : ?> · رد على تعليق محذوف<?php elseif (!empty($child['parent_author_name'])) : ?> · رد على <?= $e($child['parent_author_name']) ?><?php endif; ?></span></div>
                                    </header>
                                    <div class="reply-card__body" dir="auto"><?= \App\Services\ContentEditorService::renderInlineContent((string) $child['body'], $attachments, $baseUrl) ?></div>
                                    <footer class="reply-card__actions">
                                        <?php if ($isHelpRequest && $isOwner && in_array($issue['status'], ['open', 'in_progress'], true) && (int) $child['id'] !== (int) $issue['accepted_reply_id']) : ?>
                                            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/accept">
                                                <?= $csrf() ?><input type="hidden" name="reply_id" value="<?= (int) $child['id'] ?>">
                                                <button class="text-action text-action--primary" type="submit">اعتماد الحل</button>
                                            </form>
                                        <?php elseif ((int) $child['id'] === (int) $issue['accepted_reply_id']) : ?><span class="accepted-inline">✓ الحل المعتمد</span><?php endif; ?>
                                        <?php if (in_array($issue['status'], ['open', 'in_progress'], true) && (int) $currentUser['id'] === (int) $child['user_id']) : ?>
                                            <details class="reply-edit"><summary class="text-action">تعديل</summary>
                                                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/replies/<?= (int) $child['id'] ?>/edit" class="issue-form" enctype="multipart/form-data">
                                                    <?= $csrf() ?><?php
                                                    $editorFieldName = 'body'; $editorLabel = 'تعديل التعليق'; $editorPlaceholder = 'اكتب تعليقك...';
                                                    $editorValue = (string) $child['body']; $editorMaxLength = 5000; $editorRows = 3; $editorRequired = true;
                                                    require __DIR__ . '/../partials/content-editor.php';
                                                    ?>
                                                    <button class="btn btn--secondary" type="submit">حفظ التعديل</button>
                                                </form>
                                            </details>
                                            <details class="reply-delete"><summary class="text-action">حذف</summary>
                                                <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/replies/<?= (int) $child['id'] ?>/delete">
                                                    <?= $csrf() ?><button class="text-action text-action--danger" type="submit">تأكيد حذف التعليق</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canReply) : ?>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reply" class="card issue-form reply-composer" enctype="multipart/form-data">
                <h3>شارك بمساعدة فنية</h3>
                <?= $csrf() ?>
                <?php
                $editorFieldName = 'body'; $editorLabel = 'الحل أو خطوات التشخيص'; $editorPlaceholder = 'اكتب القياس، الاختبار، أو سبب العطل المحتمل...';
                $editorValue = ''; $editorMaxLength = 5000; $editorRows = 2; $editorRequired = true; $editorCompact = true;
                require __DIR__ . '/../partials/content-editor.php';
                ?>
                <button class="btn btn--primary" type="submit">نشر الرد</button>
            </form>
        <?php elseif (!$currentUser && !$closed) : ?>
            <div class="card login-prompt"><p>سجّل الدخول للمشاركة في حل العطل.</p><a class="btn btn--primary" href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a></div>
        <?php elseif ($awaitingConfirmation) : ?>
            <div class="card closed-note">الطلب بانتظار تأكيد صاحب الطلب لنجاح الإصلاح.</div>
        <?php else : ?>
            <div class="card closed-note"><?= $resolved ? 'تم إغلاق النقاش بعد تأكيد الإصلاح.' : 'تم إغلاق النقاش دون حل نهائي.' ?></div>
        <?php endif; ?>
    </section>


    <?php if ($isOwner) : ?>
        <details class="card content-delete">
            <summary>حذف المنشور</summary>
            <p class="muted">سيختفي المنشور من الـ Feed ومن أرشيفك، لكن لن يتم حذف سجل الردود والنتائج نهائيًا حتى لا تتكسر المراجعات الفنية.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/delete">
                <?= $csrf() ?>
                <button class="btn btn--danger" type="submit">تأكيد إخفاء المنشور</button>
            </form>
        </details>
    <?php endif; ?>

    <?php if ($isHelpRequest && $isOwner && $closed && $issue['latest_result_review'] !== null && $issue['latest_result_review'] !== 'pending') : ?>
        <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/reopen" class="card reopen-issue">
            <?= $csrf() ?>
            <p class="muted">تمت مراجعة النتيجة السابقة. يمكنك فتح النقاش مجددًا لإضافة معلومات تشخيص جديدة.</p>
            <button class="btn btn--secondary" type="submit">إعادة فتح النقاش</button>
        </form>
    <?php endif; ?>

    <?php if ($isHelpRequest && $isOwner && in_array($issue['status'], ['open', 'in_progress'], true)) : ?>
        <details class="card close-issue">
            <summary>إنهاء النقاش وتقديم النتيجة</summary>
            <p class="muted">لو لم يتم إصلاح العطل، وثّق سبب التعثر ليستفيد الفنيون الآخرون. تأكيد نجاح الإصلاح متاح بعد اعتماد رد فني.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>/close" class="issue-form">
                <?= $csrf() ?>
                <input type="hidden" name="was_repaired" value="0">
                <label class="field"><span class="field__label">سبب عدم الوصول للإصلاح</span><textarea class="field__input" name="final_cause" minlength="10" maxlength="5000" rows="3" required></textarea></label>
                <label class="field"><span class="field__label">أي رد ساعدك؟ (اختياري)</span><select class="field__input" name="helpful_reply_id"><option value="">لم يتم اختيار رد</option><?php foreach ($replies as $reply) : ?><option value="<?= (int) $reply['id'] ?>"><?= $e(mb_substr($reply['display_name'] . ': ' . $reply['body'], 0, 90)) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn--primary" type="submit">تسجيل أن العطل لم يُحل</button>
            </form>
        </details>
    <?php endif; ?>
    <dialog class="image-viewer" data-image-viewer-dialog aria-label="عارض الصور">
        <div class="image-viewer__toolbar">
            <span data-image-viewer-counter>1 / 1</span>
            <div>
                <button type="button" class="btn btn--secondary" data-image-zoom-out aria-label="تصغير">−</button>
                <button type="button" class="btn btn--secondary" data-image-zoom-in aria-label="تكبير">+</button>
                <button type="button" class="btn btn--secondary" data-image-fullscreen>ملء الشاشة</button>
                <button type="button" class="btn btn--primary" data-image-viewer-close>إغلاق</button>
            </div>
        </div>
        <div class="image-viewer__viewport" data-image-viewer-viewport>
            <button type="button" class="image-viewer__nav image-viewer__nav--prev" data-image-prev aria-label="الصورة السابقة">‹</button>
            <img data-image-viewer-image alt="صورة فنية مكبرة" draggable="false">
            <button type="button" class="image-viewer__nav image-viewer__nav--next" data-image-next aria-label="الصورة التالية">›</button>
        </div>
    </dialog>
    <script src="<?= $e($baseUrl) ?>/js/image-viewer.js" defer></script>
</div>
