<div class="page-head">
    <h2 class="page-head__title">الملف الشخصي</h2>
</div>

<div class="container">
    <section class="card profile">
        <header class="profile__head">
            <span class="avatar avatar--lg" aria-hidden="true"><?= $e($profile['initials']) ?></span>
            <div class="profile__who">
                <h3 class="profile__name"><?= $e($profile['display_name']) ?></h3>
                <p class="profile__user tech">@<?= $e($profile['username']) ?></p>
            </div>
            <span class="badge"><?= $e($profile['role_label']) ?></span>
        </header>

        <dl class="profile__list">
            <?php if ($profile['device'] !== '') : ?>
                <div><dt>الجهاز</dt><dd><?= $e($profile['device']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['level'] !== '') : ?>
                <div>
                    <dt>المستوى</dt>
                    <dd>
                        <?= $e($profile['level']) ?>
                        <?php if ((int) $profile['level_verified'] === 0) : ?>
                            <span class="profile__unverified">غير مختبر</span>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ($profile['experience'] !== '') : ?>
                <div><dt>سنوات الخبرة</dt><dd><?= $e($profile['experience']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['workshop'] !== '') : ?>
                <div><dt>الورشة</dt><dd><?= $e($profile['workshop']) ?></dd></div>
            <?php endif; ?>
            <?php if ($skillRatings) : ?>
                <div>
                    <dt>تقييم المهارات</dt>
                    <dd>
                        <?php foreach ($skillRatings as $skillRating) : ?>
                            <div style="margin:.35rem 0">
                                <span><?= $e($skillRating['skill_name']) ?>: <?= $e((string) $skillRating['rating']) ?>%</span>
                                <small class="muted">(<?= (int) $skillRating['approved_assessments'] ?> تقييمات مجتازة)</small>
                            </div>
                        <?php endforeach; ?>
                    </dd>
                </div>
            <?php endif; ?>
            <div><dt>تاريخ الانضمام</dt><dd class="tech"><?= $e($profile['joined']) ?></dd></div>
        </dl>

        <?php if ($currentUser && ($currentUser['role'] ?? '') === 'admin') : ?>
            <p><a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/admin/users">إدارة الحسابات</a></p>
        <?php endif; ?>

        <?php if ($profile['is_owner']) : ?>
            <p><a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/skills/tests">اختبارات المهارات</a></p>
        <?php endif; ?>
    </section>
</div>
