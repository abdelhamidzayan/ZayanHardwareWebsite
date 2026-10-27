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
            <?php if ($profile['experience'] !== '') : ?>
                <div><dt>الخبرة</dt><dd><?= $e($profile['experience']) ?></dd></div>
            <?php endif; ?>
            <?php if ($profile['workshop'] !== '') : ?>
                <div><dt>الورشة</dt><dd><?= $e($profile['workshop']) ?></dd></div>
            <?php endif; ?>
            <div><dt>تاريخ الانضمام</dt><dd class="tech"><?= $e($profile['joined']) ?></dd></div>
        </dl>

        <?php if ($profile['is_owner']) : ?>
            <form method="post" action="<?= $e($baseUrl) ?>/logout">
                <?= $csrf() ?>
                <button class="btn btn--secondary btn--block" type="submit">تسجيل الخروج</button>
            </form>
        <?php endif; ?>
    </section>
</div>
