<div class="page-head">
    <div>
        <h1 class="page-head__title">مراجعة موديلات الأجهزة</h1>
        <p class="muted">طلبات إضافة الموديلات لا تظهر للفنيين إلا بعد اعتمادها.</p>
    </div>
</div>
<div class="container admin-device-models">
    <?php if (empty($suggestions)) : ?>
        <section class="card empty-state"><h2>لا توجد اقتراحات معلقة</h2><p class="empty-state__text">أي موديل جديد يقترحه فني هيظهر هنا للمراجعة.</p></section>
    <?php else : ?>
        <?php foreach ($suggestions as $item) : ?>
            <article class="card admin-device-models__item">
                <div>
                    <div class="admin-device-models__meta">
                        <span class="tag"><?= $e($item['manufacturer_name']) ?></span>
                        <span class="muted">بواسطة <?= $e($item['submitter_name']) ?> · <?= $e($item['created_at']) ?></span>
                    </div>
                    <h2><?= $e($item['commercial_name']) ?></h2>
                    <?php if (!empty($item['model_code'])) : ?><p><strong>Model:</strong> <span class="tech"><?= $e($item['model_code']) ?></span></p><?php endif; ?>
                    <?php if (!empty($item['aliases'])) : ?><p><strong>أسماء بديلة:</strong> <?= $e($item['aliases']) ?></p><?php endif; ?>
                    <?php if (!empty($item['note'])) : ?><p class="muted"><?= $e($item['note']) ?></p><?php endif; ?>
                </div>
                <div class="admin-device-models__actions">
                    <form method="post" action="<?= $e($baseUrl) ?>/admin/device-model-suggestions/<?= (int) $item['id'] ?>">
                        <?= $csrf() ?><button class="btn btn--primary" name="decision" value="approve" type="submit">اعتماد الموديل</button>
                    </form>
                    <form method="post" action="<?= $e($baseUrl) ?>/admin/device-model-suggestions/<?= (int) $item['id'] ?>">
                        <?= $csrf() ?><button class="btn btn--secondary" name="decision" value="reject" type="submit">رفض</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
