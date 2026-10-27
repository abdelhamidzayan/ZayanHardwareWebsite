<?php
$topics = [
    [
        'title' => 'التسجيل والدخول',
        'text' => 'مساعدة في إنشاء الحساب، تسجيل الدخول، أو التعامل مع مشكلة في بيانات الحساب.',
        'icon' => '<circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
    ],
    [
        'title' => 'نشر عطل هاردوير',
        'text' => 'اعرف إزاي تكتب وصف واضح للعطل وتضيف القياسات والصور والخطوات اللي جرّبتها.',
        'icon' => '<path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/>',
    ],
    [
        'title' => 'الصور والمرفقات',
        'text' => 'نصائح لتصوير البوردة بوضوح وإرفاق صور تساعد الفنيين على فهم الحالة.',
        'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/>',
    ],
];
?>
<div class="help-page">
    <section class="help-hero" aria-labelledby="help-title">
        <span class="help-hero__eyebrow">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.2 1.8c-1.1 1-1.8 1.2-1.8 2.7M12 17h.01"/></svg>
            مركز المساعدة والدعم
        </span>
        <h1 class="help-hero__title" id="help-title">محتاج مساعدة؟ إحنا هنا نسهّل عليك استخدام Zayan Hardware</h1>
        <p class="help-hero__text">هنا هتلاقي شرح لطريقة استخدام المنصة، وإرشادات للتعامل مع المشكلات العامة، وإجابات لأهم الأسئلة. المنصة مخصّصة لفنيّي هاردوير الهواتف ومشاركة الخبرات الفنية.</p>
        <div class="help-hero__actions">
            <a class="btn btn--primary" href="<?= $e($baseUrl) ?>/register">إنشاء حساب</a>
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-topics-title">
        <div class="help-section__head">
            <div>
                <h2 class="help-section__title" id="help-topics-title">اختار الموضوع اللي محتاجه</h2>
                <p class="help-section__intro">إرشادات سريعة لأهم أجزاء الموقع.</p>
            </div>
        </div>
        <div class="help-categories">
            <?php foreach ($topics as $topic) : ?>
                <article class="help-card">
                    <span class="help-card__icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24"><?= $topic['icon'] ?></svg></span>
                    <h3 class="help-card__title"><?= $e($topic['title']) ?></h3>
                    <p class="help-card__text"><?= $e($topic['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-how-title">
        <div class="help-section__head">
            <div>
                <h2 class="help-section__title" id="help-how-title">إزاي تستخدم المنصة؟</h2>
                <p class="help-section__intro">ابدأ بالخطوات دي علشان تستفيد من مجتمع الفنيين.</p>
            </div>
        </div>
        <ol class="steps">
            <li class="step"><span class="step__num">1</span><div><h3 class="step__title">أنشئ حساب فني</h3><p class="step__text">سجّل بياناتك كمختص في صيانة هاردوير الهواتف. المنصة للفنيين وليست لطلبات صيانة العملاء.</p></div></li>
            <li class="step"><span class="step__num">2</span><div><h3 class="step__title">اعرض الحالة بوضوح</h3><p class="step__text">اكتب موديل الجهاز والأعراض والقياسات، ووضّح خطوات الفحص اللي نفذتها بالفعل.</p></div></li>
            <li class="step"><span class="step__num">3</span><div><h3 class="step__title">ناقش الحلول وشارك النتيجة</h3><p class="step__text">تابع ردود الفنيين، جرّب خطوات الفحص المناسبة، ثم وضّح الحل اللي نجح ليستفيد الآخرون.</p></div></li>
        </ol>
    </section>

    <section class="help-section" aria-labelledby="help-troubleshoot-title">
        <div class="help-section__head">
            <div>
                <h2 class="help-section__title" id="help-troubleshoot-title">حلول للمشكلات الشائعة</h2>
                <p class="help-section__intro">جرّب الخطوات دي قبل ما تعيد المحاولة.</p>
            </div>
        </div>
        <div class="card" style="padding: var(--space-5);">
            <ul class="help-list">
                <li><span class="help-list__check">✓</span><div><strong>مش قادر تسجّل الدخول؟</strong><br>تأكد من كتابة رقم الهاتف أو البريد الإلكتروني وكلمة المرور بشكل صحيح، وراجع لغة لوحة المفاتيح والمسافات الزائدة.</div></li>
                <li><span class="help-list__check">✓</span><div><strong>الصفحة مش بتفتح أو شكلها مش مضبوط؟</strong><br>حدّث الصفحة، وتأكد من اتصال الإنترنت. لو المشكلة مستمرة، جرّب نافذة عادية أخرى أو متصفحًا محدثًا.</div></li>
                <li><span class="help-list__check">✓</span><div><strong>الصورة مش واضحة؟</strong><br>التقط الصورة في إضاءة جيدة، وركّز على المنطقة المطلوبة، وأرفق صورة عامة وأخرى قريبة عند الحاجة.</div></li>
                <li><span class="help-list__check">✓</span><div><strong>مش لاقي القسم اللي بتدور عليه؟</strong><br>بعض خصائص المنصة ما زالت قيد التطوير، وقد لا تكون كل الأقسام متاحة حاليًا.</div></li>
            </ul>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-faq-title">
        <div class="help-section__head">
            <div>
                <h2 class="help-section__title" id="help-faq-title">الأسئلة الشائعة</h2>
                <p class="help-section__intro">إجابات مختصرة عن طبيعة المنصة واستخدامها.</p>
            </div>
        </div>
        <div class="help-faq">
            <details><summary>مين يقدر يستخدم Zayan Hardware؟</summary><div class="help-faq__answer">المنصة موجّهة لفنيّي ومختصي هاردوير الهواتف، لمشاركة الأعطال والقياسات والصور والحلول الفنية. مش مخصّصة لاستقبال طلبات صيانة العملاء.</div></details>
            <details><summary>إيه نوع المشكلات اللي ينفع أنشرها؟</summary><div class="help-faq__answer">أعطال الهاردوير على مستوى البوردة والدوائر، مثل الباور والشحن والإضاءة والصوت والشبكة، مع وصف الأعراض والقياسات وخطوات الفحص. تجنّب نشر بيانات شخصية أو معلومات تخص العميل.</div></details>
            <details><summary>هل كل أدوات الموقع متاحة حاليًا؟</summary><div class="help-faq__answer">الموقع ما زال بيتطور؛ بعض الصفحات والخصائص قد تكون غير مكتملة أو غير متاحة. هنضيف الوظائف تدريجيًا، وأي عنصر غير متاح حاليًا لا يعني وجود مشكلة في حسابك بالضرورة.</div></details>
            <details><summary>إزاي أساعد الفنيين التانيين؟</summary><div class="help-faq__answer">قدّم خطوات فحص واضحة ومرتبة، واذكر القياسات الفعلية بدل التخمين، واشرح سبب اقتراحك. ولو وصلت لحل ناجح، شارك النتيجة النهائية.</div></details>
            <details><summary>هل أقدر أشارك كلمة المرور أو بيانات الحساب مع الدعم؟</summary><div class="help-faq__answer">لا. ما تشاركش كلمة المرور أو رموز التحقق مع أي شخص. عند الإبلاغ عن مشكلة، اشرح الخطوات اللي أدت لها من غير إرسال بيانات سرية.</div></details>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-contact-title">
        <div class="help-contact">
            <div>
                <h2 class="help-section__title" id="help-contact-title">لسه محتاج مساعدة؟</h2>
                <p class="help-contact__text">لو واجهت مشكلة مستمرة، جهّز وصفًا مختصرًا للمشكلة، والخطوات اللي عملتها، واسم الصفحة أو رسالة الخطأ الظاهرة. قنوات التواصل المباشر هتتضاف لما تكون جاهزة.</p>
            </div>
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/">العودة للرئيسية</a>
        </div>
    </section>
</div>
