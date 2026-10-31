<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\App;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\HelpRequest;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\HelpRequestMediaService;
use App\Services\RateLimiter;

final class HelpRequestController extends Controller
{
    public function attachment(int $id): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $attachment = HelpRequest::attachmentForParticipant($id, (int) $user['id']);
        if (!$attachment) return $this->notFound();
        $root = realpath(\App\Core\App::root('storage/private'));
        if ($root === false) return $this->notFound();
        $relative = ltrim(str_replace('\\', '/', (string) $attachment['file_path']), '/');
        if ($relative === '' || str_contains($relative, '..')) return $this->notFound();
        $file = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) return $this->notFound();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif','application/pdf'], true)) return $this->notFound();
        return Response::streamFile($file, 0, (int) filesize($file), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($file),
            'Content-Disposition' => 'inline; filename="help-attachment-' . $id . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

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
        HelpRequest::markRead($id, (int) $user['id']);
        $messages = HelpRequest::messages($id);
        foreach ($messages as &$message) $message['attachments'] = HelpRequest::attachmentsForMessage((int) $message['id']);
        unset($message);
        return $this->view('help-requests/show', [
            'title' => $request['subject'],
            'active' => 'messages',
            'currentUser' => $user,
            'helpRequest' => $request,
            'messages' => $messages,
            'publication' => HelpRequest::publicationForRequest($id),
            'isRequester' => (int) $request['requester_id'] === (int) $user['id'],
            'isHelper' => (int) $request['helper_id'] === (int) $user['id'],
        ]);
    }

    public function create(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/feed', 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!RateLimiter::allowForUser('help.create', (int) $user['id'], 3, 3600, 15)) {
            return $this->fail('/feed', 'أرسلت طلبات مساعدة كثيرة خلال فترة قصيرة. حاول مرة أخرى لاحقًا.');
        }
        $returnTo = $this->string('return_to');
        if (preg_match('~^/profile/[a-z0-9_]{3,20}$~', $returnTo) !== 1) $returnTo = '/feed';
        $helperId = filter_var($this->request->input('helper_id'), FILTER_VALIDATE_INT);
        $subject = trim($this->string('subject'));
        $body = trim($this->string('body'));
        try {
            $uploads = HelpRequestMediaService::validate($_FILES['attachments'] ?? null);
        } catch (\DomainException $error) {
            $message = match ($error->getMessage()) {
                'too_many_attachments' => 'الحد الأقصى 8 مرفقات في الطلب.',
                'invalid_attachment_size' => 'أحد المرفقات غير صالح أو أكبر من 8 ميجابايت.',
                'invalid_attachment_type' => 'المسموح صور JPG/PNG/WEBP/GIF أو ملفات PDF فقط.',
                'invalid_attachment_dimensions' => 'أبعاد إحدى الصور كبيرة جدًا. الحد الأقصى 8000 بكسل لكل بُعد و20 مليون بكسل إجماليًا.',
                'invalid_attachment_structure' => 'بيانات المرفقات غير سليمة. اختار الملفات مرة أخرى.',
                default => 'تعذر قراءة المرفقات أو أن بيانات الرفع غير متوافقة.',
            };
            return $this->fail($returnTo, $message);
        }
        if (!$helperId || mb_strlen($subject) < 4 || mb_strlen($subject) > 180
            || (mb_strlen($body) < 10 && !$uploads) || mb_strlen($body) > 5000) {
            return $this->fail($returnTo, 'اكتب عنوانًا واضحًا ووصفًا للمساعدة، أو أرفق صورة مع وصف مختصر.');
        }
        try {
            $created = HelpRequest::createWithInitialMessage((int) $user['id'], (int) $helperId, $subject, $body);
            $id = $created['request_id'];
            $failedUploads = 0;
            try {
                $failedUploads = HelpRequestMediaService::store(
                    $id,
                    $created['message_id'],
                    (int) $user['id'],
                    $uploads
                );
            } catch (\Throwable $mediaError) {
                // The request and first message are already committed. Report the
                // attachment problem without inviting the user to submit a duplicate.
                $failedUploads = count($uploads);
                App::logException('ERROR', 'help_request.initial_media_failed', $mediaError, [
                    'request_id' => $id,
                    'message_id' => $created['message_id'],
                    'user_id' => (int) $user['id'],
                ]);
            }
            NotificationService::notify(
                (int) $helperId, (int) $user['id'], null, null, 'personal_help_request',
                'طلب مساعدة شخصي جديد', $subject, '/help-requests/' . $id, 'personal-help-created:' . $id
            );
            $this->flash($failedUploads > 0 ? 'info' : 'success', $failedUploads > 0
                ? 'تم إرسال الطلب، لكن تعذر حفظ بعض المرفقات. افتح الطلب لمراجعة ما تم حفظه.'
                : 'تم إرسال طلب المساعدة. سيظهر لك عند رد العضو.');
            return $this->redirect('/help-requests/' . $id);
        } catch (\DomainException) {
            return $this->fail($returnTo, 'هذا العضو لا يستقبل طلبات المساعدة حاليًا، أو أن الطلب غير صالح.');
        } catch (\Throwable $error) {
            App::logException('ERROR', 'help_request.create_failed', $error, [
                'helper_id' => (int) $helperId,
                'requester_id' => (int) $user['id'],
            ]);
            return $this->fail($returnTo, 'تعذر تأكيد إنشاء الطلب. راجع قائمة طلبات المساعدة قبل محاولة الإرسال مرة أخرى.');
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
            // Resolve notification context before changing the request state.
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if (!$request) throw new \DomainException('request_not_pending');

            HelpRequest::respond($id, (int) $user['id'], $accept);
            try {
                HelpRequest::markRead($id, (int) $user['id']);
            } catch (\Throwable $readError) {
                App::logException('WARNING', 'help_request.read_marker_failed', $readError, [
                    'request_id' => $id,
                    'user_id' => (int) $user['id'],
                ]);
            }

            NotificationService::notify(
                (int) $request['requester_id'], (int) $user['id'], null, null,
                $accept ? 'personal_help_accepted' : 'personal_help_declined',
                $accept ? 'تم قبول طلب المساعدة' : 'تعذر قبول طلب المساعدة',
                $request['subject'], '/help-requests/' . $id, 'personal-help-response:' . $id
            );
            $this->flash($accept ? 'success' : 'info', $accept ? 'تم قبول الطلب وفتح المحادثة الخاصة.' : 'تم الاعتذار عن طلب المساعدة.');
        } catch (\DomainException) {
            $this->flash('error', 'الطلب لم يعد متاحًا للرد.');
        } catch (\Throwable $error) {
            App::logException('ERROR', 'help_request.respond_failed', $error, [
                'request_id' => $id,
                'user_id' => (int) $user['id'],
            ]);
            $this->flash('error', 'تعذر تأكيد حالة الطلب. افتح الطلب وتحقق من حالته قبل إعادة المحاولة.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

       public function send(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests/' . $id);
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!RateLimiter::allowForUser('help.message', (int) $user['id'], 24, 300, 100)) {
            return $this->fail('/help-requests/' . $id, 'أرسلت رسائل كثيرة خلال فترة قصيرة. انتظر قليلًا ثم حاول مرة أخرى.');
        }
        $body = trim($this->string('body'));
        try {
            $uploads = HelpRequestMediaService::validate($_FILES['attachments'] ?? null);
        } catch (\DomainException $error) {
            $message = match ($error->getMessage()) {
                'too_many_attachments' => 'الحد الأقصى 8 مرفقات في الرسالة.',
                'invalid_attachment_size' => 'أحد المرفقات أكبر من 8 ميجابايت أو غير صالح.',
                'invalid_attachment_type' => 'المسموح صور JPG/PNG/WEBP/GIF أو ملفات PDF فقط.',
                'invalid_attachment_dimensions' => 'أبعاد إحدى الصور كبيرة جدًا. الحد الأقصى 8000 بكسل لكل بُعد و20 مليون بكسل إجماليًا.',
                'invalid_attachment_structure' => 'بيانات المرفقات غير سليمة. اختار الملفات مرة أخرى.',
                default => 'تعذر قراءة المرفقات.',
            };
            return $this->fail('/help-requests/' . $id, $message);
        }
        if ((mb_strlen($body) < 1 && !$uploads) || mb_strlen($body) > 5000) {
            return $this->fail('/help-requests/' . $id, 'اكتب رسالة أو أرفق صورة، وبحد أقصى 5000 حرف.');
        }
        try {
            // Resolve the recipient before the message transaction commits. A
            // later lookup failure must not make an already-saved message look lost.
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if (!$request) throw new \DomainException('conversation_unavailable');
            $recipient = (int) $request['requester_id'] === (int) $user['id']
                ? (int) $request['helper_id'] : (int) $request['requester_id'];

            $messageId = HelpRequest::sendMessage($id, (int) $user['id'], $body);
            $failedUploads = 0;
            try {
                $failedUploads = HelpRequestMediaService::store($id, $messageId, (int) $user['id'], $uploads);
            } catch (\Throwable $mediaError) {
                $failedUploads = count($uploads);
                App::logException('ERROR', 'help_request.message_media_failed', $mediaError, [
                    'request_id' => $id,
                    'message_id' => $messageId,
                    'user_id' => (int) $user['id'],
                ]);
            }
            if ($failedUploads > 0) {
                $this->flash('info', 'تم إرسال الرسالة، لكن تعذر حفظ بعض المرفقات. افتح المحادثة لمراجعة ما تم حفظه.');
            } else {
                $this->flash('success', 'تم إرسال الرسالة بنجاح.');
            }
            NotificationService::notify(
                $recipient, (int) $user['id'], null, null, 'personal_help_message',
                'رسالة جديدة في طلب المساعدة', $request['subject'],
                '/help-requests/' . $id, 'personal-help-message:' . $messageId
            );
        } catch (\DomainException) {
            $this->flash('error', 'المحادثة مغلقة أو لا تملك صلاحية إرسال رسالة فيها.');
        } catch (\Throwable $error) {
            App::logException('ERROR', 'help_request.message_send_failed', $error, [
                'request_id' => $id,
                'user_id' => (int) $user['id'],
            ]);
            $this->flash('error', 'تعذر تأكيد إرسال الرسالة. افتح المحادثة وتحقق من آخر رسالة قبل إعادة الإرسال.');
        }
        return $this->redirect('/help-requests/' . $id);
    }

       public function close(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/help-requests/' . $id);
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        try {
            // The recipient and title are read before the state transition commits.
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if (!$request) throw new \DomainException('request_not_closable');
            $recipient = (int) $request['requester_id'] === (int) $user['id']
                ? (int) $request['helper_id'] : (int) $request['requester_id'];

            HelpRequest::close($id, (int) $user['id']);
            NotificationService::notify(
                $recipient, (int) $user['id'], null, null, 'personal_help_closed',
                'تم إغلاق طلب المساعدة', $request['subject'],
                '/help-requests/' . $id, 'personal-help-closed:' . $id
            );
            $this->flash('success', 'تم إغلاق طلب المساعدة. ستظل الرسائل محفوظة للطرفين.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن إغلاق هذا الطلب حاليًا.');
        } catch (\Throwable $error) {
            App::logException('ERROR', 'help_request.close_failed', $error, [
                'request_id' => $id,
                'user_id' => (int) $user['id'],
            ]);
            $this->flash('error', 'تعذر تأكيد إغلاق الطلب. افتحه وتحقق من حالته قبل إعادة المحاولة.');
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
            $request = HelpRequest::findForParticipant($id, (int) $user['id']);
            if (!$request) throw new \DomainException('publication_request_unavailable');

            $publicationId = HelpRequest::proposePublication(
                $id, (int) $user['id'], $title, $problem, $diagnosis, $solution, $showIdentity
            );
            NotificationService::notify(
                (int) $request['requester_id'], (int) $user['id'], null, null,
                'personal_help_publication_consent', 'طلب موافقة على نشر مساعدة فنية',
                $title, '/help-requests/' . $id, 'personal-help-publication:' . $publicationId
            );
            $this->flash('success', 'تم إرسال اقتراح النشر. لن يظهر أي شيء للعامة قبل موافقة الطرف الآخر.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن اقتراح نشر هذه المساعدة أو سبق إرسال اقتراح بشأنها.');
        } catch (\Throwable $error) {
            App::logException('ERROR', 'help_request.publication_proposal_failed', $error, [
                'request_id' => $id,
                'user_id' => (int) $user['id'],
            ]);
            $this->flash('error', 'تعذر تأكيد إرسال اقتراح النشر. راجع الطلب قبل إعادة المحاولة.');
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
        $user = AuthService::user();
        return $this->view('help-library/index', [
            'title' => 'مكتبة المساعدات الفنية',
            'active' => 'help-library',
            'posts' => HelpRequest::publicPosts(),
        ], 200, $user !== null ? 'app' : 'guest');
    }

    public function publicPost(int $id): Response
    {
        $user = AuthService::user();
        $post = HelpRequest::publicPost($id);
        if (!$post) return $this->notFound();
        return $this->view('help-library/show', [
            'title' => $post['title'],
            'active' => 'help-library',
            'post' => $post,
        ], 200, $user !== null ? 'app' : 'guest');
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
