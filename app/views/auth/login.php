<?php
$countries = require dirname(__DIR__, 3) . '/config/countries.php';
$selectedCountry = 'EG';
?>
<section class="auth">
    <div class="card auth__card">
        <div>
            <h1 class="auth__title">تسجيل الدخول</h1>
            <p class="auth__sub">ادخل بحسابك لمتابعة حالاتك وحلولك.</p>
        </div>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/login" novalidate data-validate data-login-identifier-form>
            <?= $csrf() ?>

            <div class="field">
                <label class="field__label" for="login-identifier-input" data-login-identifier-label>رقم الهاتف أو البريد الإلكتروني أو اسم المستخدم</label>
                <div class="wizard-contact login-contact" dir="ltr" data-login-contact>
                    <button class="wizard-contact__switch" type="button" data-login-contact-switch aria-label="التبديل إلى البريد الإلكتروني" title="التبديل بين الهاتف والبريد">
                        <svg data-login-contact-icon viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 18.5h4"/></svg>
                    </button>

                    <div class="wizard-contact__phone" data-login-phone-controls>
                        <button class="country-picker" type="button" data-login-country-toggle aria-haspopup="listbox" aria-expanded="false">
                            <span data-login-country-flag>🇪🇬</span>
                            <span data-login-country-dial>+20</span>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                        </button>
                        <input id="login-identifier-input" type="text" inputmode="tel" autocomplete="username"
                               placeholder="رقم الهاتف أو البريد أو اسم المستخدم" autocapitalize="none" spellcheck="false"
                               data-rules="required" data-login-visible-input>
                        <div class="country-list" data-login-country-list role="listbox" aria-label="اختر الدولة" hidden>
                            <?php foreach ($countries as $iso => $country) : ?>
                                <button type="button" class="country-list__item" role="option" aria-selected="<?= $iso === $selectedCountry ? 'true' : 'false' ?>"
                                        data-login-country="<?= $e($iso) ?>" data-dial="<?= $e($country['dial']) ?>" data-flag="<?= $e($country['flag']) ?>">
                                    <span class="country-list__flag"><?= $e($country['flag']) ?></span>
                                    <span class="country-list__name"><?= $e($country['name']) ?></span>
                                    <span class="country-list__iso"><?= $e($iso) ?></span>
                                    <span class="country-list__dial">+<?= $e($country['dial']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="identifier" name="identifier" value="">
                <p class="field__error" aria-live="polite"></p>
            </div>

            <div class="field">
                <label class="field__label" for="password">كلمة المرور</label>
                <div class="field__password">
                    <input class="field__input field__input--ltr" id="password" name="password" type="password"
                           autocomplete="current-password" data-rules="required">
                    <button class="field__toggle" type="button" data-toggle-password hidden
                            aria-label="إظهار كلمة المرور" aria-pressed="false">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <p class="field__error" aria-live="polite"></p>
            </div>

            <button class="btn btn--primary btn--block" type="submit">تسجيل الدخول</button>
        </form>

        <p class="auth__switch">ليس لديك حساب؟ <a href="<?= $e($baseUrl) ?>/register">إنشاء حساب</a></p>
    </div>
</section>
