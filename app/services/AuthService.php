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
    public static function register(array $input, array $specializations): array
    {
        $displayName = trim((string) preg_replace('/\s+/u', ' ', self::str($input, 'display_name')));
        $username    = strtolower(trim(self::str($input, 'username')));
        $email       = strtolower(trim(self::str($input, 'email')));
        $password    = self::str($input, 'password');
        $workshop    = trim(self::str($input, 'workshop'));
        $phone       = trim(self::str($input, 'phone'));

        $v = new Validator([
            'display_name'   => $displayName,
            'username'       => $username,
            'email'          => $email,
            'password'       => $password,
            'specialization' => self::str($input, 'specialization'),
            'experience'     => self::str($input, 'experience'),
            'workshop'       => $workshop,
            'phone'          => $phone,
        ]);

        $v->required('display_name', 'أدخل الاسم الظاهر')
          ->length('display_name', 2, 60, 'الاسم من 2 إلى 60 حرفًا');
        $v->required('username', 'أدخل اسم المستخدم')
          ->pattern('username', '/^[a-z0-9_]{3,30}$/', 'من 3 إلى 30 حرفًا: إنجليزية صغيرة وأرقام و _');
        $v->required('email', 'أدخل البريد الإلكتروني')
          ->email('email', 'أدخل بريدًا إلكترونيًا صحيحًا')
          ->length('email', 3, 190, 'البريد الإلكتروني طويل جدًا');
        $v->required('password', 'أدخل كلمة المرور')
          ->length('password', 10, 128, 'كلمة المرور لا تقل عن 10 أحرف');
        if (strlen($password) > 72) {
            $v->fail('password', 'كلمة المرور طويلة جدًا (الحد 72 بايت)');
        }
        $v->in('specialization', $specializations, 'اختر التخصص');
        $v->in('experience', self::EXPERIENCE, 'اختر مدة الخبرة');
        if ($workshop !== '') {
            $v->length('workshop', 1, 80, 'اسم الورشة لا يزيد عن 80 حرفًا');
        }
        if ($phone !== '') {
            $v->pattern('phone', '/^\+?[0-9\s-]{7,20}$/', 'أدخل رقم هاتف صحيحًا');
        }
        if (self::str($input, 'terms') !== '1') {
            $v->fail('terms', 'يجب الموافقة على القواعد للمتابعة');
        }

        $errors = $v->errors();
        if (!isset($errors['username']) && User::exists('username', $username)) {
            $errors['username'] = 'اسم المستخدم مستخدم بالفعل';
        }
        if (!isset($errors['email']) && User::exists('email', $email)) {
            $errors['email'] = 'هذا البريد مسجّل بالفعل';
        }
        if ($errors) {
            return ['errors' => $errors, 'userId' => null];
        }

        try {
            $userId = User::create(
                [
                    'username'      => $username,
                    'display_name'  => $displayName,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ],
                [
                    'specialization' => self::str($input, 'specialization'),
                    'experience'     => self::str($input, 'experience'),
                    'workshop_name'  => $workshop !== '' ? $workshop : null,
                    'phone'          => $phone !== '' ? preg_replace('/[\s-]+/', '', $phone) : null,
                ]
            );
        } catch (\PDOException $e) {
            // Two people registering the same name at the same moment.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $emailTaken = str_contains($e->getMessage(), 'uq_users_email');

                return [
                    'errors' => $emailTaken
                        ? ['email' => 'هذا البريد مسجّل بالفعل']
                        : ['username' => 'اسم المستخدم مستخدم بالفعل'],
                    'userId' => null,
                ];
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
        if ($user === null || in_array($user['account_status'], ['suspended', 'banned'], true)) {
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
        if (in_array($row['account_status'], ['suspended', 'banned'], true)) {
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
