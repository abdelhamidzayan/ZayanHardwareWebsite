<?php
$features = [
    [
        'title' => 'وثّق العطل بالأدلة',
        'text'  => 'سجّل قياسات الجهد والتيار والمقاومة، وأرفق صور البوردة ولقطات المخططات.',
        'icon'  => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    ],
    [
        'title' => 'اشرح بصوتك',
        'text'  => 'سجّل ملاحظة صوتية أثناء الفحص بدل كتابة كل التفاصيل.',
        'icon'  => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    ],
    [
        'title' => 'حلول يقبلها صاحب الحالة',
        'text'  => 'يختار صاحب العطل الحل الذي أصلح الجهاز، فيظهر مميّزًا للجميع.',
        'icon'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    ],
    [
        'title' => 'نصائح من خبراء موثّقين',
        'text'  => 'مقالات قصيرة عن قراءة القياسات وتتبّع الدوائر وتجنّب أخطاء الفحص.',
        'icon'  => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>',
    ],
    [
        'title' => 'سوق بين الفنّيين',
        'text'  => 'معدات وقطع غيار مستعملة تُتداول بين الفنّيين فقط.',
        'icon'  => '<path d="m4 9 1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M10 20v-5h4v5"/>',
    ],
    [
        'title' => 'نطاق واحد واضح',
        'text'  => 'هاردوير الهواتف فقط: بلا عملاء، وبلا نقاشات برمجيات أو فك حماية.',
        'icon'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/>',
    ],
];

$steps = [
    ['title' => 'انشر العطل', 'text' => 'اكتب الأعراض وقياساتك وما جرّبته، وأضف الصور أو ملاحظة صوتية.'],
    ['title' => 'استقبل الحلول', 'text' => 'يقترح فنّيون ذوو خبرة خطوات فحص مرتّبة، ويمكنك مناقشتهم.'],
    ['title' => 'اختر الحل الناجح', 'text' => 'بعد التجربة، اقبل الحل الذي أصلح العطل ليستفيد منه من يأتي بعدك.'],
];
?>
<section class="section hero">
    <div class="hero__copy">
        <h1 class="hero__title">حيث تُحَلّ أعطال الهاردوير الصعبة</h1>
        <p class="hero__text">مجتمع لفنّيي صيانة الهواتف على مستوى البوردة. وثّق العطل بقياساتك وصورك وصوتك، واحصل على حلول مجرّبة من فنّيين يعرفون الدائرة.</p>
        <div class="hero__actions">
            <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/register">إنشاء حساب</a>
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a>
        </div>
        <p class="hero__note">المنصة مخصّصة للفنّيين فقط ولا تقبل حسابات العملاء.</p>
    </div>

    <div class="hero__preview" aria-label="معاينة متحركة لمنشورات الهاردوير">
        <?php foreach ($previewIssues as $index => $issue) : ?>
            <div class="hero__deck-card hero__deck-card--<?= $index + 1 ?>">
                <?php require __DIR__ . '/../partials/issue-card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" aria-labelledby="features-title">
    <h2 class="section__title" id="features-title">ما الذي ستجده هنا</h2>
    <div class="features">
        <?php foreach ($features as $feature) : ?>
            <article class="card feature">
                <span class="feature__icon" aria-hidden="true">
                    <svg class="icon" viewBox="0 0 24 24"><?= $feature['icon'] ?></svg>
                </span>
                <h3 class="feature__title"><?= $e($feature['title']) ?></h3>
                <p class="feature__text"><?= $e($feature['text']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" aria-labelledby="steps-title">
    <h2 class="section__title" id="steps-title">كيف تعمل المنصة</h2>
    <ol class="steps">
        <?php foreach ($steps as $index => $step) : ?>
            <li class="step">
                <span class="step__num" aria-hidden="true"><?= $index + 1 ?></span>
                <div>
                    <h3 class="step__title"><?= $e($step['title']) ?></h3>
                    <p class="step__text"><?= $e($step['text']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<section class="section">
    <div class="cta-band">
        <h2 class="cta-band__title">شارك أول حالة لك</h2>
        <p class="cta-band__text">أنشئ حسابك كفنّي، وابدأ بتوثيق عطل أو مساعدة غيرك في حلّ عطل.</p>
        <div class="hero__actions">
            <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/register">إنشاء حساب</a>
            <a class="btn btn--ghost-light" href="<?= $e($baseUrl) ?>/login">لدي حساب بالفعل</a>
        </div>
    </div>
</section>
