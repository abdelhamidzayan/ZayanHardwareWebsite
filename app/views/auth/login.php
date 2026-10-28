<section class="auth">
    <div class="card auth__card">
        <div>
            <h1 class="auth__title">تسجيل الدخول</h1>
            <p class="auth__sub">ادخل بحسابك لمتابعة حالاتك وحلولك.</p>
        </div>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/login" novalidate data-validate data-login-identifier-form>
            <?= $csrf() ?>

            <div class="field">
                <label class="field__label" for="login-identifier-input" data-login-identifier-label>البريد الإلكتروني أو رقم الهاتف أو اسم المستخدم</label>
                <div class="login-identifier">
                    <div class="login-identifier__phone" data-login-phone-controls>
                        <label class="sr-only" for="login-country-code">الدولة</label>
                        <select class="login-identifier__country" id="login-country-code" aria-label="كود الدولة" data-login-country>
                            <option value="+20" selected>🇪🇬 +20</option>
                            <option value="+966">🇸🇦 +966</option>
                            <option value="+971">🇦🇪 +971</option>
                            <option value="+965">🇰🇼 +965</option>
                            <option value="+974">🇶🇦 +974</option>
                            <option value="+962">🇯🇴 +962</option>
                            <option value="+1">🇺🇸 +1</option>
                            <option value="+44">🇬🇧 +44</option>
                        </select>
                    </div>
                    <input class="field__input login-identifier__input" id="login-identifier-input" type="text"
                           inputmode="text" autocomplete="username" placeholder="البريد الإلكتروني أو رقم الهاتف أو اسم المستخدم"
                           autocapitalize="none" spellcheck="false" data-rules="required" data-login-visible-input>
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
