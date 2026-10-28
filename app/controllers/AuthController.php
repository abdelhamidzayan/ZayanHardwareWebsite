<?php
declare(strict_types=1);

namespace App\Controllers;

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
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');

            return $this->redirect('/login');
        }

        $identifier = $this->text('identifier');
        $password   = $this->text('password');

        $ipBucket = 'login:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $idBucket = 'login:id:' . hash('sha256', strtolower(trim($identifier)));
        if (RateLimiter::tooMany($ipBucket, 20, 900) || RateLimiter::tooMany($idBucket, 5, 900)) {
            $this->flash('error', 'محاولات كثيرة. حاول مرة أخرى بعد 15 دقيقة.');

            return $this->redirect('/login');
        }

        $result = AuthService::attempt($identifier, $password);

        if ($result['error'] === 'invalid') {
            RateLimiter::hit($ipBucket);
            RateLimiter::hit($idBucket);
            $this->flash('error', 'بيانات الدخول غير صحيحة.');

            return $this->redirect('/login');
        }
        if ($result['error'] === 'blocked') {
            $this->flash('error', 'هذا الحساب موقوف. تواصل مع الإدارة.');

            return $this->redirect('/login');
        }

        $user = $result['user'];
        User::markLoggedIn((int) $user['id'], $user['new_hash'] ?? null);
        AuthService::login((int) $user['id']);

        $this->flash('success', 'تم تسجيل الدخول بنجاح.');

        return $this->redirect('/feed');
    }

    public function logout(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');

            return $this->redirect('/feed');
        }

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
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register');
        }

        $bucket = 'register:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (RateLimiter::tooMany($bucket, 5, 3600)) {
            $this->flash('error', 'محاولات كثيرة. حاول مرة أخرى بعد قليل.');
            return $this->redirect('/register');
        }
        RateLimiter::hit($bucket);

        $result = AuthService::register($this->request->body);
        if ($result['errors']) return $this->registerForm($result['errors'], 422);

        // Registration creates an active account, so establish the session immediately.
        $userId = (int) ($result['userId'] ?? 0);
        if ($userId > 0) {
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
        $bucket = 'register:usernames:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (RateLimiter::tooMany($bucket, 90, 60)) {
            RateLimiter::hit($bucket);
            return new Response(
                json_encode(['available' => []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                429,
                ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
            );
        }
        RateLimiter::hit($bucket);

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
        $firstStepErrors = ['display_name', 'contact', 'password'];
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
