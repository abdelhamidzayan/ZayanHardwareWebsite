<?php
$editorFieldName = $editorFieldName ?? 'body';
$editorLabel = $editorLabel ?? 'التفاصيل';
$editorPlaceholder = $editorPlaceholder ?? 'اكتب التفاصيل بوضوح...';
$editorValue = (string) ($editorValue ?? '');
$editorMaxLength = (int) ($editorMaxLength ?? 10000);
$editorRows = (int) ($editorRows ?? 5);
$editorRequired = (bool) ($editorRequired ?? true);
$editorExistingMedia = [];
foreach (($attachments ?? []) as $attachment) {
    $mediaId = (int) ($attachment['id'] ?? 0);
    $mediaType = (string) ($attachment['mime_type'] ?? '');
    if ($mediaId > 0 && (str_starts_with($mediaType, 'image/') || $mediaType === 'application/pdf')) {
        $editorExistingMedia[(string) $mediaId] = [
            'url' => $baseUrl . '/attachments/' . $mediaId,
            'type' => $mediaType === 'application/pdf' ? 'pdf' : 'image',
        ];
    }
}
$editorExistingMediaJson = json_encode(
    $editorExistingMedia,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?: '{}';
?>
<div class="content-editor" data-content-editor data-existing-media="<?= $e($editorExistingMediaJson) ?>">
    <label class="field">
        <span class="field__label"><?= $e($editorLabel) ?></span>
        <textarea class="field__input" name="<?= $e($editorFieldName) ?>" data-content-textarea minlength="2" maxlength="<?= $editorMaxLength ?>" rows="<?= $editorRows ?>" placeholder="<?= $e($editorPlaceholder) ?>" <?= $editorRequired ? 'required' : '' ?>><?= $e($editorValue) ?></textarea>
        <small class="muted">لإدراج صورة داخل النص: اختارها، ثم اضغط «إدراج عند المؤشر». موضع العلامة داخل النص هو موضع الصورة عند العرض.</small>
    </label>
    <label class="field content-editor__file-field">
        <span class="field__label">إضافة صور أو مخططات</span>
        <input class="field__input" type="file" data-content-files name="attachments[]" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple>
        <small class="muted">حتى 8 ملفات، بحد أقصى 8 ميجابايت لكل ملف. الصور وPDF فقط.</small>
    </label>
    <div class="content-editor__previews" data-content-previews></div>
    <section class="content-editor__live-preview" data-content-live-preview hidden aria-label="معاينة النص مع الصور">
        <strong>معاينة النص مع الصور</strong>
        <div data-content-live-preview-body></div>
    </section>
    <div data-content-token-inputs></div>
</div>
