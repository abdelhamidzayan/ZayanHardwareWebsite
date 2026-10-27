<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\User;

final class AuthService
{
    public const EXPERIENCE = ['lt1', '1-3', '3-5', '5-10', 'gt10'];

    /**
     * Validates and creates a technician account.
     * @param string[] $specializations allowed specialization slugs
     * @return array{errors: array<string,string>, userId: ?int}
     */
    public static function register(array $input, array $skillDomains): array
    {
        $displayName = trim((string) preg_replace('/\\s+/u', ' ', self::str($input, 'display_name')));
        $username    = strtolower(trim(self::str($input, 'username')));
        $email       = strtolower(trim(self::str($input, 'email')));
        $password    = self::str($input, 'password');
        $workshop    = trim(self::str($input, 'workshop'));
        $phone       = preg_replace('/[\\s-]+/', '', trim(self::str($input, 'phone')));
        $devicesRaw  = $input['device_specializations'] ?? [];
        $skillsRaw   = $input['skill_domains'] ?? [];
        $devices     = is_array($devicesRaw) ? array_values(array_unique(array_filter($devicesRaw, 'is_string'))) : [];
        $skills      = is_array($skillsRaw) ? array_values(array_unique(array_filter($skillsRaw, 'is_string'))) : [];

        $v = new Validator([
            'display_name' => $displayName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ]);

        $v->required('display_name', 'أدخل الاسم الظاهر')
          ->length('display_name', 2, 60, 'الاسم من 2 إلى 60 حرفًا');
        $v->required('username', 'أدخل اسم المستخدم')
          ->pattern('username', '/^[a-z0-9_]{3,30}$/', 'من 3 إلى 30 حرفًا: إنجليزية صغيرة وأرقام و _ فقط');
        if ($email !== '') {
            $v->email('email', 'أدخل بريدًا إلكترونيًا صحيحًا')
              ->length('email', 3, 190, 'البريد الإلكتروني طويل جدًا');
        }
        if ($phone !== '' && preg_match('/^\\+?[0-9]{7,20}$/', $phone) !== 1) {
            $v->fail('phone', 'أدخل رقم هاتف صحيحًا');
        }
        if ($email === '' && $phone === '') {
            $v->fail('contact', 'أدخل البريد الإلكتروني أو رقم الهاتف على الأقل');
        }
        $v->required('password', 'أدخل كلمة المرور')
          ->length('password', 10, 128, 'كلمة المرور لا تقل عن 10 أحرف');
        if (strlen($password) > 72) {
            $v->fail('password', 'كلمة المرور طويلة جدًا (الحد 72 بايت)');
        }
        if (array_diff($devices, ['iphone', 'android'])) {
            $v->fail('device_specializations', 'اختيار الأجهزة غير صالح');
        }
        if (array_diff($skills, $skillDomains)) {
            $v->fail('skill_domains', 'اختيار المجالات الفنية غير صالح');
        }
        if ($workshop !== '' && mb_strlen($workshop) > 80) {
            $v->fail('workshop', 'اسم الورشة لا يزيد عن 80 حرفًا');
        }
        if (self::str($input, 'terms') !== '1') {
            $v->fail('terms', 'يجب الموافقة على القواعد وسياسة الخصوصية للمتابعة');
        }

        $errors = $v->errors();
        if (!isset($errors['username']) && User::exists('username', $username)) {
            $errors['username'] = 'اسم المستخدم مستخدم بالفعل';
        }
        if ($email !== '' && !isset($errors['email']) && User::exists('email', $email)) {
            $errors['email'] = 'هذا البريد مسجّل بالفعل';
        }
        if ($phone !== '' && !isset($errors['phone']) && User::phoneExists($phone)) {
            $errors['phone'] = 'رقم الهاتف مسجّل بالفعل';
        }
        if ($errors) {
            return ['errors' => $errors, 'userId' => null];
        }

        try {
            $userId = User::create(
                [
                    'username' => $username,
                    'display_name' => $displayName,
                    'email' => $email !== '' ? $email : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ],
                [
                    'specialization' => $skills[0] ?? null,
                    'experience' => null,
                    'workshop_name' => $workshop !== '' ? $workshop : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'device_specializations' => $devices,
                    'skill_domains' => $skills,
                    'terms_version' => '1.0',
                    'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
                ]
            );
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $message = $e->getMessage();
                if (str_contains($message, 'uq_users_email')) {
                    return ['errors' => ['email' => 'هذا البريد مسجّل بالفعل'], 'userId' => null];
                }
                if (str_contains($message, 'uq_technician_profiles_phone')) {
                    return ['errors' => ['phone' => 'رقم الهاتف مسجّل بالفعل'], 'userId' => null];
                }
                return ['errors' => ['username' => 'اسم المستخدم مستخدم بالفعل'], 'userId' => null];
            }
            throw $e;
        }

        return ['errors' => [], 'userId' => $userId];
    }

    private static ?array $current = null;
    private static bool $loaded = false;

    /** The logged-in user for this request (re-checked against the database every request). */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;

        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return self::$current = null;
        }

        $user = User::findById($id);
        if ($user === null || $user['account_status'] !== 'active') {
            self::forget();

            return null;
        }

        return self::$current = $user;
    }

    /**
     * Checks credentials. Same work whether or not the account exists (no timing hint).
     * @return array{user: ?array, error: ?string}  error is 'invalid' or 'blocked'
     */
    public static function attempt(string $identifier, string $password): array
    {
        $identifier = strtolower(trim($identifier));
        $row        = strlen($identifier) <= 190 ? User::findForLogin($identifier) : null;

        $valid = password_verify($password, $row['password_hash'] ?? self::dummyHash());
        if ($row === null || !$valid) {
            return ['user' => null, 'error' => 'invalid'];
        }
        if ($row['account_status'] !== 'active') {
            return ['user' => null, 'error' => 'blocked'];
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $row['new_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return ['user' => $row, 'error' => null];
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['_csrf']);
        self::$loaded  = false;
        self::$current = null;
    }

    public static function logout(): void
    {
        self::forget();
    }

    private static function forget(): void
    {
        unset($_SESSION['user_id'], $_SESSION['_csrf']);
        session_regenerate_id(true);
        self::$current = null;
        self::$loaded  = true;
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }

    private static function str(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
