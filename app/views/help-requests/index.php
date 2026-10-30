<div class="page-head">
    <div><h1 class="page-head__title">طلبات المساعدة الشخصية</h1><p class="muted">محادثات فنية خاصة مرتبطة بطلب واضح، وليست دردشة عامة.</p></div>
</div>
<div class="container personal-help-list">
    <?php if (!$requests) : ?>
        <div class="card empty-state"><h2>لا توجد طلبات مساعدة حتى الآن</h2><p class="empty-state__text">عندما ترسل طلبًا أو يستعين بك أحد الأعضاء، ستجد المحادثة هنا.</p></div>
    <?php else : ?>
        <?php foreach ($requests as $request) : ?>
            <?php
            $isRequester = (int) $request['requester_id'] === (int) $currentUser['id'];
            $otherName = $isRequester ? $request['helper_name'] : $request['requester_name'];
            $otherUsername = $isRequester ? $request['helper_username'] : $request['requester_username'];
            $otherId = $isRequester ? $request['helper_id'] : $request['requester_id'];
            $otherPath = $isRequester ? $request['helper_avatar_path'] : $request['requester_avatar_path'];
            $otherKey = $isRequester ? $request['helper_avatar_key'] : $request['requester_avatar_key'];
            $avatarSrc = !empty($otherPath) ? '/avatar/' . (int) $otherId : '/assets/avatars/' . rawurlencode((string) ($otherKey ?? 'technician-blue')) . '.svg';
            $statusLabel = match ($request['status']) { 'pending' => 'في انتظار القبول', 'active' => 'محادثة نشطة', 'closed' => 'مغلق ومحفوظ', 'declined' => 'تم الاعتذار', default => 'غير معروف' };
            ?>
            <article class="card personal-help-card">
                <div class="personal-help-card__head">
                    <img class="avatar avatar-image" src="<?= $e($baseUrl . $avatarSrc) ?>" alt="" width="48" height="48">
                    <div class="personal-help-card__identity"><strong><?= $e($otherName) ?></strong><span class="muted tech">@<?= $e($otherUsername) ?></span></div>
                    <span class="help-status help-status--<?= $e($request['status']) ?>"><?= $e($statusLabel) ?></span>
                </div>
                <h2><a href="<?= $e($baseUrl) ?>/help-requests/<?= (int) $request['id'] ?>"><?= $e($request['subject']) ?></a></h2>
                <p class="personal-help-card__excerpt"><?= $e(mb_strimwidth((string) ($request['last_message'] ?? ''), 0, 180, '…', 'UTF-8')) ?></p>
                <div class="personal-help-card__foot"><span class="muted"><?= $e($request['last_message_at']) ?></span><a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/help-requests/<?= (int) $request['id'] ?>">فتح الطلب</a></div>
                <?php if ($request['status'] === 'pending' && !$isRequester) : ?>
                    <div class="personal-help-card__actions">
                        <form method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $request['id'] ?>/accept"><?= $csrf() ?><button class="btn btn--primary" type="submit">قبول المساعدة</button></form>
                        <form method="post" action="<?= $e($baseUrl) ?>/help-requests/<?= (int) $request['id'] ?>/decline"><?= $csrf() ?><button class="btn btn--secondary" type="submit">اعتذار</button></form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
