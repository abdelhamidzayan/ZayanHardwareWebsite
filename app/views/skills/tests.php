<div class="page-head"><h1 class="page-head__title">اختبارات المهارات الفنية</h1></div>
<div class="container">
    <?php if (!$tests) : ?>
        <section class="card empty-state"><h2>لا توجد اختبارات متاحة حاليًا</h2><p class="empty-state__text">ستظهر الاختبارات عندما تعتمدها الإدارة.</p></section>
    <?php else : ?>
        <?php foreach ($tests as $test) : ?>
            <section class="card" style="padding:1rem;margin-bottom:1rem">
                <h2><?= $e($test['title']) ?></h2>
                <p class="muted">المهارة: <?= $e($test['skill_name']) ?> · درجة النجاح: <?= $e((string) $test['pass_score']) ?>%</p>
                <p>المحاولات المتبقية: <?= (int) $test['state']['attempts_remaining'] ?></p>
                <?php if ($test['state']['wait_minutes'] > 0) : ?><p class="muted">انتظر <?= (int) $test['state']['wait_minutes'] ?> دقيقة قبل المحاولة التالية.</p><?php endif; ?>
                <?php if ($test['state']['can_attempt']) : ?><a class="btn btn--primary" href="<?= $e($baseUrl) ?>/skills/tests/<?= (int) $test['id'] ?>">ابدأ الاختبار</a><?php else : ?><button class="btn btn--secondary" disabled>غير متاح الآن</button><?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
