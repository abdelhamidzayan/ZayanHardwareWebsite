<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\HelpRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\NotificationService;

final class HelpRequestController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        return $this->view('help-requests/index', [
            'title' => 'طلبات المساعدة الشخصية',
            'active' => 'messages',
            'currentUser' => $user,
            'requests' => HelpRequest::forUser((int) $user['id']),
        ]);
    }

    public function show(int $id): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $request = HelpRequest::findForParticipant($id, (int) $user['id']);
        if (!$request) return $this->notFound();
        return $this->view('help-requests/show', [
            'title' => $request['subject'],
            'active' => 'messages',
            'currentUser' => $user,
            'helpRequest' => $request,
            'messages' => HelpRequest::messages($id),
            'isRequester' => (int) $request['requester_id'] === (int) $user['id'],
            'isHelper' => (int) $request['helper_id'] === (int) $user['id'],
        ]);
    }

    public function create(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/feed', 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $helperId = filter_var($this->request->input('helper_id'), FILTER_VALIDATE_INT);
        $subject = trim($this->string('subject'));
        $body = trim($this->string('body'));
        if (!$helperId || mb_strlen($subject) < 4 || mb_strlen($subject) > 180 || mb_strlen($body) < 10 || mb_strlen($body) > 5000) {
            return $this->fail('/feed', 'اكتب عنوانًا واضحًا ووصفًا للمساعدة من 10 إلى 5000 حرف.');
        }
        try {
            $id = HelpRequest::create((int) $user['id'], (int) $helperId, $subject, $body);
            NotificationService::notify(
                (int) $helperId, (int) $user['id'], null, null, 'personal_help_request',
                'طلب مساعدة شخصي جديد', $subject, '/help-requests/' . $id, 'personal-help-created:' . $id
            );
            $this->flash('success', 'تم إرسال طلب المساعدة. سيظهر لك عند رد العضو.');
            return $this->redirect('/help-requests/' . $id);
        } catch (\DomainException) {
            return $this->fail('/feed', 'هذا العضو لا يستقبل طلبات المساعدة حاليًا، أو أن الطلب غير صالح.');
        }
    }

    public function accept(int $id): Response
    {
        return $this->respond($id, true);
    }

    public function decline(int $id): Response
    {
        return $this->respond($id, false);
    }

    private function respond(int $id, bool $accept): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        try {
            HelpRequest::respond($id, (int) $user['id'], $accept);
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if ($request) {
                NotificationService::notify(
                    (int) $request['requester_id'], (int) $user['id'], null, null,
                    $accept ? 'personal_help_accepted' : 'personal_help_declined',
                    $accept ? 'تم قبول طلب المساعدة' : 'تعذر قبول طلب المساعدة',
                    $request['subject'], '/help-requests/' . $id, 'personal-help-response:' . $id
                );
            }
            $this->flash($accept ? 'success' : 'info', $accept ? 'تم قبول الطلب وفتح المحادثة الخاصة.' : 'تم الاعتذار عن طلب المساعدة.');
        } catch (\DomainException) {
            $this->flash('error', 'الطلب لم يعد متاحًا للرد.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

    public function send(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests/' . $id);
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $body = trim($this->string('body'));
        if (mb_strlen($body) < 1 || mb_strlen($body) > 5000) {
            return $this->fail('/help-requests/' . $id, 'اكتب رسالة من 1 إلى 5000 حرف.');
        }
        try {
            HelpRequest::sendMessage($id, (int) $user['id'], $body);
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if ($request) {
                $recipient = (int) $request['requester_id'] === (int) $user['id']
                    ? (int) $request['helper_id'] : (int) $request['requester_id'];
                NotificationService::notify(
                    $recipient, (int) $user['id'], null, null, 'personal_help_message',
                    'رسالة جديدة في طلب المساعدة', $request['subject'],
                    '/help-requests/' . $id, 'personal-help-message:' . $id . ':' . time()
                );
            }
        } catch (\DomainException) {
            $this->flash('error', 'المحادثة مغلقة أو لا تملك صلاحية إرسال رسالة فيها.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

    public function close(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests/' . $id);
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        try {
            HelpRequest::close($id, (int) $user['id']);
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if ($request) {
                $recipient = (int) $request['requester_id'] === (int) $user['id']
                    ? (int) $request['helper_id'] : (int) $request['requester_id'];
                NotificationService::notify(
                    $recipient, (int) $user['id'], null, null, 'personal_help_closed',
                    'تم إغلاق طلب المساعدة', $request['subject'],
                    '/help-requests/' . $id, 'personal-help-closed:' . $id
                );
            }
            $this->flash('success', 'تم إغلاق طلب المساعدة. ستظل الرسائل محفوظة للطرفين.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن إغلاق هذا الطلب حاليًا.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

    public function proposePublication(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests/' . $id);
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $title = trim($this->string('title'));
        $problem = trim($this->string('problem_summary'));
        $diagnosis = trim($this->string('diagnosis_summary'));
        $solution = trim($this->string('solution_summary'));
        $showIdentity = $this->request->input('helper_show_identity') === '1';
        if (mb_strlen($title) < 5 || mb_strlen($title) > 180
            || mb_strlen($problem) < 10 || mb_strlen($problem) > 5000
            || mb_strlen($diagnosis) < 10 || mb_strlen($diagnosis) > 5000
            || mb_strlen($solution) < 5 || mb_strlen($solution) > 5000) {
            return $this->fail('/help-requests/' . $id, 'اكتب ملخصًا فنيًا واضحًا للمشكلة والتشخيص والحل قبل إرسال اقتراح النشر.');
        }
        try {
            $publicationId = HelpRequest::proposePublication(
                $id, (int) $user['id'], $title, $problem, $diagnosis, $solution, $showIdentity
            );
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if ($request) {
                NotificationService::notify(
                    (int) $request['requester_id'], (int) $user['id'], null, null,
                    'personal_help_publication_consent', 'طلب موافقة على نشر مساعدة فنية',
                    $title, '/help-requests/' . $id, 'personal-help-publication:' . $publicationId
                );
            }
            $this->flash('success', 'تم إرسال اقتراح النشر. لن يظهر أي شيء للعامة قبل موافقة الطرف الآخر.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن اقتراح نشر هذه المساعدة أو سبق إرسال اقتراح بشأنها.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

    public function respondPublication(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $consent = $this->request->input('consent') === '1';
        $showIdentity = $consent && $this->request->input('show_identity') === '1';
        try {
            $publication = HelpRequest::publicationForRequester($id, (int) $user['id']);
            if (!$publication) throw new \DomainException('publication_not_pending');
            $postId = HelpRequest::respondPublication($id, (int) $user['id'], $consent, $showIdentity);
            $recipient = (int) $publication['helper_id'];
            NotificationService::notify(
                $recipient, (int) $user['id'], null, null,
                $consent ? 'personal_help_published' : 'personal_help_publication_declined',
                $consent ? 'تمت الموافقة على نشر المساعدة' : 'تم رفض نشر المساعدة',
                $publication['draft_title'],
                $consent && $postId ? '/help-library/' . $postId : '/help-requests/' . $publication['request_id'],
                'personal-help-publication-response:' . $id
            );
            $this->flash($consent ? 'success' : 'info', $consent
                ? 'تمت الموافقة ونشر المساعدة. ستظهر هويات من وافقوا على إظهارها فقط.'
                : 'تم رفض النشر. ستظل المحادثة محفوظة للطرفين فقط داخل الطلب المغلق.');
        } catch (\DomainException) {
            $this->flash('error', 'طلب الموافقة غير متاح أو تمت معالجته بالفعل.');
        }
        return $this->redirect('/help-requests');
    }

    public function library(): Response
    {
        return $this->view('help-library/index', [
            'title' => 'مكتبة المساعدات الفنية',
            'active' => 'help-library',
            'posts' => HelpRequest::publicPosts(),
        ]);
    }

    public function publicPost(int $id): Response
    {
        $post = HelpRequest::publicPost($id);
        if (!$post) return $this->notFound();
        return $this->view('help-library/show', [
            'title' => $post['title'],
            'active' => 'help-library',
            'post' => $post,
        ]);
    }
}
