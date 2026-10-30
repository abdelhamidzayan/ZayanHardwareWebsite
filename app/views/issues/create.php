<?php
$draftPayload = is_array($draft['payload'] ?? null) ? $draft['payload'] : [];
$contentType = (string) ($draftPayload['content_type'] ?? 'technical_post');
$detailsMode = (string) ($draftPayload['details_mode'] ?? 'text');
$selectedManufacturer = (string) ($draftPayload['manufacturer_id'] ?? '');
$selectedModel = (string) ($draftPayload['device_model_id'] ?? '');
$selectedModelLabel = (string) ($draftPayload['device_model_label'] ?? '');
$steps = is_array($draftPayload['steps'] ?? null) ? $draftPayload['steps'] : [];
$mediaForView = [];
foreach (($draft['media'] ?? []) as $mediaItem) {
    $uuid = (string) ($mediaItem['media_uuid'] ?? '');
    $mediaForView[] = [
        'media_uuid' => $uuid,
        'media_kind' => (string) ($mediaItem['media_kind'] ?? ''),
        'mime_type' => (string) ($mediaItem['mime_type'] ?? ''),
        'file_size' => (int) ($mediaItem['file_size'] ?? 0),
        'sort_order' => (int) ($mediaItem['sort_order'] ?? 0),
        'duration_ms' => $mediaItem['duration_ms'] ?? null,
        'editor_state' => $mediaItem['editor_state'] ?? null,
        'url' => $baseUrl . '/drafts/' . rawurlencode((string) ($draft['id'] ?? '')) . '/media/' . rawurlencode($uuid),
    ];
}
$initialState = [
    'id' => $draft['id'] ?? '',
    'revision' => (int) ($draft['revision'] ?? 0),
    'payload' => $draftPayload,
    'media' => $mediaForView,
];
$initialJson = json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
?>
<div class="page-head content-create-head">
    <a class="issue-back" href="<?= $e($baseUrl) ?>/feed">← الرجوع للمنشورات</a>
    <div>
        <h1 class="page-head__title"><?= !empty($draft) ? 'استكمال المسودة' : 'إنشاء منشور' ?></h1>
        <p class="muted">شارك معلومة فنية واضحة تساعد فنيين الورش.</p>
    </div>
</div>

<div class="container content-create">
    <form method="post" action="<?= $e($baseUrl) ?>/issues/create" class="card issue-form post-composer"
        data-post-composer
        data-save-url="<?= $e($baseUrl) ?>/drafts/save"
        data-media-base="<?= $e($baseUrl) ?>/drafts"
        data-models-url="<?= $e($baseUrl) ?>/devices/models"
        data-suggest-url="<?= $e($baseUrl) ?>/devices/suggestions"
        data-initial="<?= $e($initialJson) ?>">
        <?= $csrf() ?>
        <input type="hidden" name="draft_id" data-draft-id value="<?= $e((string) ($draft['id'] ?? '')) ?>">
        <input type="hidden" name="revision" data-draft-revision value="<?= (int) ($draft['revision'] ?? 0) ?>">

        <div class="post-composer__save-state" data-save-state role="status" aria-live="polite">
            <span class="post-composer__save-dot"></span>
            <span data-save-label>المسودة هتتحفظ تلقائيًا</span>
        </div>

        <fieldset class="post-composer__types">
            <legend class="field__label">نوع المنشور</legend>
            <label class="post-composer__type <?= $contentType === 'technical_post' ? 'is-selected' : '' ?>">
                <input type="radio" name="content_type" value="technical_post" <?= $contentType === 'technical_post' ? 'checked' : '' ?>>
                <span class="post-composer__type-icon">⚙</span>
                <span><strong>معلومة فنية</strong><small>معلومة أو عطل أو نقاش فني.</small></span>
            </label>
            <label class="post-composer__type <?= $contentType === 'help_request' ? 'is-selected' : '' ?>">
                <input type="radio" name="content_type" value="help_request" <?= $contentType === 'help_request' ? 'checked' : '' ?>>
                <span class="post-composer__type-icon">؟</span>
                <span><strong>طلب مساعدة</strong><small>حالة تحتاج مساعدة وتشخيص الفنيين.</small></span>
            </label>
        </fieldset>

        <section class="post-composer__section">
            <div class="post-composer__section-heading">
                <h2>الجهاز</h2>
                <span class="muted">الشركة والموديل</span>
            </div>
            <div class="post-composer__device-grid">
                <label class="field">
                    <span class="field__label">الشركة المصنعة</span>
                    <span class="post-composer__manufacturer-wrap">
                        <span class="post-composer__manufacturer-mark" data-manufacturer-mark>؟</span>
                        <select class="field__input" name="manufacturer_id" data-manufacturer required>
                            <option value="">اختار الشركة</option>
                            <?php foreach (($manufacturers ?? []) as $manufacturer) : ?>
                                <option value="<?= (int) $manufacturer['id'] ?>" data-slug="<?= $e($manufacturer['slug']) ?>"
                                    <?= (string) $manufacturer['id'] === $selectedManufacturer ? 'selected' : '' ?>>
                                    <?= $e($manufacturer['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>
                <div class="field post-composer__model-field">
                    <label class="field__label" for="post-device-model">موديل الجهاز</label>
                    <input id="post-device-model" class="field__input" type="search" autocomplete="off"
                        placeholder="اكتب اسم الجهاز أو رقم الموديل مثل X605"
                        value="<?= $e($selectedModelLabel) ?>" data-model-query required>
                    <input type="hidden" name="device_model_id" data-model-id value="<?= $e($selectedModel) ?>">
                    <div class="post-composer__model-results" data-model-results hidden></div>
                    <small class="muted">تقدر تبحث باسم الجهاز التجاري أو برقم الموديل مباشرة.</small>
                    <button class="text-action text-action--primary" type="button" data-open-model-suggest hidden>مش لاقي الموديل؟ اقترح إضافته</button>
                </div>
            </div>
            <div class="field post-composer__category" data-help-category <?= $contentType !== 'help_request' ? 'hidden' : '' ?>>
                <label class="field__label" for="post-category">التخصص الفني</label>
                <select class="field__input" name="category" id="post-category" data-category>
                    <option value="">اختار التخصص</option>
                    <?php foreach (['power' => 'الباور', 'charging' => 'الشحن', 'network' => 'الشبكات', 'display' => 'الشاشة والداتا والتاتش', 'audio' => 'الصوت والميكروفون', 'wifi_bluetooth' => 'الواي فاي والبلوتوث', 'camera' => 'الكاميرا', 'schematics' => 'المخططات وBoardview', 'cpu' => 'المعالج واللحام الدقيق', 'short_circuit' => 'شورت / قفلة'] as $value => $label) : ?>
                        <option value="<?= $e($value) ?>" <?= ($draftPayload['category'] ?? '') === $value ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <label class="field post-composer__title-field">
            <span class="field__label">عنوان المنشور</span>
            <input class="field__input post-composer__title" name="title" minlength="5" maxlength="180" required
                value="<?= $e((string) ($draftPayload['title'] ?? '')) ?>"
                placeholder="اكتب عنوانًا واضحًا يعبّر عن المشكلة أو المعلومة">
            <small class="muted"><span data-title-count>0</span> / 180</small>
        </label>

        <section class="post-composer__section">
            <div class="post-composer__section-heading">
                <h2>تفاصيل المنشور</h2>
                <span class="muted">اختار طريقة واحدة</span>
            </div>
            <div class="post-composer__mode-switch" role="radiogroup" aria-label="طريقة إضافة التفاصيل">
                <label class="post-composer__mode <?= $detailsMode === 'text' ? 'is-selected' : '' ?>">
                    <input type="radio" name="details_mode" value="text" <?= $detailsMode === 'text' ? 'checked' : '' ?>>
                    <span class="post-composer__mode-icon">✎</span><strong>كتابة التفاصيل</strong>
                </label>
                <label class="post-composer__mode <?= $detailsMode === 'audio' ? 'is-selected' : '' ?>">
                    <input type="radio" name="details_mode" value="audio" <?= $detailsMode === 'audio' ? 'checked' : '' ?>>
                    <span class="post-composer__mode-icon">♫</span><strong>تسجيل صوتي</strong>
                </label>
            </div>

            <div class="post-composer__text-mode" data-text-mode <?= $detailsMode !== 'text' ? 'hidden' : '' ?>>
                <div class="post-composer__format-tools" role="toolbar" aria-label="تنسيق النص">
                    <button type="button" data-format="bold" aria-label="خط عريض"><strong>B</strong></button>
                    <button type="button" data-format="heading" aria-label="عنوان فرعي">H</button>
                    <button type="button" data-format="numbered" aria-label="قائمة مرقمة">1.</button>
                    <button type="button" data-format="bulleted" aria-label="قائمة نقطية">•</button>
                    <button type="button" data-format="divider" aria-label="فاصل">―</button>
                </div>
                <label class="field">
                    <span class="sr-only">تفاصيل المنشور</span>
                    <textarea class="field__input post-composer__details" name="description" data-details rows="7" maxlength="10000"
                        placeholder="اشرح المشكلة أو المعلومة الفنية، واذكر القياسات والنتيجة..."><?= $e((string) ($draftPayload['description'] ?? '')) ?></textarea>
                </label>
            </div>

            <div class="post-composer__audio-mode" data-audio-mode <?= $detailsMode !== 'audio' ? 'hidden' : '' ?>>
                <div class="audio-recorder" data-audio-recorder>
                    <canvas class="audio-recorder__wave" data-audio-wave width="640" height="120" aria-label="الموجة الصوتية"></canvas>
                    <div class="audio-recorder__time">
                        <strong data-audio-time>00:00</strong>
                        <span data-audio-status>جاهز للتسجيل</span>
                    </div>
                    <div class="audio-recorder__controls">
                        <button class="btn btn--primary" type="button" data-audio-start><span>●</span> ابدأ التسجيل</button>
                        <button class="btn btn--secondary" type="button" data-audio-pause hidden>إيقاف مؤقت</button>
                        <button class="btn btn--secondary" type="button" data-audio-resume hidden>استكمال</button>
                        <button class="btn btn--secondary" type="button" data-audio-stop hidden>إنهاء التسجيل</button>
                        <button class="btn btn--ghost" type="button" data-audio-delete hidden>حذف وإعادة التسجيل</button>
                    </div>
                    <audio data-audio-preview controls hidden></audio>
                    <div class="audio-recorder__trim" data-audio-trim hidden>
                        <label>بداية القص <input type="range" min="0" max="100" value="0" data-trim-start></label>
                        <label>نهاية القص <input type="range" min="0" max="100" value="100" data-trim-end></label>
                        <small class="muted">حدد الجزء المطلوب من التسجيل قبل النشر.</small>
                    </div>
                    <p class="muted" data-audio-error hidden></p>
                </div>
            </div>
        </section>

        <section class="post-composer__steps">
            <div class="post-composer__section-heading">
                <h2>الخطوات والفحوصات المجربة</h2>
                <span class="muted">اختياري</span>
            </div>
            <div class="post-composer__step-list" data-step-list>
                <?php foreach ($steps as $index => $step) : ?>
                    <?php if (!is_string($step)) continue; ?>
                    <div class="post-composer__step-row" data-step-row>
                        <span class="post-composer__step-number"><?= (int) $index + 1 ?></span>
                        <textarea rows="1" maxlength="2000" data-step-input placeholder="اكتب الفحص أو التجربة..."><?= $e($step) ?></textarea>
                        <button type="button" data-remove-step aria-label="حذف الخطوة">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="text-action text-action--primary post-composer__add-step" type="button" data-add-step>
                <span>＋</span> إضافة
            </button>
        </section>

        <section class="post-composer__attachments">
            <div class="post-composer__section-heading">
                <h2>المرفقات</h2>
                <span class="muted">صور أو مخططات أو فيديو</span>
            </div>
            <label class="post-composer__paperclip" for="post-media-input">
                <span aria-hidden="true">📎</span>
                <span>إضافة صور أو فيديو</span>
                <small>حتى 8 ملفات</small>
            </label>
            <input id="post-media-input" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" multiple data-media-input>
            <div class="post-composer__media-grid" data-media-list>
                <?php foreach ($mediaForView as $mediaItem) : ?>
                    <?php if ($mediaItem['media_kind'] === 'audio') continue; ?>
                    <article class="post-composer__media-item" data-media-item data-media-uuid="<?= $e($mediaItem['media_uuid']) ?>" data-kind="<?= $e($mediaItem['media_kind']) ?>">
                        <?php if ($mediaItem['media_kind'] === 'image') : ?>
                            <img src="<?= $e($mediaItem['url']) ?>" alt="مرفق فني" loading="lazy">
                            <button type="button" data-edit-image>تعديل الصورة</button>
                        <?php else : ?>
                            <video src="<?= $e($mediaItem['url']) ?>" muted playsinline preload="metadata"></video>
                            <span class="post-composer__video-label">فيديو</span>
                        <?php endif; ?>
                        <button type="button" data-remove-media aria-label="حذف المرفق">×</button>
                    </article>
                <?php endforeach; ?>
            </div>
            <small class="muted">الصور تظهر كمصغرات، ويمكن فتحها وتعديلها قبل النشر.</small>
        </section>

        <div class="post-composer__footer">
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/feed">إلغاء</a>
            <button class="btn btn--primary" type="submit" data-publish-button>نشر المنشور</button>
        </div>
    </form>
</div>

<dialog class="post-composer__suggest-dialog" data-model-suggest-dialog>
    <form method="post" data-model-suggest-form>
        <div class="post-composer__dialog-head"><h2>اقتراح موديل جديد</h2><button type="button" data-close-suggest aria-label="إغلاق">×</button></div>
        <p class="muted">هنراجع بيانات الموديل قبل إضافته لقائمة الأجهزة.</p>
        <label class="field"><span class="field__label">اسم الجهاز التجاري</span><input class="field__input" name="commercial_name" maxlength="140" required placeholder="مثال: Infinix Smart 5"></label>
        <label class="field"><span class="field__label">رقم الموديل</span><input class="field__input tech" name="model_code" maxlength="120" placeholder="مثال: X605"></label>
        <label class="field"><span class="field__label">أسماء أو أرقام بديلة (اختياري)</span><input class="field__input" name="aliases" maxlength="500" placeholder="أسماء بحث إضافية مفصولة بفواصل"></label>
        <label class="field"><span class="field__label">ملاحظة للأدمن (اختياري)</span><textarea class="field__input" name="note" maxlength="500" rows="2"></textarea></label>
        <p class="muted" data-suggest-message></p>
        <button class="btn btn--primary" type="submit">إرسال للمراجعة</button>
    </form>
</dialog>

<dialog class="image-editor-dialog" data-image-editor-dialog>
    <div class="image-editor" data-image-editor>
        <header class="image-editor__header">
            <button type="button" data-image-editor-cancel aria-label="إغلاق">×</button>
            <strong>تعديل الصورة</strong>
            <button class="image-editor__done" type="button" data-image-editor-save>تم</button>
        </header>
        <div class="image-editor__workspace">
            <canvas data-image-editor-canvas></canvas>
        </div>
        <div class="image-editor__toolbar" role="toolbar" aria-label="أدوات تعديل الصورة">
            <button type="button" data-image-tool="crop">قص</button>
            <button type="button" data-image-tool="select">تحريك</button>
            <button type="button" data-image-tool="text">كتابة</button>
            <button type="button" data-image-tool="pen">قلم</button>
            <button type="button" data-image-tool="arrow">سهم</button>
            <button type="button" data-image-tool="circle">دائرة</button>
            <button type="button" data-image-tool="rect">مربع</button>
            <button type="button" data-image-tool="line">خط</button>
            <button type="button" data-image-tool="eraser">مسح</button>
        </div>
        <div class="image-editor__properties">
            <label>اللون <input type="color" data-image-color value="#2563eb"></label>
            <label>الحجم <input type="range" min="1" max="24" value="4" data-image-size></label>
            <button type="button" data-image-undo aria-label="تراجع">↶</button>
            <button type="button" data-image-redo aria-label="إعادة">↷</button>
            <button type="button" data-image-clear>مسح التعديلات</button>
        </div>
        <div class="image-editor__text-entry" data-image-text-entry hidden>
            <input type="text" maxlength="120" placeholder="اكتب على الصورة..." data-image-text>
            <button type="button" data-image-add-text>إضافة النص</button>
        </div>
    </div>
</dialog>

<script src="<?= $e($baseUrl) ?>/js/post-composer.js" defer></script>
<script src="<?= $e($baseUrl) ?>/js/image-editor.js" defer></script>
<script src="<?= $e($baseUrl) ?>/js/audio-recorder.js" defer></script>
