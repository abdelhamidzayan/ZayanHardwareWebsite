<section class="auth">
    <div class="card auth__card">
        <div>
            <h1 class="auth__title">تسجيل الدخول</h1>
            <p class="auth__sub">ادخل بحسابك لمتابعة حالاتك وحلولك.</p>
        </div>

        <form class="auth__form" method="post" action="<?= $e($baseUrl) ?>/login" novalidate data-validate>
            <?= $csrf() ?>
            <div class="field">
                <label class="field__label" for="identifier">البريد الإلكتروني أو اسم المستخدم</label>
                <input class="field__input field__input--ltr" id="identifier" name="identifier" type="text"
                       autocomplete="username" autocapitalize="none" spellcheck="false" data-rules="required">
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
