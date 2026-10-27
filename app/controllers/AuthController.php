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
        $user = AuthService::user();
        if ($user === null) return $this->redirect('/register');
        return $this->view('auth/register-stage-2', [
            'title' => 'إنشاء الحساب - المرحلة 2', 'authPage' => true,
            'user' => $user, 'usernameSuggestions' => $this->usernameSuggestions($user),
            'errors' => [], 'old' => [],
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
        $username = strtolower(trim($this->text('username')));
        $device = $this->text('device_specialization');
        $level = $this->text('technician_level');
        $experience = $this->text('experience');
        $errors = [];

        if (preg_match('/^[a-z0-9_]{3,20}$/', $username) !== 1) {
            $errors['username'] = 'اسم المستخدم من 3 إلى 20 حرفًا: إنجليزية صغيرة وأرقام و _ فقط';
        } elseif ($username !== $user['username'] && User::exists('username', $username)) {
            $errors['username'] = 'اسم المستخدم ده مستخدم بالفعل، جرّب اقتراحًا آخر';
        }
        if (!in_array($device, ['iphone', 'android'], true)) $errors['device_specialization'] = 'اختار نوع الجهاز اللي بتتخصص فيه';
        if (!in_array($level, ['fast', 'board'], true)) $errors['technician_level'] = 'اختار مستوى شغلك';
        $allowedExperience = ['lt1', '1-3', '3-5', '5-10', 'gt10'];
        if ($level === 'board' && !in_array($experience, $allowedExperience, true)) $errors['experience'] = 'حدد سنوات خبرتك في البورد';
        if ($level !== 'board') $experience = '';

        if ($errors) {
            return $this->view('auth/register-stage-2', [
                'title' => 'إنشاء الحساب - المرحلة 2', 'authPage' => true,
                'user' => $user, 'usernameSuggestions' => $this->usernameSuggestions($user),
                'errors' => $errors,
                'old' => ['username' => $username, 'device_specialization' => $device,
                    'technician_level' => $level, 'experience' => $experience],
            ], 422, 'guest');
        }

        try {
            User::saveRegistrationStage2((int) $user['id'], $username, $level, $experience !== '' ? $experience : null, [$device]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return $this->view('auth/register-stage-2', [
                    'title' => 'إنشاء الحساب - المرحلة 2', 'authPage' => true,
                    'user' => $user, 'usernameSuggestions' => $this->usernameSuggestions($user),
                    'errors' => ['username' => 'اسم المستخدم مستخدم بالفعل، اختار اقتراحًا آخر'],
                    'old' => ['username' => $username, 'device_specialization' => $device,
                        'technician_level' => $level, 'experience' => $experience],
                ], 422, 'guest');
            }
            throw $e;
        }
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
            'title' => 'إنشاء الحساب - المرحلة 3', 'authPage' => true,
            'errors' => [], 'old' => [],
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
        $workshop = trim($this->text('workshop'));
        $biography = trim($this->text('biography'));
        $errors = [];
        if (mb_strlen($workshop) > 80) $errors['workshop'] = 'اسم الورشة لا يزيد عن 80 حرفًا';
        if (mb_strlen($biography) > 500) $errors['biography'] = 'النبذة لا تزيد عن 500 حرف';
        if ($errors) {
            return $this->view('auth/register-stage-3', [
                'title' => 'إنشاء الحساب - المرحلة 3', 'authPage' => true,
                'errors' => $errors, 'old' => ['workshop' => $workshop, 'biography' => $biography],
            ], 422, 'guest');
        }
        User::saveOptionalProfile((int) $user['id'], $workshop, $biography);
        $this->flash('success', 'اكتمل إعداد حسابك. أهلًا بك في Zayan Hardware.');
        return $this->redirect('/feed');
    }

    public function skipRegisterStage3(): Response
    {
        if (AuthService::user() === null) return $this->redirect('/register');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/register/stage/3');
        }
        $this->flash('success', 'تم إنشاء حسابك. تقدر تستكمل ملفك الشخصي لاحقًا.');
        return $this->redirect('/feed');
    }

    private function usernameSuggestions(array $user): array
    {
        $emailSeed = strstr((string) ($user['email'] ?? ''), '@', true);
        $seed = trim((string) ($user['display_name'] ?? '')) ?: (string) $emailSeed;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $seed);
        $base = strtolower((string) $ascii);
        $base = preg_replace('/[^a-z0-9]+/', '', $base) ?? '';
        if ($base === '') $base = 'tech';
        $suggestions = [];
        for ($i = 0; $i < 30 && count($suggestions) < 3; $i++) {
            $suffix = (string) random_int(10, 99999);
            $candidate = substr($base, 0, 20 - strlen($suffix)) . $suffix;
            if (!User::exists('username', $candidate) && !in_array($candidate, $suggestions, true)) $suggestions[] = $candidate;
        }
        while (count($suggestions) < 3) {
            $candidate = 'tech' . bin2hex(random_bytes(4));
            if (!User::exists('username', $candidate)) $suggestions[] = substr($candidate, 0, 20);
        }
        return $suggestions;
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
