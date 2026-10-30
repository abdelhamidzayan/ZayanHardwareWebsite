<div class="page-head">
    <div>
        <h1 class="page-head__title">تحديث الموقع</h1>
        <p class="muted">اسحب آخر إصدار من GitHub وطبّقه على نسخة الموقع المحلية.</p>
    </div>
    <a class="btn btn--ghost" href="<?= $e($baseUrl) ?>/admin">إدارة الموقع</a>
</div>

<div class="container admin-update">
    <section class="card" style="padding: 24px; margin-bottom: 18px;">
        <div style="display:flex; align-items:flex-start; gap:14px;">
            <span aria-hidden="true" style="color:#2563eb; font-size:24px;">↻</span>
            <div>
                <h2 style="margin:0 0 8px;">تحديث من GitHub</h2>
                <p class="muted" style="margin:0;">
                    سيتم فحص التغييرات المحلية أولًا، ثم جلب آخر تحديث من فرع main وتطبيقه بطريقة Fast-forward فقط.
                </p>
            </div>
        </div>

        <div style="margin-top:18px; padding:14px; border:1px solid var(--border, #dbe3ee); border-radius:12px;">
            <strong>حماية الملفات المحلية</strong>
            <p class="muted" style="margin:6px 0 0;">
                لن يتم حذف أو استبدال الملفات المحلية غير المتتبعة. إذا وُجدت تعديلات غير مسموح بها، ستتوقف العملية بدلًا من المخاطرة ببياناتك.
            </p>
        </div>

        <form method="post" action="<?= $e($baseUrl) ?>/admin/update" style="margin-top:20px;"
              onsubmit="return confirm('هل تريد بدء تحديث الموقع من GitHub الآن؟');">
            <?= $csrf() ?>
            <button class="btn btn--primary" type="submit">
                <span aria-hidden="true">↻</span>
                تحديث الموقع الآن
            </button>
        </form>
    </section>

    <?php if ($updateResult !== null) : ?>
        <section class="card" style="padding:24px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <h2 style="margin:0;">نتيجة آخر عملية</h2>
                <span class="tag <?= !empty($updateResult['success']) ? 'tech' : '' ?>">
                    <?= !empty($updateResult['success']) ? 'اكتمل التحديث' : 'تعذر التحديث' ?>
                </span>
            </div>
            <?php if (!empty($updateResult['finishedAt'])) : ?>
                <p class="muted" style="margin:10px 0;">وقت الانتهاء: <?= $e((string) $updateResult['finishedAt']) ?></p>
            <?php endif; ?>
            <pre style="direction:ltr; text-align:left; white-space:pre-wrap; overflow-wrap:anywhere; overflow:auto; max-height:420px; padding:16px; border-radius:12px; background:var(--surface-muted, #f3f6fa); border:1px solid var(--border, #dbe3ee);"><?= $e((string) ($updateResult['output'] ?? '')) ?></pre>
        </section>
    <?php endif; ?>
</div>
