<div class="page-head">
    <h1 class="page-head__title">إدارة اختبارات المهارات</h1>
    <p class="muted">الاختبار الجديد يُحفظ كمسودة غير متاحة للأعضاء حتى تفعيله من قاعدة البيانات أو أداة إدارة معتمدة.</p>
</div>
<div class="container">
    <section class="card" style="padding:1rem;margin-bottom:1rem">
        <h2>إنشاء اختبار جديد</h2>
        <form method="post" action="<?= $e($baseUrl) ?>/admin/skill-tests" class="issue-form">
            <?= $csrf() ?>
            <label class="field"><span class="field__label">عنوان الاختبار</span><input class="field__input" name="title" maxlength="160" required></label>
            <label class="field"><span class="field__label">مجال المهارة</span><select class="field__input" name="skill_domain_id" required><?php foreach ($domains as $domain) : ?><option value="<?= (int) $domain['id'] ?>"><?= $e($domain['name_ar']) ?> (<?= $e($domain['slug']) ?>)</option><?php endforeach; ?></select></label>
            <label class="field"><span class="field__label">الإصدار</span><input class="field__input" name="version" maxlength="30" value="1" required></label>
            <label class="field"><span class="field__label">درجة النجاح (1–100)</span><input class="field__input" type="number" name="pass_score" min="1" max="100" value="70" required></label>
            <label class="field"><span class="field__label">الحد الأقصى للمحاولات (1–10)</span><input class="field__input" type="number" name="max_attempts" min="1" max="10" value="3" required></label>
            <label class="field"><span class="field__label">فترة الانتظار بالدقائق (1–10080)</span><input class="field__input" type="number" name="cooldown_minutes" min="1" max="10080" value="1440" required></label>
            <label class="field"><span class="field__label">الأسئلة بصيغة JSON</span><textarea class="field__input" name="questions_json" rows="12" required spellcheck="false">[
  {
    "question": "ما أول خطوة عند وجود قصر على خط تغذية؟",
    "options": {
      "a": "رفع المعالج مباشرة",
      "b": "قياس المقاومة وتحديد الخط",
      "c": "تغيير البطارية"
    },
    "correct": "b"
  }
]</textarea></label>
            <p class="muted">كل سؤال يحتاج question و options و correct. لن تُرسل إجابات التصحيح الصحيحة إلى المتدرّب.</p>
            <button class="btn btn--primary" type="submit">حفظ كمسودة</button>
        </form>
    </section>

    <section class="card" style="padding:1rem">
        <h2>الاختبارات الموجودة</h2>
        <?php if (!$tests) : ?><p class="muted">لا توجد اختبارات بعد.</p><?php else : ?>
            <?php foreach ($tests as $test) : ?>
                <div style="padding:.75rem 0;border-bottom:1px solid var(--border,#333)">
                    <strong><?= $e($test['title']) ?></strong>
                    <p class="muted"><?= $e($test['skill_name']) ?> · إصدار <?= $e($test['version']) ?> · النجاح <?= $e((string) $test['pass_score']) ?>% · المحاولات <?= (int) $test['max_attempts'] ?> · انتظار <?= (int) $test['cooldown_minutes'] ?> دقيقة</p>
                    <span class="badge"><?= (int) $test['is_active'] === 1 ? 'مفعّل' : 'مسودة' ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
