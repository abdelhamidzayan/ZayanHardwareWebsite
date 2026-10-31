<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\User;

final class AuthService
{
    private const TERMS_VERSION = '2026-10-28';

    public const EXPERIENCE = ['lt1', '1-3', '3-5', '5-10', 'gt10'];

    /**
     * Validates registration fields and creates the account atomically.
     * @return array{errors: array<string,string>, userId: ?int}
     */
    public static function register(array $input): array
    {
        $displayName = trim((string) preg_replace('/\s+/u', ' ', self::str($input, 'display_name')));
        $password = self::str($input, 'password');
        $phoneRaw = self::str($input, 'phone_number');
        $contactType = self::str($input, 'contact_type');
        if (!in_array($contactType, ['phone', 'email'], true)) {
            $contactType = $phoneRaw !== '' ? 'phone' : 'email';
        }

        $countries = require dirname(__DIR__, 2) . '/config/countries.php';
        $country = self::str($input, 'country') ?: 'EG';
        if (!isset($countries[$country])) $country = 'EG';
        $dial = (string) $countries[$country]['dial'];

        $emailInput = self::str($input, 'email_local');
        $emailDomain = self::str($input, 'email_domain');
        if (str_contains($emailInput, '@')) {
            $email = strtolower(trim($emailInput));
        } else {
            $email = strtolower(trim($emailInput . ($emailDomain !== '' ? '@' . trim($emailDomain) : '')));
        }
        $phone = $contactType === 'phone' ? PhoneNumber::fromParts($dial, $phoneRaw) : null;
        $emailValue = $contactType === 'email' ? $email : '';
        $username = strtolower(trim(self::str($input, 'username')));
        $deviceFocusInput = $input['device_focus'] ?? [];
        if (!is_array($deviceFocusInput)) $deviceFocusInput = [$deviceFocusInput];
        $deviceFocuses = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            $deviceFocusInput
        ), static fn (string $value): bool => in_array($value, ['iphone', 'android'], true))));
        $deviceFocus = $deviceFocuses[0] ?? '';
        $techLevel = self::str($input, 'tech_level');
        $boardExperience = self::str($input, 'board_experience');
        $biography = trim(self::str($input, 'profile_bio'));
        $workshopName = trim(self::str($input, 'workshop_name'));
        $reserved = ['admin', 'administrator', 'moderator', 'support', 'root', 'system', 'staff', 'zayan', 'hardware', 'hardwaretech'];
        $errors = [];

        if (self::str($input, 'accept_terms') !== '1') {
            $errors['terms_acceptance'] = 'يجب الموافقة على قواعد المنصة وسياسة الخصوصية لإنشاء الحساب.';
        }
        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 60) {
            $errors['display_name'] = 'الاسم من 2 إلى 60 حرفًا';
        }
        if ($contactType === 'phone') {
            if ($phone === null) $errors['contact'] = 'أدخل رقم هاتف صحيحًا';
            elseif (User::exists('phone', $phone)) $errors['contact'] = 'هذا الرقم مسجّل بالفعل';
        } else {
            if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['contact'] = 'أدخل بريدًا إلكترونيًا صحيحًا';
            } elseif (User::exists('email', $email)) {
                $errors['contact'] = 'هذا البريد مسجّل بالفعل';
            }
        }
        if ($password === '' || mb_strlen($password) < 10) {
            $errors['password'] = 'كلمة المرور يجب أن تكون 10 أحرف على الأقل';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'كلمة المرور طويلة جدًا (الحد 72 بايت)';
        }
        if (preg_match('/^[a-z0-9_]{3,20}$/', $username) !== 1 || in_array($username, $reserved, true)) {
            $errors['username'] = 'اسم المستخدم من 3 إلى 20 حرفًا، ولا يمكن استخدام هذا الاسم';
        } elseif (User::exists('username', $username)) {
            $errors['username'] = 'اسم المستخدم غير متاح';
        }
        if (!$deviceFocuses) {
            $errors['device_focus'] = 'اختر مجال تخصصك';
        }
        if (!in_array($techLevel, ['fast', 'board'], true)) {
            $errors['tech_level'] = 'اختر مستواك الفني';
        }
        if ($techLevel === 'board' && !in_array($boardExperience, self::EXPERIENCE, true)) {
            $errors['board_experience'] = 'حدد سنوات الخبرة';
        }
        if ($techLevel !== 'board') $boardExperience = '';
        if (mb_strlen($biography) > 1000) $errors['profile_bio'] = 'النبذة يجب ألا تتجاوز 1000 حرف';
        if (mb_strlen($workshopName) > 100) $errors['workshop_name'] = 'اسم الورشة يجب ألا يتجاوز 100 حرف';

        if ($errors) return ['errors' => $errors, 'userId' => null];

        try {
            $userId = User::create([
                'username' => $username,
                'display_name' => $displayName,
                'email' => $emailValue !== '' ? $emailValue : null,
                'phone' => $phone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'terms_version' => self::TERMS_VERSION,
                'terms_ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ], [
                'device_focus' => $deviceFocus,
                'device_focuses' => $deviceFocuses,
                'tech_level' => $techLevel,
                'board_experience' => $boardExperience !== '' ? $boardExperience : null,
                'biography' => $biography !== '' ? $biography : null,
                'workshop_name' => $workshopName !== '' ? $workshopName : null,
            ]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $message = $e->getMessage();
                if (str_contains($message, 'uq_users_email')) {
                    return ['errors' => ['contact' => 'هذا البريد مسجّل بالفعل'], 'userId' => null];
                }
                if (str_contains($message, 'uq_users_phone')) {
                    return ['errors' => ['contact' => 'هذا الرقم مسجّل بالفعل'], 'userId' => null];
                }
                return ['errors' => ['username' => 'اسم المستخدم غير متاح'], 'userId' => null];
            }
            throw $e;
        }

        return ['errors' => [], 'userId' => $userId];
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
        $sessionVersion = $_SESSION['auth_version'] ?? null;
        if (!is_int($id) || !is_int($sessionVersion) || $sessionVersion < 1) {
            self::forget();
            return null;
        }

        $user = User::findById($id);
        if ($user === null || $user['account_status'] !== 'active'
            || (int) ($user['auth_version'] ?? 0) !== $sessionVersion) {
            self::forget();

            return null;
        }

        return self::$current = $user;
    }

    /**
     * Checks credentials with a dummy hash for missing accounts.
     * @return array{user: ?array, error: ?string}
     */
    public static function attempt(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $row = null;
        if ($identifier !== '' && strlen($identifier) <= 190) {
            if (str_contains($identifier, '@')) {
                // Any identifier containing @ is treated only as an email address.
                $row = User::findForLogin(strtolower($identifier), 'email');
            } elseif (preg_match('/^[+0-9٠-٩۰-۹().\s-]+$/u', $identifier) === 1
                && preg_match_all('/[0-9٠-٩۰-۹]/u', $identifier) >= 7) {
                // Phone numbers are normalized and checked only against the phone column.
                $countries = require dirname(__DIR__, 2) . '/config/countries.php';
                $phone = PhoneNumber::guess($identifier, array_column($countries, 'dial'));
                if ($phone !== null) $row = User::findForLogin($phone, 'phone');
            } else {
                // Anything without @ that is not a phone number is a username.
                $row = User::findForLogin(strtolower($identifier), 'username');
            }
        }

        $valid = password_verify($password, $row['password_hash'] ?? self::dummyHash());
        if ($row === null || !$valid) return ['user' => null, 'error' => 'invalid'];
        if ($row['account_status'] !== 'active') return ['user' => null, 'error' => 'blocked'];
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $row['new_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return ['user' => $row, 'error' => null];
    }

    public static function login(int $userId): bool
    {
        // Bind the new session to the current database auth_version. A password
        // reset increments that value and invalidates every previously issued session.
        $user = User::findById($userId);
        if ($user === null || $user['account_status'] !== 'active') {
            self::forget();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['auth_version'] = (int) $user['auth_version'];
        unset($_SESSION['_csrf']);
        self::$loaded  = false;
        self::$current = null;
        return true;
    }

    public static function logout(): void
    {
        self::forget();
    }

    private static function forget(): void
    {
        unset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['_csrf']);
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
