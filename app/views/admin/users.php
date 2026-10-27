<div class="page-head">
    <h1 class="page-head__title">إدارة الحسابات</h1>
    <p class="muted">إجراءات الإدارة تسجّل في سجل الأمان. لا تُعرض كلمات المرور الحالية.</p>
    <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/admin/skill-tests">إدارة اختبارات المهارات</a>
</div>
<div class="container">
    <?php if (!$users) : ?>
        <div class="card empty-state">لا توجد حسابات.</div>
    <?php else : ?>
        <?php foreach ($users as $user) : ?>
            <section class="card" style="margin-bottom:1rem;padding:1rem">
                <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                    <div>
                        <strong><?= $e($user['display_name']) ?></strong>
                        <div class="muted">@<?= $e($user['username']) ?> · #<?= (int) $user['id'] ?></div>
                        <div><?= $e((string) ($user['email'] ?? 'بدون بريد')) ?> · <?= $e((string) ($user['phone'] ?? 'بدون هاتف')) ?></div>
                        <div class="muted">الدور: <?= $e($user['role']) ?> · الحالة: <?= $e($user['account_status']) ?></div>
                        <?php if (!empty($user['scheduled_purge_at'])) : ?>
                            <div class="muted">موعد انتهاء الاحتفاظ: <?= $e($user['scheduled_purge_at']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['account_status'] !== 'deleted') : ?>
                        <form method="post" action="<?= $e($baseUrl) ?>/admin/users/<?= (int) $user['id'] ?>/role" class="issue-form">
                            <?= $csrf() ?>
                            <label class="field"><span class="field__label">دور الحساب</span>
                                <select class="field__input" name="role">
                                    <?php foreach (['member' => 'عضو', 'moderator' => 'مشرف', 'admin' => 'مدير'] as $value => $label) : ?>
                                        <option value="<?= $e($value) ?>"<?= $user['role'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="btn btn--secondary" type="submit">حفظ الدور</button>
                        </form>
                        <form method="post" action="<?= $e($baseUrl) ?>/admin/users/<?= (int) $user['id'] ?>/status" class="issue-form">
                            <?= $csrf() ?>
                            <label class="field"><span class="field__label">حالة الحساب</span>
                                <select class="field__input" name="status">
                                    <?php foreach (['active' => 'نشط', 'suspended' => 'موقوف مؤقتًا', 'banned' => 'محظور'] as $value => $label) : ?>
                                        <option value="<?= $e($value) ?>"<?= $user['account_status'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="btn btn--secondary" type="submit">حفظ الحالة</button>
                        </form>
                        <form method="post" action="<?= $e($baseUrl) ?>/admin/users/<?= (int) $user['id'] ?>/password-reset" class="issue-form">
                            <?= $csrf() ?>
                            <label class="field"><span class="field__label">كلمة مرور مؤقتة (12 حرفًا على الأقل)</span><input class="field__input" type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
                            <label class="field"><span class="field__label">ملاحظة إدارية (اختياري)</span><input class="field__input" name="note" maxlength="255"></label>
                            <button class="btn btn--primary" type="submit">إعادة تعيين كلمة المرور</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
