<?php $currentUser = $currentUser ?? null; ?>
<div class="page-head">
    <h2 class="page-head__title">الملف الشخصي</h2>
</div>

<div class="container public-profile">
    <section class="card profile">
        <div class="profile__intro">
        <header class="profile__head">
            <img class="avatar avatar--lg avatar-image" src="<?= $e($baseUrl . $profile['avatar_src']) ?>" alt="الصورة الشخصية لـ <?= $e($profile['display_name']) ?>" width="72" height="72">
            <div class="profile__who">
                <h1 class="profile__name"><?= $e($profile['display_name']) ?></h1>
                <p class="profile__user tech">@<?= $e($profile['username']) ?></p>
            </div>
            <span class="badge"><?= $e($profile['role_label']) ?></span>
        </header>

        <?php if ($profile['biography'] !== '') : ?><p class="public-profile__bio"><?= $e($profile['biography']) ?></p><?php endif; ?>
        </div>

        <section class="profile-details" aria-labelledby="profile-details-title">
            <h2 id="profile-details-title" class="profile-section-title">المعلومات الفنية</h2>
        <dl class="profile__list">
            <?php if ($profile['device'] !== '') : ?><div><dt>مجال التخصص</dt><dd><?= $e($profile['device']) ?></dd></div><?php endif; ?>
            <?php if ($profile['level'] !== '') : ?>
                <div><dt>المستوى</dt><dd><?= $e($profile['level']) ?><?php if ((int) $profile['level_verified'] === 0) : ?> <span class="profile__unverified">غير مختبر</span><?php endif; ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['experience'] !== '') : ?><div><dt>سنوات الخبرة</dt><dd><?= $e($profile['experience']) ?></dd></div><?php endif; ?>
            <?php if ($profile['workshop'] !== '') : ?><div><dt>الورشة</dt><dd><?= $e($profile['workshop']) ?></dd></div><?php endif; ?>
            <div><dt>تاريخ الانضمام</dt><dd class="tech"><?= $e($profile['joined']) ?></dd></div>
        </dl>
        </section>

        <?php if ($skillRatings) : ?>
            <section class="profile-skills" aria-labelledby="profile-skills-title">
                <h2 id="profile-skills-title" class="profile-section-title">تقييم المهارات</h2>
                <?php foreach ($skillRatings as $skillRating) : ?>
                    <div class="profile-skills__row"><span><?= $e($skillRating['skill_name']) ?></span><strong><?= $e((string) $skillRating['rating']) ?>%</strong><small class="muted"><?= (int) $skillRating['approved_assessments'] ?> تقييمات مجتازة</small></div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($profile['is_owner']) : ?>
            <div class="profile__actions">
            <a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/settings">تعديل إعدادات الملف الشخصي</a>
            <a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/skills/tests">اختبارات المهارات</a>
        <?php elseif ($currentUser) : ?>
            <?php if ($profile['accept_help_requests']) : ?>
                <details class="profile-help-request">
                    <summary class="btn btn--primary btn--block">طلب مساعدة شخصية</summary>
                    <form class="profile-help-request__form" method="post" action="<?= $e($baseUrl) ?>/help-requests/create" enctype="multipart/form-data">
                        <?= $csrf() ?>
                        <input type="hidden" name="helper_id" value="<?= (int) $profile['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= $e('/profile/' . rawurlencode($profile['username'])) ?>">
                        <label class="field"><span class="field__label">موضوع طلب المساعدة</span><input class="field__input" name="subject" minlength="4" maxlength="180" placeholder="مثال: عطل شحن iPhone 13" required></label>
                        <label class="field"><span class="field__label">اشرح المشكلة</span><textarea class="field__input" name="body" minlength="10" maxlength="5000" rows="5" placeholder="اكتب تفاصيل العطل والقياسات والتجارب التي قمت بها..." required></textarea></label>
                        <label class="field"><span class="field__label">صور أو مخططات (اختياري)</span><input class="field__input" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple></label>
                        <p class="field__hint">حتى 8 مرفقات، كل ملف حتى 8 ميجابايت. لن يراها إلا طرفا طلب المساعدة.</p>
                        <p class="field__hint">سيصل الطلب إلى العضو، ولن تبدأ المحادثة الخاصة إلا بعد قبوله.</p>
                        <button class="btn btn--primary btn--block" type="submit">إرسال طلب المساعدة</button>
                    </form>
                </details>
            <?php else : ?>
                <p class="profile-help-request__disabled">هذا العضو لا يستقبل طلبات المساعدة الشخصية حاليًا.</p>
            <?php endif; ?>
        <?php elseif (!$currentUser && $profile['accept_help_requests']) : ?>
            <a class="btn btn--primary btn--block" href="<?= $e($baseUrl) ?>/login">سجّل الدخول لطلب مساعدة شخصية</a>
        <?php endif; ?>

        <?php if ($profile['is_owner']) : ?></div><?php endif; ?>
    </section>

    <?php if ($profile['is_owner'] && ($currentUser['role'] ?? '') === 'admin') : ?>
        <section class="card account-admin" aria-labelledby="account-admin-title">
            <div class="account-admin__head">
                <div>
                    <span class="account-admin__eyebrow">مساحة خاصة بالمدير</span>
                    <h2 id="account-admin-title">لوحة الإدارة</h2>
                    <p class="muted">كل أدوات إدارة Zayan Hardware مجمعة هنا في مكان واحد.</p>
                </div>
                <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/admin">فتح لوحة التحكم</a>
            </div>

            <div class="account-admin__group">
                <h3>إدارة المجتمع</h3>
                <div class="account-admin__links">
                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin/users"><strong>إدارة الحسابات</strong><span>مراجعة الأعضاء والصلاحيات وحالة الحسابات</span></a>
                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin/device-model-suggestions"><strong>اقتراحات موديلات الأجهزة</strong><span>مراجعة واعتماد أو رفض الموديلات المقترحة</span></a>
                </div>
            </div>

            <div class="account-admin__group">
                <h3>المحتوى والتقييم</h3>
                <div class="account-admin__links">
                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin"><strong>منشورات الرئيسية</strong><span>اختيار المنشورات الفنية المعروضة في الرئيسية</span></a>
                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin/skill-tests"><strong>اختبارات المهارات</strong><span>إنشاء الاختبارات ومراجعة تفعيلها</span></a>
                </div>
            </div>

            <div class="account-admin__group">
                <h3>النظام</h3>
                <div class="account-admin__links">
                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin/update"><strong>تحديث الموقع</strong><span>تطبيق آخر إصدار من GitHub على النسخة المحلية</span></a>\n                    <a class="account-admin__link" href="<?= $e($baseUrl) ?>/admin/terminal"><strong>وحدة التشخيص</strong><span>فحص الملفات والأوامر والسجلات من المتصفح</span></a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="profile-activity">
        <div class="profile-activity__head"><h2>مشاركات <?= $e($profile['display_name']) ?></h2><span class="badge"><?= count($profilePosts) ?></span></div>
        <?php if (!$profilePosts) : ?>
            <div class="card empty-state"><p class="empty-state__text">لم ينشر هذا العضو مشاركات بعد.</p></div>
        <?php else : ?>
            <?php foreach ($profilePosts as $post) : ?>
                <article class="card profile-post">
                    <div class="profile-post__meta"><span class="tag"><?= $e(match ($post['content_type']) { 'technical_post' => 'معلومة فنية', 'discussion' => 'نقاش عام', default => 'طلب مساعدة' }) ?></span><span class="muted"><?= $e($post['created_at']) ?></span></div>
                    <h3><a href="<?= $e($baseUrl) ?>/issues/<?= (int) $post['id'] ?>"><?= $e($post['title']) ?></a></h3>
                    <p class="muted"><?= $e((string) ($post['device_model'] ?? '')) ?><?= !empty($post['category']) ? ' · ' . $e($post['category']) : '' ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="profile-activity">
        <div class="profile-activity__head"><h2>مساعدات منشورة</h2><a class="text-action text-action--primary" href="<?= $e($baseUrl) ?>/help-library">مكتبة المساعدات</a></div>
        <?php if (!$publicHelpPosts) : ?>
            <div class="card empty-state"><p class="empty-state__text">لا توجد مساعدات منشورة مرتبطة بهذا الملف حتى الآن.</p></div>
        <?php else : ?>
            <?php foreach ($publicHelpPosts as $post) : ?>
                <article class="card profile-post">
                    <h3><a href="<?= $e($baseUrl) ?>/help-library/<?= (int) $post['id'] ?>"><?= $e($post['title']) ?></a></h3>
                    <p class="muted"><?= $e(mb_strimwidth((string) $post['problem_summary'], 0, 180, '…', 'UTF-8')) ?></p>
                    <div class="help-library-card__people"><span class="help-library-person"><?php if (!empty($post['requester_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['requester_user_id']) ?>" alt="" width="32" height="32"><?php endif; ?><?= !empty($post['requester_display_name']) ? $e($post['requester_display_name']) : 'عضو مجهول' ?></span><span class="help-library-person"><?php if (!empty($post['helper_user_id'])) : ?><img class="avatar avatar-image" src="<?= $e($baseUrl . '/avatar/' . (int) $post['helper_user_id']) ?>" alt="" width="32" height="32"><?php endif; ?><?= !empty($post['helper_display_name']) ? $e($post['helper_display_name']) : 'عضو مجهول' ?></span></div>
                    <span class="muted"><?= $e($post['created_at']) ?></span>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
