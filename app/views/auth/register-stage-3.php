<?php
$errors = $errors ?? [];
$old = $old ?? [];
$val = fn (string $key): string => $e($old[$key] ?? '');
?>
<style>
.registration-progress{display:flex;align-items:flex-start;gap:0;margin:8px 0 28px;direction:rtl}
.registration-progress__step{flex:1;position:relative;text-align:center;color:var(--muted,#8b929b);font-size:12px}
.registration-progress__step:not(:last-child):before{content:"";position:absolute;top:15px;right:calc(50% + 21px);left:calc(-50% + 21px);height:2px;background:var(--border,#30363d)}
.registration-progress__number{display:flex;width:32px;height:32px;border-radius:50%;align-items:center;justify-content:center;margin:0 auto 8px;border:1px solid var(--border,#30363d);background:var(--surface,#15191e);font-weight:700}
.registration-progress__step.is-active{color:var(--text,#f5f5f5);font-weight:700}
.registration-progress__step.is-active .registration-progress__number{background:#c49a42;border-color:#c49a42;color:#101010}
.registration-progress__step.is-done .registration-progress__number{border-color:#c49a42;color:#c49a42}
.avatar-placeholder{display:flex;flex-direction:column;align-items:center;gap:12px;padding:24px 16px;border:1px dashed var(--border,#30363d);border-radius:14px;text-align:center}
.avatar-placeholder__circle{display:grid;place-items:center;width:82px;height:82px;border-radius:50%;background:var(--surface,#15191e);border:1px solid var(--border,#30363d);color:#c49a42}
.avatar-placeholder__circle svg{width:32px;height:32px;stroke:currentColor;fill:none;stroke-width:1.6}
.avatar-placeholder__button{border:1px solid var(--border,#30363d);border-radius:9px;padding:9px 14px;background:var(--surface,#15191e);color:var(--muted,#8b929b);cursor:not-allowed}
</style>
<section class="auth">
    <div class="card auth__card auth__card--wide">
        <div class="registration-progress" aria-label="مراحل إنشاء الحساب">
            <div class="registration-progress__step is-done"><span class="registration-progress__number">✓</span><span>الحساب</span></div>
            <div class="registration-progress__step is-done"><span class="registration-progress__number">✓</span><span>التخصص</span></div>
            <div class="registration-progress__step is-active" aria-current="step"><span class="registration-progress__number">3</span><span>الملف الشخصي</span></div>
        </div>
        <h1 class="auth__title">خلّي ملفك الشخصي جاهز</h1>
        <p class="auth__sub">الخطوة دي اختيارية. تقدر تضيف نبذة واسم الورشة دلوقتي، أو تتخطاها وترجع لها بعدين.</p>

        <div class="avatar-placeholder">
            <div class="avatar-placeholder__circle" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/><path d="M18 10h.01"/></svg>
            </div>
            <strong>صورة الملف الشخصي</strong>
            <span class="auth__sub">إضافة الصورة هتتوفر قريبًا — الجزء ده واجهة مبدئية فقط.</span>
            <button class="avatar-placeholder__button" type="button" disabled>إضافة صورة قريبًا</button>
        </div>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/register/stage/3">
            <?= $csrf() ?>
            <div class="field">
                <label class="field__label" for="workshop">اسم الورشة <span class="field__opt">اختياري</span></label>
                <input class="field__input" id="workshop" name="workshop" type="text" maxlength="80"
                       value="<?= $val('workshop') ?>" placeholder="مثال: Zayan Mobile Lab">
                <p class="field__error" aria-live="polite"><?= $e($errors['workshop'] ?? '') ?></p>
            </div>
            <div class="field">
                <label class="field__label" for="biography">نبذة عنك <span class="field__opt">اختياري</span></label>
                <textarea class="field__input" id="biography" name="biography" rows="4" maxlength="500"
                          placeholder="اكتب نبذة قصيرة عن خبرتك ومجالات شغلك..."><?= $val('biography') ?></textarea>
                <p class="field__hint">حد أقصى 500 حرف.</p>
                <p class="field__error" aria-live="polite"><?= $e($errors['biography'] ?? '') ?></p>
            </div>
            <button class="btn btn--primary btn--block" type="submit">حفظ وإنهاء التسجيل</button>
        </form>
        <form method="post" action="<?= $e($baseUrl) ?>/register/stage/3/skip" class="auth__form">
            <?= $csrf() ?>
            <button class="btn btn--block" type="submit">تخطي وإنهاء التسجيل</button>
        </form>
    </div>
</section>
