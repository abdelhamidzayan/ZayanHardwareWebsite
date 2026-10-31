<?php
$errors = $errors ?? [];
$old = $old ?? [];
$countries = $countries ?? require dirname(__DIR__, 3) . '/config/countries.php';
$activeStep = (int) ($activeStep ?? 1);
$val = static fn (string $key): string => $e(is_string($old[$key] ?? null) ? $old[$key] : '');
$err = static fn (string $key): string => $e(is_string($errors[$key] ?? null) ? $errors[$key] : '');
$contactType = in_array(($old['contact_type'] ?? ''), ['phone', 'email'], true)
    ? $old['contact_type']
    : (((string) ($old['email_local'] ?? '') !== '' || (string) ($old['email_domain'] ?? '') !== '') ? 'email' : 'phone');
$selectedCountry = isset($countries[$old['country'] ?? 'EG']) ? (string) ($old['country'] ?? 'EG') : 'EG';
$emailLocal = (string) ($old['email_local'] ?? '');
$emailDomain = (string) ($old['email_domain'] ?? '');
if (str_contains($emailLocal, '@')) {
    $emailParts = explode('@', $emailLocal, 2);
    $emailLocal = $emailParts[0];
    if ($emailDomain === '') $emailDomain = $emailParts[1] ?? '';
}
$selectedFocuses = $old['device_focus'] ?? [];
if (!is_array($selectedFocuses)) $selectedFocuses = [$selectedFocuses];
$selectedFocuses = array_values(array_intersect(['iphone', 'android'], array_map('strval', $selectedFocuses)));
$selectedLevel = (string) ($old['tech_level'] ?? 'fast');
$selectedExperience = (string) ($old['board_experience'] ?? '');
?>
<section class="auth wizard">
    <div class="card auth__card wizard__card">
        <h1 class="auth__title">إنشاء حساب فنّي</h1>
        <p class="auth__sub">أنشئ حسابك وابدأ مشاركة خبرتك في هاردوير الهواتف.</p>

        <ol class="wizard__stepper" aria-label="مراحل إنشاء الحساب">
            <li class="wizard__stepper-item is-active" data-stepper="1"><span class="wizard__stepper-circle">1</span><span>الحساب</span></li>
            <li class="wizard__stepper-item" data-stepper="2"><span class="wizard__stepper-circle">2</span><span>تخصصك</span></li>
            <li class="wizard__stepper-item" data-stepper="3"><span class="wizard__stepper-circle">3</span><span>ملفك</span></li>
        </ol>

        <form class="auth__form wizard__form" method="post" action="<?= $e($baseUrl) ?>/register" id="registration-wizard" data-register-wizard data-initial-step="<?= $activeStep ?>" novalidate>
            <?= $csrf() ?>
            <input type="hidden" name="wizard_action" value="skip" data-profile-action>
            <input type="hidden" name="contact_type" value="<?= $e($contactType) ?>" data-contact-type>
            <input type="hidden" name="country" value="<?= $e($selectedCountry) ?>" data-country-value>

            <section class="wizard__step" data-step="1" aria-labelledby="wizard-account-title"<?= $activeStep === 1 ? '' : ' hidden' ?>>
                <h2 class="wizard__heading" id="wizard-account-title">بيانات حسابك</h2>
                <div class="field">
                    <label class="field__label" for="display_name">الاسم الظاهر</label>
                    <input class="field__input" id="display_name" name="display_name" type="text" value="<?= $val('display_name') ?>"
                           autocomplete="name" minlength="2" maxlength="60" data-wizard-required required>
                    <p class="field__error" data-error-for="display_name" aria-live="polite"><?= $err('display_name') ?></p>
                </div>

                <div class="field">
                    <label class="field__label" for="phone_number" data-contact-label><?= $contactType === 'phone' ? 'رقم الهاتف' : 'البريد الإلكتروني' ?></label>
                    <div class="wizard-contact" dir="ltr">
                        <button class="wizard-contact__switch" type="button" data-contact-switch aria-label="التبديل إلى البريد الإلكتروني" title="التبديل بين الهاتف والبريد">
                            <svg data-contact-icon viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/></svg>
                        </button>

                        <div class="wizard-contact__phone" data-phone-fields <?= $contactType === 'email' ? 'hidden' : '' ?>>
                            <button class="country-picker" type="button" data-country-toggle aria-haspopup="listbox" aria-expanded="false">
                                <span data-country-flag><?= $e($countries[$selectedCountry]['flag']) ?></span>
                                <span data-country-dial>+<?= $e($countries[$selectedCountry]['dial']) ?></span>
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                            </button>
                            <input id="phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel-national"
                                   placeholder="01012345678" value="<?= $val('phone_number') ?>" data-phone-input <?= $contactType === 'email' ? 'disabled' : 'required' ?>>
                            <div class="country-list" data-country-list role="listbox" aria-label="اختر الدولة" hidden>
                                <?php foreach ($countries as $iso => $country) : ?>
                                    <button type="button" class="country-list__item" role="option" aria-selected="<?= $iso === $selectedCountry ? 'true' : 'false' ?>"
                                            data-country="<?= $e($iso) ?>" data-dial="<?= $e($country['dial']) ?>" data-flag="<?= $e($country['flag']) ?>">
                                        <span class="country-list__flag"><?= $e($country['flag']) ?></span>
                                        <span class="country-list__name"><?= $e($country['name']) ?></span>
                                        <span class="country-list__iso"><?= $e($iso) ?></span>
                                        <span class="country-list__dial">+<?= $e($country['dial']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="wizard-contact__email" data-email-fields <?= $contactType !== 'email' ? 'hidden' : '' ?>>
                            <input id="email_local" name="email_local" type="text" inputmode="email" autocomplete="email" placeholder="name"
                                   value="<?= $e($emailLocal) ?>" aria-label="الجزء قبل علامة @ " data-email-local <?= $contactType === 'phone' ? 'disabled' : 'required' ?>>
                            <span class="wizard-contact__at" aria-hidden="true">@</span>
                            <input id="email_domain" name="email_domain" type="text" inputmode="email" autocomplete="off" placeholder="example.com"
                                   value="<?= $e($emailDomain) ?>" aria-label="نطاق البريد الإلكتروني" data-email-domain <?= $contactType === 'phone' ? 'disabled' : 'required' ?>>
                        </div>
                    </div>
                    <p class="field__hint" data-contact-hint><?= $contactType === 'phone' ? 'مثال: 01033771537 في مصر يُحفظ +201033771537.' : 'يمكنك لصق البريد الإلكتروني كاملًا في الخانة الأولى.' ?></p>
                    <p class="field__error" data-error-for="contact" aria-live="polite"><?= $err('contact') ?></p>
                </div>

                <div class="field">
                    <label class="field__label" for="password">كلمة المرور</label>
                    <div class="field__password">
                        <input class="field__input field__input--ltr" id="password" name="password" type="password" autocomplete="new-password"
                               minlength="10" maxlength="72" data-wizard-required data-password-input required>
                        <button class="field__toggle" type="button" data-password-toggle aria-label="إظهار كلمة المرور" aria-pressed="false">
                            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p class="field__hint">10 أحرف على الأقل.</p>
                    <p class="field__error" data-error-for="password" aria-live="polite"><?= $err('password') ?></p>
                </div>

                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="accept_terms" value="1" data-terms-acceptance required <?= ($old['accept_terms'] ?? '') === '1' ? 'checked' : '' ?>>
                        <span>أوافق على <a class="policy-text-link" href="<?= $e($baseUrl) ?>/policies" target="_blank" rel="noopener">قواعد المنصة وسياسة الخصوصية</a>.</span>
                    </label>
                    <p class="field__error" data-error-for="terms_acceptance" aria-live="polite"><?= $err('terms_acceptance') ?></p>
                </div>

                <div class="wizard__actions wizard__actions--end">
                    <button class="btn btn--primary" type="button" data-next>التالي</button>
                </div>
            </section>

            <section class="wizard__step" data-step="2" aria-labelledby="wizard-specialty-title"<?= $activeStep === 2 ? '' : ' hidden' ?>>
                <h2 class="wizard__heading" id="wizard-specialty-title">خصص حسابك الفني</h2>

                <div class="field">
                    <label class="field__label" for="username">اسم المستخدم</label>
                    <div class="wizard-username">
                        <span aria-hidden="true">@</span>
                        <input class="field__input field__input--ltr" id="username" name="username" type="text" autocomplete="username"
                               minlength="3" maxlength="20" pattern="[a-z0-9_]{3,20}" value="<?= $val('username') ?>"
                               data-username-input required>
                        <p class="field__status" data-username-status aria-live="polite"><?= $err('username') ?></p>
                    </div>
                    <div class="wizard-suggestions" data-username-suggestions aria-label="اقتراحات أسماء المستخدمين"></div>
                </div>

                <fieldset class="field wizard-choice">
                    <legend class="field__label">مجال تخصصك</legend>
                    <p class="field__hint">يمكنك اختيار مجال واحد أو المجالين معًا إذا كنت متخصصًا في الاثنين.</p>
                    <div class="wizard-choice__grid">
                        <label class="wizard-choice__card">
                            <input type="checkbox" name="device_focus[]" value="iphone" data-wizard-required <?= in_array('iphone', $selectedFocuses, true) ? 'checked' : '' ?>>
                            <span class="wizard-choice__logo wizard-choice__logo--apple" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M16.37 12.2c.02 2.2 1.93 2.93 1.95 2.94-.02.05-.3 1.05-1 2.08-.6.9-1.23 1.8-2.22 1.82-.97.02-1.28-.59-2.39-.59-1.1 0-1.45.57-2.36.61-.95.04-1.67-.98-2.28-1.88-1.25-1.82-2.2-5.15-.92-7.4.64-1.12 1.78-1.83 3.02-1.85.94-.02 1.82.66 2.39.66.56 0 1.62-.82 2.73-.7.46.02 1.76.18 2.6 1.42-.07.04-1.55.9-1.53 2.89ZM14.6 5.9c.5-.6.83-1.44.74-2.27-.72.03-1.59.48-2.1 1.08-.46.53-.86 1.39-.75 2.2.8.06 1.61-.4 2.11-1.01Z"/></svg>
                            </span>
                            <strong>iPhone</strong>
                            <span>أجهزة Apple</span>
                        </label>
                        <label class="wizard-choice__card">
                            <input type="checkbox" name="device_focus[]" value="android" data-wizard-required <?= in_array('android', $selectedFocuses, true) ? 'checked' : '' ?>>
                            <span class="wizard-choice__logo wizard-choice__logo--android" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M6.2 8.1 4.6 5.3a.5.5 0 0 1 .87-.5l1.65 2.85a9.2 9.2 0 0 1 9.76 0l1.65-2.85a.5.5 0 0 1 .87.5L17.8 8.1a8.2 8.2 0 0 1 2.6 6H3.6a8.2 8.2 0 0 1 2.6-6ZM7.1 10.6a.8.8 0 1 0 0-1.6.8.8 0 0 0 0 1.6Zm9.8 0a.8.8 0 1 0 0-1.6.8.8 0 0 0 0 1.6ZM3.6 15.2h16.8v3.1a1.5 1.5 0 0 1-1.5 1.5h-.8v2.1a1.1 1.1 0 0 1-2.2 0v-2.1h-7v2.1a1.1 1.1 0 0 1-2.2 0v-2.1h-.8a1.5 1.5 0 0 1-1.5-1.5v-3.1Z"/></svg>
                            </span>
                            <strong>Android</strong>
                            <span>أجهزة أندرويد</span>
                        </label>
                    </div>
                    <p class="field__error" data-error-for="device_focus" aria-live="polite"><?= $err('device_focus') ?></p>
                </fieldset>

                <fieldset class="field wizard-choice">
                    <legend class="field__label">مستواك</legend>
                    <div class="wizard-choice__grid">
                        <label class="wizard-choice__card">
                            <input type="radio" name="tech_level" value="fast" data-wizard-required <?= $selectedLevel === 'fast' ? 'checked' : '' ?>>
                            <strong>فني فاست</strong>
                            <span>مبتدئ</span>
                        </label>
                        <label class="wizard-choice__card">
                            <input type="radio" name="tech_level" value="board" data-wizard-required <?= $selectedLevel === 'board' ? 'checked' : '' ?>>
                            <strong>فني بورد</strong>
                            <span>صيانة على مستوى البوردة</span>
                        </label>
                    </div>
                    <p class="field__error" data-error-for="tech_level" aria-live="polite"><?= $err('tech_level') ?></p>
                </fieldset>

                <div class="field" data-board-experience <?= $selectedLevel === 'board' ? '' : 'hidden' ?>>
                    <label class="field__label" for="board_experience">سنوات الخبرة</label>
                    <div class="wizard-experience__title"><span class="wizard-badge">غير مختبر</span></div>
                    <select class="field__input" id="board_experience" name="board_experience" data-board-select <?= $selectedLevel === 'board' ? 'required' : 'disabled' ?>>
                        <option value="">اختر سنوات الخبرة</option>
                        <option value="lt1" <?= $selectedExperience === 'lt1' ? 'selected' : '' ?>>أقل من سنة</option>
                        <option value="1-3" <?= $selectedExperience === '1-3' ? 'selected' : '' ?>>من سنة إلى 3 سنوات</option>
                        <option value="3-5" <?= $selectedExperience === '3-5' ? 'selected' : '' ?>>من 3 إلى 5 سنوات</option>
                        <option value="5-10" <?= $selectedExperience === '5-10' ? 'selected' : '' ?>>من 5 إلى 10 سنوات</option>
                        <option value="gt10" <?= $selectedExperience === 'gt10' ? 'selected' : '' ?>>أكثر من 10 سنوات</option>
                    </select>
                    <p class="field__hint">لم تجتز اختبار المستوى بعد. الاختبار يوثّق مستواك وسيتوفر قريبًا.</p>
                    <p class="field__error" data-error-for="board_experience" aria-live="polite"><?= $err('board_experience') ?></p>
                </div>

                <div class="wizard__actions">
                    <button class="btn btn--secondary" type="button" data-back>رجوع</button>
                    <button class="btn btn--primary" type="button" data-next>التالي</button>
                </div>
            </section>

            <section class="wizard__step" data-step="3" aria-labelledby="wizard-profile-title"<?= $activeStep === 3 ? '' : ' hidden' ?>>
                <h2 class="wizard__heading" id="wizard-profile-title">ملفك الشخصي</h2>
                <div class="wizard-profile-placeholder">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h3l1.5-2h7L17 7h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg></span>
                    <strong>صورتك الشخصية</strong>
                    <p>إضافة الصورة ستتوفر قريبًا.</p>
                </div>
                <div class="field">
                    <label class="field__label" for="profile_bio">نبذة عنك</label>
                    <textarea class="field__input" id="profile_bio" name="profile_bio" rows="3" maxlength="1000" placeholder="اكتب نبذة قصيرة عن خبرتك" data-profile-field><?= $val('profile_bio') ?></textarea>
                    <p class="field__error" data-error-for="profile_bio" aria-live="polite"><?= $err('profile_bio') ?></p>
                </div>
                <div class="field">
                    <label class="field__label" for="workshop_name">اسم الورشة</label>
                    <input class="field__input" id="workshop_name" name="workshop_name" type="text" maxlength="100" value="<?= $val('workshop_name') ?>" placeholder="اسم ورشتك" data-profile-field>
                    <p class="field__error" data-error-for="workshop_name" aria-live="polite"><?= $err('workshop_name') ?></p>
                </div>
                <p class="field__hint">يمكنك تخطي هذه الخطوة وإكمالها لاحقًا من ملفك الشخصي.</p>
                <div class="wizard__actions">
                    <button class="btn btn--secondary" type="button" data-back>رجوع</button>
                    <button class="btn btn--primary" type="submit" data-profile-submit>تخطي</button>
                </div>
            </section>
        </form>

        <p class="auth__switch">لديك حساب؟ <a href="<?= $e($baseUrl) ?>/login">تسجيل الدخول</a></p>
    </div>
</section>
