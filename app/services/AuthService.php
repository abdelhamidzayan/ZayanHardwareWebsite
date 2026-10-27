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
    public static function register(array $input, array $skillDomains = []): array
    {
        $displayName = trim((string) preg_replace('/\\s+/u', ' ', self::str($input, 'display_name')));
        $password = self::str($input, 'password');
        $contactMode = self::str($input, 'contact_mode') === 'email' ? 'email' : 'phone';
        $email = $contactMode === 'email' ? strtolower(trim(self::str($input, 'email'))) : '';
        $phoneRaw = $contactMode === 'phone' ? self::str($input, 'phone') : '';
        $countryCode = self::str($input, 'country_code') ?: '+20';
        $phone = $phoneRaw !== '' ? self::normalizePhone($phoneRaw, $countryCode) : '';

        $v = new Validator([
            'display_name' => $displayName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ]);
        $v->required('display_name', 'أدخل الاسم الظاهر')
          ->length('display_name', 2, 60, 'الاسم من 2 إلى 60 حرفًا');

        if ($contactMode === 'email') {
            $v->required('email', 'أدخل البريد الإلكتروني')
              ->email('email', 'أدخل بريدًا إلكترونيًا صحيحًا')
              ->length('email', 3, 190, 'البريد الإلكتروني طويل جدًا');
        } else {
            if ($phone === '') {
                $v->fail('phone', 'أدخل رقم الهاتف');
            } elseif (preg_match('/^\\+[0-9]{7,20}$/', $phone) !== 1) {
                $v->fail('phone', 'أدخل رقم هاتف صحيحًا مع كود الدولة');
            }
        }

        $v->required('password', 'أدخل كلمة المرور')
          ->length('password', 10, 128, 'كلمة المرور لا تقل عن 10 أحرف');
        if (strlen($password) > 72) {
            $v->fail('password', 'كلمة المرور طويلة جدًا (الحد 72 بايت)');
        }
        if (self::str($input, 'terms') !== '1') {
            $v->fail('terms', 'يجب الموافقة على القواعد وسياسة الخصوصية للمتابعة');
        }

        $errors = $v->errors();
        if ($email !== '' && !isset($errors['email']) && User::exists('email', $email)) {
            $errors['email'] = 'هذا البريد مسجّل بالفعل';
        }
        if ($phone !== '' && !isset($errors['phone']) && User::phoneExists($phone)) {
            $errors['phone'] = 'رقم الهاتف مسجّل بالفعل';
        }
        if ($errors) {
            return ['errors' => $errors, 'userId' => null];
        }

        $username = self::initialUsername($displayName, $email);
        try {
            $userId = User::create(
                [
                    'username' => $username,
                    'display_name' => $displayName,
                    'email' => $email !== '' ? $email : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ],
                [
                    'specialization' => null,
                    'experience' => null,
                    'workshop_name' => null,
                    'phone' => $phone !== '' ? $phone : null,
                    'device_specializations' => [],
                    'skill_domains' => [],
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
                return ['errors' => ['display_name' => 'تعذر إنشاء الحساب الآن. حاول مرة أخرى.'], 'userId' => null];
            }
            throw $e;
        }

        return ['errors' => [], 'userId' => $userId];
    }

    private static function normalizePhone(string $raw, string $countryCode): string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\\D+/', '', $raw) ?? '';
        if ($digits === '') return '';
        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }
        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }
        $code = preg_replace('/\\D+/', '', $countryCode) ?? '20';
        if ($code === '') $code = '20';
        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
            return '+' . $digits;
        }
        $national = ltrim($digits, '0');
        return '+' . $code . $national;
    }

    private static function initialUsername(string $displayName, string $email): string
    {
        $seed = $displayName !== '' ? $displayName : strstr($email, '@', true);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $seed);
        $base = strtolower((string) $ascii);
        $base = preg_replace('/[^a-z0-9]+/', '', $base) ?? '';
        if ($base === '') $base = 'tech';
        $base = substr($base, 0, 12);
        for ($i = 0; $i < 12; $i++) {
            $candidate = $base . random_int(1000, 999999);
            $candidate = substr($candidate, 0, 20);
            if (!User::exists('username', $candidate)) return $candidate;
        }
        return 'tech' . bin2hex(random_bytes(6));
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
        $originalIdentifier = strtolower(trim($identifier));
        $identifier = $originalIdentifier;
        if ($identifier !== '' && !str_contains($identifier, '@')) {
            $identifier = self::normalizePhone($identifier, '+20');
        }
        $row = strlen($identifier) <= 190 ? User::findForLogin($identifier) : null;
        // Keep legacy accounts working if their phone was saved before country-code normalization.
        if ($row === null && $originalIdentifier !== $identifier && strlen($originalIdentifier) <= 190) {
            $row = User::findForLogin($originalIdentifier);
        }

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
