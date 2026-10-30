<?php
$isRequester = (bool) $isRequester;
$isHelper = (bool) $isHelper;
$otherName = $isRequester ? $helpRequest['helper_name'] : $helpRequest['requester_name'];
$otherId = $isRequester ? (int) $helpRequest['helper_id'] : (int) $helpRequest['requester_id'];
$otherPath = $isRequester ? $helpRequest['helper_avatar_path'] : $helpRequest['requester_avatar_path'];
$otherKey = $isRequester ? $helpRequest['helper_avatar_key'] : $helpRequest['requester_avatar_key'];
$otherAvatar = !empty($otherPath) ? '/avatar/' . $otherId : '/assets/avatars/' . rawurlencode((string) ($otherKey ?? 'technician-blue')) . '.svg';
$statusLabel = match ($helpRequest['status']) { 'pending' => 'في انتظار القبول', 'active' => 'محادثة نشطة', 'closed' => 'مغلق ومحفوظ للطرفين', 'declined' => 'تم الاعتذار', default => 'طلب مساعدة' };
?>
<div class="page-head personal-help-head">
    <a class="issue-back" href="<?= $e($baseUrl) ?>/help-requests">← كل طلبات المساعدة</a>
    <span class="help-status help-status--<?= $e($helpRequest['status']) ?>"><?= $e($statusLabel) ?></span>
</div>
<div class="container personal-help-conversation">
    <section class="card personal-help-summary">
        <div class="personal-help-summary__member">
            <img class="avatar avatar--lg avatar-image" src="<?= $e($baseUrl . $otherAvatar) ?>" alt="" width="64" height="64">
            <div><strong><?= $e($otherName) ?></strong><p class="muted"><?= $isRequester ? 'الشخص الذي طلبت منه المساعدة' : 'صاحب طلب المساعدة' ?></p></div>
        </div>
        <h1><?= $e($helpRequest['subject']) ?></h1>
        <p class="muted">طلب #<?= (int) $helpRequest['id'] ?> · بدأ في <?= $e($helpRequest['created_at']) ?></p>
        <?php if ($helpRequest['status'] === 'pending' && $isHelper) : ?>
            <p class="personal-help-notice">راجع تفاصيل الطلب قبل القبول. لن تبدأ المحادثة الخاصة إلا بعد قبولك.</p>
            <div class="personal-help-actions">
                <form method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $helpRequest['id'] ?>/accept"><?= $csrf() ?><button class="btn btn--primary" type="submit">قبول طلب المساعدة</button></form>
                <form method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $helpRequest['id'] ?>/decline"><?= $csrf() ?><button class="btn btn--secondary" type="submit">اعتذار</button></form>
            </div>
        <?php elseif ($helpRequest['status'] === 'pending') : ?>
            <p class="personal-help-notice">تم إرسال الطلب، وبانتظار موافقة العضو الآخر.</p>
        <?php elseif ($helpRequest['status'] === 'declined') : ?>
            <p class="personal-help-notice">تم الاعتذار عن الطلب. لن تُفتح محادثة، وسيظل سجل الطلب ظاهرًا للطرفين.</p>
        <?php endif; ?>
    </section>

    <section class="card personal-help-thread" aria-label="رسائل طلب المساعدة">
        <div class="personal-help-thread__head"><h2>المحادثة الخاصة</h2><span class="muted">لا تظهر هذه الرسائل للعامة</span></div>
        <?php foreach ($messages as $message) : ?>
            <?php
            $isMine = (int) $message['sender_id'] === (int) $currentUser['id'];
            $senderAvatar = !empty($message['sender_avatar_path']) ? '/avatar/' . (int) $message['sender_id'] : '/assets/avatars/' . rawurlencode((string) ($message['sender_avatar_key'] ?? 'technician-blue')) . '.svg';
            ?>
            <article class="personal-message<?= $isMine ? ' is-mine' : '' ?>">
                <div class="personal-message__head">
                    <img class="avatar avatar-image" src="<?= $e($baseUrl . $senderAvatar) ?>" alt="" width="34" height="34">
                    <strong><?= $e($message['sender_name']) ?></strong><time><?= $e($message['created_at']) ?></time>
                </div>
                <?php if (trim((string) $message['body']) !== '') : ?><p class="personal-message__body" dir="auto"><?= nl2br($e($message['body'])) ?></p><?php endif; ?>
                <?php if (!empty($message['attachments'])) : ?>
                    <div class="personal-message__attachments">
                        <?php foreach ($message['attachments'] as $attachment) : ?>
                            <?php $attachmentUrl = $baseUrl . '/help-requests/attachments/' . (int) $attachment['id']; ?>
                            <?php if (str_starts_with($attachment['mime_type'], 'image/')) : ?>
                                <a href="<?= $e($attachmentUrl) ?>" target="_blank" rel="noopener"><img src="<?= $e($attachmentUrl) ?>" alt="مرفق خاص" loading="lazy"></a>
                            <?php else : ?>
                                <a class="personal-message__pdf" href="<?= $e($attachmentUrl) ?>" target="_blank" rel="noopener">عرض ملف PDF</a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if ($helpRequest['status'] === 'active') : ?>
            <form class="personal-message-composer" method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $helpRequest['id'] ?>/messages" enctype="multipart/form-data">
                <?= $csrf() ?>
                <label class="field"><span class="field__label">رسالتك</span><textarea class="field__input" name="body" maxlength="5000" rows="3" placeholder="اكتب تفاصيل القياسات أو الاختبارات..."></textarea></label>
                <label class="btn btn--secondary personal-message-composer__attach">إرفاق صور أو مخططات<input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple></label>
                <p class="field__hint">حتى 8 مرفقات، بحد أقصى 8 ميجابايت للملف. الصور والمخططات خاصة بين الطرفين.</p>
                <button class="btn btn--primary" type="submit">إرسال الرسالة</button>
            </form>
        <?php elseif ($helpRequest['status'] === 'closed') : ?>
            <p class="personal-help-closed-note">تم إغلاق الطلب. الرسائل والمرفقات محفوظة للطرفين فقط، ولا يمكن إرسال رسائل جديدة.</p>
        <?php endif; ?>
    </section>

    <?php if ($helpRequest['status'] === 'active') : ?>
        <form class="card personal-help-close" method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $helpRequest['id'] ?>/close">
            <?= $csrf() ?><p>انتهى النقاش؟ يمكنك إغلاق الطلب. ستظل الرسائل محفوظة، ولن يمكن استكمال المحادثة بعد الإغلاق.</p>
            <button class="btn btn--secondary" type="submit">إغلاق طلب المساعدة</button>
        </form>
    <?php endif; ?>

    <?php if ($helpRequest['status'] === 'closed' && $isHelper && !$publication) : ?>
        <section class="card help-publication-proposal">
            <h2>تحويل المساعدة إلى مرجع عام</h2>
            <p class="muted">بصفتك العضو الذي استقبل طلب المساعدة، يمكنك اقتراح نشر ملخص فني. لن ننشر الرسائل الخاصة نفسها، بل ملخصًا تكتبه أنت. سيظل النشر معلقًا حتى يوافق الطرف الآخر.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $helpRequest['id'] ?>/publication" class="issue-form">
                <?= $csrf() ?>
                <label class="field"><span class="field__label">عنوان المرجع</span><input class="field__input" name="title" maxlength="180" minlength="5" required></label>
                <label class="field"><span class="field__label">وصف المشكلة</span><textarea class="field__input" name="problem_summary" minlength="10" maxlength="5000" rows="3" required></textarea></label>
                <label class="field"><span class="field__label">خطوات التشخيص والقياسات</span><textarea class="field__input" name="diagnosis_summary" minlength="10" maxlength="5000" rows="4" required></textarea></label>
                <label class="field"><span class="field__label">الحل النهائي</span><textarea class="field__input" name="solution_summary" minlength="5" maxlength="5000" rows="3" required></textarea></label>
                <fieldset class="field"><legend class="field__label">هل تريد إظهار هويتك في المرجع العام؟</legend>
                    <label class="check"><input type="radio" name="helper_show_identity" value="1" required><span>نعم، إظهار اسمي وصورتي</span></label>
                    <label class="check"><input type="radio" name="helper_show_identity" value="0"><span>لا، أريد إخفاء هويتي</span></label>
                </fieldset>
                <button class="btn btn--primary" type="submit">إرسال طلب الموافقة على النشر</button>
            </form>
        </section>
    <?php elseif ($publication && $publication['status'] === 'pending' && $isRequester) : ?>
        <section class="card help-publication-consent">
            <h2>موافقتك على نشر المساعدة</h2>
            <p>الطرف الآخر يقترح تحويل ملخص المساعدة إلى مرجع عام. الرسائل الخاصة نفسها لن تُنشر.</p>
            <form method="post" action="<?= $e($baseUrl) ?>/help-publications/<?= (int) $publication['id'] ?>/respond" data-publication-consent-form>
                <?= $csrf() ?>
                <fieldset class="field"><legend class="field__label">هل توافق على نشر ملخص المساعدة للعامة؟</legend>
                    <label class="check"><input type="radio" name="consent" value="1" required data-publication-yes><span>نعم، أوافق على النشر</span></label>
                    <label class="check"><input type="radio" name="consent" value="0" required data-publication-no><span>لا، أرفض النشر</span></label>
                </fieldset>
                <fieldset class="field" data-publication-identity hidden><legend class="field__label">هل توافق على إظهار هويتك في المرجع؟</legend>
                    <label class="check"><input type="radio" name="show_identity" value="1" required><span>نعم، إظهار اسمي وصورتي</span></label>
                    <label class="check"><input type="radio" name="show_identity" value="0" required><span>لا، إخفاء هويتي</span></label>
                </fieldset>
                <p class="field__hint">يمكنك الموافقة على نشر المحتوى مع إخفاء هويتك. قرار إظهار هوية الطرف الآخر لا يتخذه إلا هو.</p>
                <button class="btn btn--primary" type="submit">إرسال قراري</button>
            </form>
        </section>
    <?php elseif ($publication && $publication['status'] === 'pending') : ?>
        <section class="card personal-help-notice">تم إرسال اقتراح النشر، وبانتظار موافقة صاحب الطلب. لا يظهر المحتوى للعامة الآن.</section>
    <?php elseif ($publication && $publication['status'] === 'declined') : ?>
        <section class="card personal-help-notice">تم رفض نشر المساعدة. ستظل المحادثة محفوظة للطرفين فقط داخل الطلب المغلق.</section>
    <?php elseif ($publication && $publication['status'] === 'published' && !empty($publication['public_post_id'])) : ?>
        <section class="card personal-help-notice">تم نشر ملخص المساعدة بموافقة الطرفين. <a class="text-action text-action--primary" href="<?= $e($baseUrl) ?>/help-library/<?= (int) $publication['public_post_id'] ?>">عرض المرجع العام</a></section>
    <?php endif; ?>
</div>
<script src="<?= $e($baseUrl) ?>/js/help-requests.js" defer></script>
