<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\SkillTestService;

final class SkillTestController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $tests = SkillTestService::activeTests();
        foreach ($tests as &$test) {
            $test['state'] = SkillTestService::attemptState(
                (int) $user['id'], (int) $test['id'],
                (int) $test['max_attempts'], (int) $test['cooldown_minutes']
            );
        }
        unset($test);

        return $this->view('skills/tests', ['title' => 'اختبارات المهارات', 'active' => '', 'tests' => $tests]);
    }

    public function show(int $id): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $test = SkillTestService::findActive($id);
        if (!$test) return $this->notFound();

        $state = SkillTestService::attemptState(
            (int) $user['id'], $id, (int) $test['max_attempts'], (int) $test['cooldown_minutes']
        );

        return $this->view('skills/test', [
            'title' => $test['title'],
            'active' => '',
            'test' => $test,
            'state' => $state,
        ]);
    }

    public function submit(int $id): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/skills/tests/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');

        $raw = $this->request->input('answers', []);
        $answers = is_array($raw) ? $raw : [];
        try {
            $result = SkillTestService::submit((int) $user['id'], $id, $answers);
            $message = $result['passed']
                ? 'اجتزت الاختبار بنجاح. الدرجة: ' . $result['score'] . '%'
                : 'لم تصل إلى درجة النجاح. نتيجتك: ' . $result['score'] . '%. راجع المجال وحاول بعد فترة الانتظار.';
            $this->flash($result['passed'] ? 'success' : 'info', $message);
        } catch (\DomainException $e) {
            $messages = [
                'test_unavailable' => 'الاختبار غير متاح حاليًا.',
                'max_attempts' => 'استهلكت عدد المحاولات المسموح به لهذا الإصدار من الاختبار.',
                'cooldown' => 'لم تنتهِ فترة الانتظار بين المحاولات بعد.',
                'invalid_test' => 'تعذر تصحيح هذا الاختبار بسبب خلل في إعداداته.',
            ];
            return $this->fail('/skills/tests/' . $id, $messages[$e->getMessage()] ?? 'تعذر إرسال الإجابات.');
        }

        return $this->redirect('/skills/tests/' . $id);
    }

    private function fail(string $path, string $message): Response
    {
        $this->flash('error', $message);
        return $this->redirect($path);
    }
}
