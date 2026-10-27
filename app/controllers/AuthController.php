<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\DemoData;
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

        return $this->view('auth/login', ['title' => 'تسجيل الدخول', 'authPage' => true], 200, 'guest');
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
        if (AuthService::user() !== null) {
            return $this->redirect('/feed');
        }

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

        $result = AuthService::register(
            $this->request->body,
            array_column(DemoData::categories(), 'slug') // allowed repair-skill domains
        );

        if ($result['errors']) {
            return $this->registerForm($result['errors'], 422);
        }

        $userId = (int) $result['userId'];
        User::markLoggedIn($userId);
        AuthService::login($userId);
        $this->flash('success', 'تم إنشاء الحساب. أكمل إعداد ملفك الفني أو تخطَّ الخطوات الاختيارية.');

        return $this->redirect('/register/stage/2');
    }

    public function showRegisterStage2(): Response
    {
        if (AuthService::user() === null) return $this->redirect('/register');
        return $this->view('auth/register-stage-2', [
            'title' => 'إنشاء الحساب - المرحلة 2',
            'authPage' => true,
            'errors' => [],
            'old' => [],
        ], 200, 'guest');
    }

    public function saveRegisterStage2(): Response
    {
        $user = AuthService::user();
        if ($user === null) return $this->redirect('/register');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register/stage/2');
        }
        $raw = $this->request->body['device_specializations'] ?? [];
        $devices = is_array($raw) ? array_values(array_unique(array_filter($raw, 'is_string'))) : [];
        if (array_diff($devices, ['iphone', 'android'])) {
            return $this->view('auth/register-stage-2', [
                'title' => 'إنشاء الحساب - المرحلة 2', 'authPage' => true,
                'errors' => ['device_specializations' => 'اختيار غير صالح'],
                'old' => ['device_specializations' => $devices],
            ], 422, 'guest');
        }
        User::saveDeviceSpecializations((int) $user['id'], $devices);
        return $this->redirect('/register/stage/3');
    }

    public function skipRegisterStage2(): Response
    {
        if (AuthService::user() === null) return $this->redirect('/register');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register/stage/2');
        }
        return $this->redirect('/register/stage/3');
    }

    public function showRegisterStage3(): Response
    {
        if (AuthService::user() === null) return $this->redirect('/register');
        return $this->view('auth/register-stage-3', [
            'title' => 'إنشاء الحساب - المرحلة 3',
            'authPage' => true,
            'skillDomains' => DemoData::categories(),
            'errors' => [],
            'old' => [],
        ], 200, 'guest');
    }

    public function saveRegisterStage3(): Response
    {
        $user = AuthService::user();
        if ($user === null) return $this->redirect('/register');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register/stage/3');
        }
        $raw = $this->request->body['skill_domains'] ?? [];
        $skills = is_array($raw) ? array_values(array_unique(array_filter($raw, 'is_string'))) : [];
        $allowed = array_column(DemoData::categories(), 'slug');
        $workshop = trim(is_string($this->request->body['workshop'] ?? null) ? $this->request->body['workshop'] : '');
        $errors = [];
        if (array_diff($skills, $allowed)) $errors['skill_domains'] = 'اختيار المجالات الفنية غير صالح';
        if (mb_strlen($workshop) > 80) $errors['workshop'] = 'اسم الورشة لا يزيد عن 80 حرفًا';
        if ($errors) {
            return $this->view('auth/register-stage-3', [
                'title' => 'إنشاء الحساب - المرحلة 3', 'authPage' => true,
                'skillDomains' => DemoData::categories(), 'errors' => $errors,
                'old' => ['skill_domains' => $skills, 'workshop' => $workshop],
            ], 422, 'guest');
        }
        User::saveOptionalSkills((int) $user['id'], $skills, $workshop);
        $this->flash('success', 'اكتمل إعداد حسابك الفني بنجاح.');
        return $this->redirect('/feed');
    }

    public function skipRegisterStage3(): Response
    {
        if (AuthService::user() === null) return $this->redirect('/register');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register/stage/3');
        }
        $this->flash('success', 'تم إنشاء حسابك. يمكنك استكمال ملفك الفني لاحقًا.');
        return $this->redirect('/feed');
    }

    private function registerForm(array $errors = [], int $status = 200): Response
    {
        // Send back what the user typed, except the password and the token.
        $old = array_diff_key($this->request->body, ['password' => 1, '_token' => 1]);
        foreach ($old as $key => $value) {
            if (!is_string($value) && !is_array($value)) {
                unset($old[$key]);
            }
            if (is_array($value)) {
                $old[$key] = array_values(array_filter($value, 'is_string'));
            }
        }

        return $this->view('auth/register', [
            'title'      => 'إنشاء حساب',
            'authPage'   => true,
            'categories' => DemoData::categories(),
            'skillDomains' => DemoData::categories(),
            'errors'     => $errors,
            'old'        => $old,
        ], $status, 'guest');
    }

    private function text(string $key): string
    {
        $value = $this->request->input($key);

        return is_string($value) ? $value : '';
    }
}
