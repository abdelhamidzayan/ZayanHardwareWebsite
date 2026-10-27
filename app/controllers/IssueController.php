<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Issue;
use App\Services\AuthService;

final class IssueController extends Controller
{
    public function show(int $id): Response
    {
        $issue = Issue::find($id);
        if (!$issue) return $this->notFound();
        $sort = $this->request->query['sort'] ?? 'relevant';
        $sort = is_string($sort) && in_array($sort, ['relevant', 'experts', 'reputation', 'helpful', 'newest'], true) ? $sort : 'relevant';
        $user = AuthService::user();
        $isOwner = $user !== null && (int) $user['id'] === (int) $issue['user_id'];

        return $this->view('issues/show', [
            'title' => $issue['title'],
            'active' => 'home',
            'issue' => $issue,
            'replies' => Issue::replies($id, $sort),
            'attachments' => Issue::attachments($id),
            'history' => $isOwner ? Issue::history($id) : [],
            'sort' => $sort,
            'isOwner' => $isOwner,
            'currentUser' => $user,
        ]);
    }

    public function reply(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $body = trim($this->string('body'));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) return $this->fail('/issues/' . $id, 'الرد يجب أن يكون بين حرفين و5000 حرف.');
        try {
            Issue::addReply($id, (int) $user['id'], $body);
            $this->flash('success', 'تم نشر ردك الفني.');
        } catch (\DomainException) {
            $this->flash('error', 'النقاش مغلق أو لم يعد متاحًا للرد.');
        }
        return $this->redirect('/issues/' . $id . '#discussion');
    }

    public function edit(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $title = trim($this->string('title'));
        $description = trim($this->string('description'));
        $measurements = trim($this->string('measurements'));
        if (mb_strlen($title) < 10 || mb_strlen($title) > 180 || mb_strlen($description) < 20 || mb_strlen($description) > 10000 || mb_strlen($measurements) > 5000) {
            return $this->fail('/issues/' . $id, 'راجع العنوان والوصف والقياسات. يوجد حقل خارج الحدود المسموحة.');
        }
        try {
            Issue::edit($id, (int) $user['id'], $title, $description, $measurements);
            $this->flash('success', 'تم تحديث المنشور وحفظ النسخة السابقة في سجل التعديلات.');
        } catch (\DomainException) {
            $this->flash('error', 'لا تملك صلاحية تعديل هذا المنشور أو أن النقاش مغلق.');
        }
        return $this->redirect('/issues/' . $id);
    }

    public function vote(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $replyId = filter_var($this->request->input('reply_id'), FILTER_VALIDATE_INT);
        if (!$replyId || $replyId < 1) return $this->fail('/issues/' . $id, 'الرد غير صحيح.');
        try {
            $issue = Issue::find($id);
            if (!$issue) return $this->notFound();
            Issue::vote((int) $replyId, (int) $user['id']);
            $this->flash('success', 'تم تحديث تقييم الإفادة.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكنك تقييم ردك أو أن الرد غير متاح.');
        }
        return $this->redirect('/issues/' . $id . '#discussion');
    }

    public function accept(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $replyId = filter_var($this->request->input('reply_id'), FILTER_VALIDATE_INT);
        if (!$replyId || $replyId < 1) return $this->fail('/issues/' . $id, 'الرد غير صحيح.');
        try {
            Issue::accept($id, (int) $replyId, (int) $user['id']);
            $this->flash('success', 'تم اعتماد الحل.');
        } catch (\DomainException) {
            $this->flash('error', 'اعتماد الحل متاح لصاحب المنشور فقط، ويجب أن يكون الرد تابعًا لنفس العطل.');
        }
        return $this->redirect('/issues/' . $id . '#discussion');
    }

    public function close(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $repaired = $this->string('was_repaired') === '1';
        $cause = trim($this->string('final_cause'));
        $action = trim($this->string('repair_action'));
        $replyRaw = $this->string('helpful_reply_id');
        $replyId = $replyRaw === '' ? null : filter_var($replyRaw, FILTER_VALIDATE_INT);
        if (mb_strlen($cause) < 10 || mb_strlen($cause) > 5000 || ($repaired && (mb_strlen($action) < 5 || mb_strlen($action) > 5000)) || ($replyRaw !== '' && (!$replyId || $replyId < 1))) {
            return $this->fail('/issues/' . $id, 'اكتب سبب العطل النهائي، وأضف إجراء الإصلاح إذا تم إصلاحه.');
        }
        try {
            Issue::closeWithResult($id, (int) $user['id'], $repaired, $cause, $action, $replyId ? (int) $replyId : null);
            $this->flash('success', 'تم إغلاق النقاش وإرسال النتيجة للمراجعة. لن تُضاف نقاط تلقائيًا قبل المراجعة.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن إغلاق هذا النقاش. تأكد أنك صاحبه وأنه ما زال مفتوحًا.');
        } catch (\PDOException $e) {
            $this->flash('error', 'تم إرسال نتيجة لهذا العطل من قبل، أو تعذر حفظ النتيجة.');
        }
        return $this->redirect('/issues/' . $id);
    }

    private function string(string $key): string
    {
        $value = $this->request->input($key);
        return is_string($value) ? $value : '';
    }

    private function fail(string $path, string $message): Response
    {
        $this->flash('error', $message);
        return $this->redirect($path);
    }
}
