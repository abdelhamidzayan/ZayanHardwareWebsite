<?php
$contentType = (string) ($_POST['content_type'] ?? 'help_request');
?>
<div class="page-head">
    <a class="issue-back" href="<?= $e($baseUrl) ?>/feed">← الرجوع للمنشورات</a>
    <h1 class="page-head__title">إنشاء منشور</h1>
</div>

<div class="container content-create">
    <form method="post" action="<?= $e($baseUrl) ?>/issues/create" enctype="multipart/form-data" class="card issue-form content-create__form">
        <?= $csrf() ?>
        <fieldset class="content-create__types">
            <legend class="field__label">هتنشر إيه؟</legend>
            <label class="content-create__type">
                <input type="radio" name="content_type" value="help_request" <?= $contentType === 'help_request' ? 'checked' : '' ?>>
                <span><strong>طلب مساعدة</strong><small>عطل محتاج تشخيص ومساعدة فنيين.</small></span>
            </label>
            <label class="content-create__type">
                <input type="radio" name="content_type" value="technical_post" <?= $contentType === 'technical_post' ? 'checked' : '' ?>>
                <span><strong>معلومة فنية</strong><small>عطل واجهك واتحل أو معلومة تفيد الورشة.</small></span>
            </label>
            <label class="content-create__type">
                <input type="radio" name="content_type" value="discussion" <?= $contentType === 'discussion' ? 'checked' : '' ?>>
                <span><strong>نقاش عام</strong><small>سؤال أو نقاش متعلق بهاردوير المحمول والمخططات.</small></span>
            </label>
        </fieldset>

        <label class="field">
            <span class="field__label">عنوان المنشور</span>
            <input class="field__input" name="title" minlength="5" maxlength="180" required value="<?= $e((string) ($_POST['title'] ?? '')) ?>" placeholder="اكتب عنوانًا واضحًا ومحددًا">
        </label>

        <div class="content-create__help-fields" data-help-fields>
            <label class="field">
                <span class="field__label">موديل الجهاز</span>
                <input class="field__input" name="device_model" maxlength="120" value="<?= $e((string) ($_POST['device_model'] ?? '')) ?>" placeholder="مثال: iPhone 13 Pro">
            </label>
            <label class="field">
                <span class="field__label">نوع العطل</span>
                <select class="field__input" name="category">
                    <option value="">اختار التخصص</option>
                    <?php foreach (['power' => 'الباور', 'charging' => 'الشحن', 'network' => 'الشبكات', 'display' => 'الشاشة والداتا والتاتش', 'audio' => 'الصوت والميكروفون', 'wifi_bluetooth' => 'الواي فاي والبلوتوث', 'camera' => 'الكاميرا', 'schematics' => 'المخططات وBoardview', 'cpu' => 'المعالج واللحام الدقيق', 'short_circuit' => 'شورت / قفلة'] as $value => $label) : ?>
                        <option value="<?= $e($value) ?>" <?= ($_POST['category'] ?? '') === $value ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span class="field__label">القياسات والاختبارات</span>
                <textarea class="field__input" name="measurements" minlength="3" maxlength="5000" rows="4" placeholder="اكتب القراءات، الفولتات، المقاومة، أو الاختبارات اللي جربتها"><?= $e((string) ($_POST['measurements'] ?? '')) ?></textarea>
            </label>
        </div>

        <?php
        $editorFieldName = 'description';
        $editorLabel = 'التفاصيل';
        $editorPlaceholder = 'اكتب التفاصيل بوضوح، واذكر اللي جربته والنتيجة اللي ظهرت.';
        $editorValue = (string) ($_POST['description'] ?? '');
        $editorMaxLength = 10000;
        $editorRows = 7;
        $editorRequired = true;
        require __DIR__ . '/../partials/content-editor.php';
        ?>

        <div class="content-create__actions">
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/feed">إلغاء</a>
            <button class="btn btn--primary" type="submit">نشر المنشور</button>
        </div>
    </form>
</div>
<script src="<?= $e($baseUrl) ?>/js/content-create.js" defer></script>
