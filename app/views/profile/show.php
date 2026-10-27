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
            <?php if ($profile['specialization'] !== '') : ?>
                <div><dt>التخصص</dt><dd><?= $e($profile['specialization']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['device_specializations'] !== '') : ?>
                <div><dt>الأجهزة</dt><dd><?= $e(implode('، ', array_map(static fn (string $device): string => $device === 'iphone' ? 'iPhone' : 'Android', array_filter(explode(',', $profile['device_specializations']))))) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['skill_domains'] !== '') : ?>
                <div><dt>مجالات الإصلاح</dt><dd><?= $e($profile['skill_domains']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['experience'] !== '') : ?>
                <div><dt>الخبرة</dt><dd><?= $e($profile['experience']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['workshop'] !== '') : ?>
                <div><dt>الورشة</dt><dd><?= $e($profile['workshop']) ?></dd></div>
            <?php endif; ?>
            <div><dt>تاريخ الانضمام</dt><dd class="tech"><?= $e($profile['joined']) ?></dd></div>
        </dl>

        <?php if ($currentUser && ($currentUser['role'] ?? '') === 'admin') : ?>
            <p><a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/admin/users">إدارة الحسابات</a></p>
        <?php endif; ?>

        <?php if ($profile['is_owner']) : ?>
            <p><a class="btn btn--secondary btn--block" href="<?= $e($baseUrl) ?>/skills/tests">اختبارات المهارات</a></p>
            <form method="post" action="<?= $e($baseUrl) ?>/account/deletion-request" onsubmit="return confirm('سيتم تعطيل الحساب وإخفاؤه لمدة 30 يومًا قبل الحذف النهائي. هل تريد المتابعة؟')">
                <?= $csrf() ?>
                <button class="btn btn--secondary btn--block" type="submit">طلب حذف الحساب</button>
            </form>
            <form method="post" action="<?= $e($baseUrl) ?>/logout">
                <?= $csrf() ?>
                <button class="btn btn--secondary btn--block" type="submit">تسجيل الخروج</button>
            </form>
        <?php endif; ?>
    </section>
</div>
