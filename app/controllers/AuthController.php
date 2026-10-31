<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RateLimiter;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (AuthService::user() !== null) {
            return $this->redirect('/feed');
        }

        $countries = require dirname(__DIR__, 2) . '/config/countries.php';
        return $this->view('auth/login', ['title' => 'تسجيل الدخول', 'authPage' => true, 'countries' => $countries], 200, 'guest');
    }

    public function login(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) {
            App::security('auth.csrf.rejected', ['ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))]);
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');

            return $this->redirect('/login');
        }

        $identifier = $this->text('identifier');
        $password   = $this->text('password');

        $guardedAttempt = RateLimiter::attemptForIpAndIdentifier(
            'auth.login',
            $identifier,
            20,
            5,
            900,
            static fn (): array => AuthService::attempt($identifier, $password)
        );
        if (!$guardedAttempt['allowed']) {
            App::security('auth.login.rate_limited', [
                'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $this->flash('error', 'محاولات كثيرة. حاول مرة أخرى بعد 15 دقيقة.');
            return $this->redirect('/login');
        }

        $result = $guardedAttempt['result'] ?? ['user' => null, 'error' => 'invalid'];
        if ($result['error'] === 'invalid') {
            App::security('auth.login.failed', [
                'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $this->flash('error', 'بيانات الدخول غير صحيحة.');
            return $this->redirect('/login');
        }
        if ($result['error'] === 'blocked') {
            App::security('auth.login.blocked_account', [
                'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $this->flash('error', 'هذا الحساب موقوف. تواصل مع الإدارة.');
            return $this->redirect('/login');
        }

        $user = $result['user'];
        User::markLoggedIn((int) $user['id'], $user['new_hash'] ?? null);
        AuthService::login((int) $user['id']);
        App::security('auth.login.succeeded', ['user_id' => (int) $user['id'], 'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))]);

        $this->flash('success', 'تم تسجيل الدخول بنجاح.');

        return $this->redirect('/feed');
    }

    public function logout(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) {
            App::security('auth.csrf.rejected', ['ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))]);
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');

            return $this->redirect('/feed');
        }

        $currentUser = AuthService::user();
        App::audit('auth.logout', ['user_id' => (int) ($currentUser['id'] ?? 0)]);
        AuthService::logout();
        $this->flash('info', 'تم تسجيل الخروج.');

        return $this->redirect('/');
    }

    public function showRegister(): Response
    {
        if (AuthService::user() !== null) return $this->redirect('/feed');
        return $this->registerForm();
    }

    public function register(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) {
            App::security('auth.csrf.rejected', ['ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))]);
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register');
        }

        if (!RateLimiter::allowForIp('auth.register', 5, 3600)) {
            App::security('auth.register.rate_limited', [
                'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $this->flash('error', 'محاولات كثيرة. حاول مرة أخرى بعد قليل.');
            return $this->redirect('/register');
        }

        $result = AuthService::register($this->request->body);
        if ($result['errors']) return $this->registerForm($result['errors'], 422);

        // Registration creates an active account, so establish the session immediately.
        $userId = (int) ($result['userId'] ?? 0);
        if ($userId > 0) {
            App::audit('account.registered', ['user_id' => $userId]);
            User::markLoggedIn($userId);
            AuthService::login($userId);
            $this->flash('success', 'تم إنشاء حسابك وتسجيل دخولك بنجاح.');
            return $this->redirect('/feed');
        }

        $this->flash('error', 'تم إنشاء الحساب، لكن تعذّر تسجيل الدخول تلقائيًا. حاول تسجيل الدخول.');
        return $this->redirect('/login');
    }

    public function usernames(): Response
    {
        if (!RateLimiter::allowForIp('auth.usernames', 90, 60)) {
            App::security('auth.usernames.rate_limited', [
                'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            return Response::json(['available' => []], 429);
        }

        $names = $this->request->query['names'] ?? [];
        if (!is_array($names)) $names = [];
        $names = array_slice(array_values(array_unique(array_filter($names, 'is_string'))), 0, 8);
        $reserved = ['admin', 'administrator', 'moderator', 'support', 'root', 'system', 'staff', 'zayan', 'hardware', 'hardwaretech'];
        $valid = [];
        foreach ($names as $name) {
            $name = strtolower(trim($name));
            if (preg_match('/^[a-z0-9_]{3,20}$/', $name) === 1 && !in_array($name, $reserved, true)) {
                if (!in_array($name, $valid, true)) $valid[] = $name;
            }
        }
        $taken = User::existingUsernames($valid);
        $available = array_values(array_diff($valid, $taken));
        return Response::json(['available' => $available]);
    }

    private function registerForm(array $errors = [], int $status = 200): Response
    {
        $old = array_diff_key($this->request->body, ['password' => 1, '_token' => 1]);
        foreach ($old as $key => $value) {
            if ($key === 'device_focus' && is_array($value)) {
                $old[$key] = array_values(array_filter($value, static fn ($item): bool => is_string($item)));
                continue;
            }
            if (!is_string($value)) unset($old[$key]);
        }
        $countries = require dirname(__DIR__, 2) . '/config/countries.php';
        $firstStepErrors = ['display_name', 'contact', 'password', 'terms_acceptance'];
        $profileErrors = ['profile_bio', 'workshop_name'];
        if (!$errors || count(array_intersect(array_keys($errors), $firstStepErrors)) > 0) {
            $activeStep = 1;
        } elseif (count(array_intersect(array_keys($errors), $profileErrors)) > 0) {
            $activeStep = 3;
        } else {
            $activeStep = 2;
        }

        return $this->view('auth/register', [
            'title' => 'إنشاء حساب',
            'authPage' => true,
            'countries' => $countries,
            'errors' => $errors,
            'old' => $old,
            'activeStep' => $activeStep,
            'scripts' => ['register.js'],
        ], $status, 'guest');
    }

    private function text(string $key): string
    {
        $value = $this->request->input($key);

        return is_string($value) ? $value : '';
    }
}
