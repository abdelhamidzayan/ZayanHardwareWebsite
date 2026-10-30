<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Issue;
use App\Services\ContentEditorService;
use App\Services\NotificationService;
use App\Services\AuthService;

final class IssueController extends Controller
{
    public function attachment(int $id): Response
    {
        if (!AuthService::user()) {
            return $this->redirect('/login');
        }

        $attachment = Issue::attachmentById($id);
        if (!$attachment) {
            return $this->notFound();
        }

        $privateRoot = realpath(\App\Core\App::root('storage/private'));
        if ($privateRoot === false) {
            return $this->notFound();
        }

        $storedPath = str_replace('\\', '/', (string) $attachment['file_path']);
        $storedPath = ltrim($storedPath, '/');
        if (str_starts_with($storedPath, 'storage/private/')) {
            $storedPath = substr($storedPath, strlen('storage/private/'));
        }
        if ($storedPath === '' || str_contains($storedPath, '..')) {
            return $this->notFound();
        }

        $target = realpath($privateRoot . DIRECTORY_SEPARATOR . $storedPath);
        if ($target === false || !str_starts_with($target, $privateRoot . DIRECTORY_SEPARATOR) || !is_file($target)) {
            return $this->notFound();
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($target) ?: 'application/octet-stream';
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm',
            'application/pdf',
        ];
        if (!in_array($mime, $allowed, true)) {
            return $this->notFound();
        }

        return new Response((string) file_get_contents($target), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($target),
            'Content-Disposition' => 'inline; filename="attachment-' . $id . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function create(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        if (!$this->request->isPost()) {
            $draftId = $this->request->query['draft'] ?? '';
            $draftId = is_string($draftId) && preg_match('/^[a-f0-9-]{36}$/i', $draftId) ? strtolower($draftId) : '';
            $draft = $draftId !== '' ? PostDraft::findForUser($draftId, (int) $user['id']) : null;
            return $this->view('issues/create', [
                'title' => 'إنشاء منشور',
                'active' => 'add',
                'currentUser' => $user,
                'manufacturers' => DeviceCatalog::manufacturers(),
                'draft' => $draft,
            ]);
        }

        if (!Csrf::verify($this->request->input('_token'))) {
            return $this->fail('/issues/create', 'انتهت صلاحية الصفحة. أعد المحاولة.');
        }

        $draftId = $this->request->input('draft_id');
        if (is_string($draftId) && preg_match('/^[a-f0-9-]{36}$/i', $draftId)) {
            $revision = filter_var($this->request->input('revision', 0), FILTER_VALIDATE_INT);
            if ($revision === false || $revision < 1) {
                return $this->fail('/issues/create', 'تعذر التحقق من آخر نسخة محفوظة للمسودة.');
            }
            try {
                $issueId = DraftPublishingService::publish(strtolower($draftId), (int) $user['id'], $revision);
                $this->flash('success', 'تم نشر المعلومة الفنية بنجاح.');
                return $this->redirect('/issues/' . $issueId);
            } catch (\\DomainException $e) {
                $message = match ($e->getMessage()) {
                    'draft_revision_conflict' => 'المسودة اتعدلت من جهاز آخر. افتح المسودة وراجع آخر نسخة قبل النشر.',
                    'draft_not_found' => 'المسودة غير موجودة أو لم تعد متاحة.',
                    'device_model_unavailable' => 'الموديل لم يعد متاحًا. اختار موديلًا معتمدًا من القائمة.',
                    'device_manufacturer_unavailable' => 'الشركة المصنعة غير متاحة حاليًا.',
                    'draft_audio_required_or_conflict' => 'راجع التسجيل الصوتي: مطلوب تسجيل واحد فقط عند اختيار التسجيل، ولا يقبل مع الكتابة.',
                    'draft_mode_conflict' => 'اختار الكتابة أو التسجيل الصوتي فقط.',
                    'draft_text_invalid' => 'اكتب تفاصيل واضحة لا تقل عن 10 أحرف.',
                    'draft_media_missing' => 'أحد المرفقات غير موجود على الخادم. أعد مزامنته قبل النشر.',
                    default => 'راجع بيانات المنشور والمرفقات وحاول مرة أخرى.',
                };
                return $this->fail('/issues/create?draft=' . rawurlencode($draftId), $message);
            }
        }

        $type = $this->string('content_type');
        $title = trim($this->string('title'));
        $description = trim($this->string('description'));
        $deviceModel = trim($this->string('device_model'));
        $category = trim($this->string('category'));
        $measurements = trim($this->string('measurements'));

        if (!in_array($type, ['help_request', 'technical_post', 'discussion'], true)) {
            return $this->fail('/issues/create', 'اختار نوع المنشور.');
        }
        if (mb_strlen($title) < 5 || mb_strlen($title) > 180
            || mb_strlen($description) < 10 || mb_strlen($description) > 10000
            || mb_strlen($measurements) > 5000) {
            return $this->fail('/issues/create', 'راجع العنوان والوصف والقياسات؛ يوجد حقل خارج الحدود المسموحة.');
        }
        if ($type === 'help_request'
            && ($deviceModel === '' || mb_strlen($deviceModel) > 120 || $category === '' || mb_strlen($category) > 40
                || mb_strlen($measurements) < 3)) {
            return $this->fail('/issues/create', 'طلب المساعدة يحتاج موديل الجهاز والتخصص والقياسات أو الاختبارات التي جربتها.');
        }

        try {
            $uploads = ContentEditorService::validateUploads(
                $_FILES['attachments'] ?? null,
                $this->request->input('attachment_tokens', [])
            );
        } catch (\DomainException $e) {
            $message = match ($e->getMessage()) {
                'too_many_uploads' => 'الحد الأقصى 8 مرفقات للمنشور الواحد.',
                'invalid_upload_size' => 'أحد المرفقات غير صالح أو أكبر من 8 ميجابايت.',
                'invalid_upload_type' => 'المرفقات المسموحة صور JPG/PNG/WEBP/GIF أو PDF فقط.',
                default => 'تعذر قراءة المرفقات. حاول اختيار الملفات مرة أخرى.',
            };
            return $this->fail('/issues/create', $message);
        }

        $issueId = Issue::create(
            (int) $user['id'],
            $type,
            $title,
            $type === 'help_request' ? $deviceModel : '',
            $type === 'help_request' ? $category : '',
            $description,
            $type === 'help_request' ? $measurements : ''
        );

        $stored = ContentEditorService::storeUploads($issueId, (int) $user['id'], $uploads, $description);
        $resolvedDescription = ContentEditorService::resolveInlineTokens($description, $stored['map']);
        Issue::updateDescriptionContent($issueId, (int) $user['id'], $resolvedDescription);
        ContentEditorService::pruneUnusedInlineMedia($issueId);

        if ((int) $stored['failed'] > 0) {
            $this->flash('info', 'تم نشر المنشور، لكن تعذر حفظ بعض المرفقات. يمكنك إضافتها مرة أخرى.');
        } else {
            $this->flash('success', 'تم نشر المنشور بنجاح.');
        }
        return $this->redirect('/issues/' . $issueId);
    }

    public function deleteIssue(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        try {
            Issue::deleteIssue($id, (int) $user['id']);
            $this->flash('success', 'تم إخفاء المنشور من المنصة. تم الاحتفاظ بسجله الفني وروابطه الداخلية.');
            return $this->redirect('/my-posts');
        } catch (\DomainException) {
            return $this->fail('/issues/' . $id, 'لا تملك صلاحية حذف هذا المنشور أو أنه محذوف بالفعل.');
        }
    }

    public function show(int $id): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $issue = Issue::find($id);
        if (!$issue) return $this->notFound();
        $sort = $this->request->query['sort'] ?? 'relevant';
        $sort = is_string($sort) && in_array($sort, ['relevant', 'experts', 'reputation', 'helpful', 'newest'], true) ? $sort : 'relevant';
        $isOwner = (int) $user['id'] === (int) $issue['user_id'];

        return $this->view('issues/show', [
            'title' => $issue['title'],
            'active' => 'home',
            'issue' => $issue,
            'replies' => Issue::replies($id, $sort),
            'attachments' => Issue::attachments($id),
            'diagnosticUpdates' => Issue::diagnosticUpdates($id),
            'history' => $isOwner ? Issue::history($id) : [],
            'sort' => $sort,
            'isOwner' => $isOwner,
            'currentUser' => $user,
        ]);
    }

    public function diagnosticUpdate(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $body = trim($this->string('body'));
        if (mb_strlen($body) < 3 || mb_strlen($body) > 5000) {
            return $this->fail('/issues/' . $id, 'تحديث التشخيص يجب أن يكون بين 3 و5000 حرف.');
        }
        try {
            $uploads = ContentEditorService::validateUploads($_FILES['attachments'] ?? null, $this->request->input('attachment_tokens', []));
        } catch (\DomainException $e) {
            return $this->fail('/issues/' . $id, 'راجع المرفقات: الحد الأقصى 8 ملفات صور أو PDF، كل ملف حتى 8 ميجابايت.');
        }
        try {
            $updateId = Issue::addDiagnosticUpdate($id, (int) $user['id'], $body);
            $stored = ContentEditorService::storeUploads($id, (int) $user['id'], $uploads, $body);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $stored['map']);
            Issue::updateDiagnosticUpdateContent($updateId, (int) $user['id'], $resolvedBody);
            ContentEditorService::pruneUnusedInlineMedia($id);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'diagnostic_update', 'تحديث جديد للتشخيص',
                $resolvedBody, 'diagnostic-update:' . $updateId
            );
            $this->flash((int) $stored['failed'] > 0 ? 'info' : 'success',
                (int) $stored['failed'] > 0 ? 'تمت إضافة التحديث، لكن تعذر حفظ بعض المرفقات.' : 'تمت إضافة تحديث التشخيص مع حفظ توقيته.');
        } catch (\DomainException) {
            $this->flash('error', 'تحديث التشخيص متاح لصاحب طلب المساعدة فقط ما دام الطلب غير مغلق.');
        }
        return $this->redirect('/issues/' . $id . '#diagnostic-updates');
    }

    public function editDiagnosticUpdate(int $id, string $updateId): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $parsedId = filter_var($updateId, FILTER_VALIDATE_INT);
        $body = trim($this->string('body'));
        if (!$parsedId || $parsedId < 1 || mb_strlen($body) < 3 || mb_strlen($body) > 5000) {
            return $this->fail('/issues/' . $id . '#diagnostic-updates', 'راجع نص تحديث التشخيص.');
        }
        try {
            $uploads = ContentEditorService::validateUploads($_FILES['attachments'] ?? null, $this->request->input('attachment_tokens', []));
        } catch (\DomainException) {
            return $this->fail('/issues/' . $id . '#diagnostic-updates', 'راجع المرفقات وحاول مرة أخرى.');
        }
        try {
            Issue::editDiagnosticUpdate($id, (int) $parsedId, (int) $user['id'], $body);
            $stored = ContentEditorService::storeUploads($id, (int) $user['id'], $uploads, $body);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $stored['map']);
            Issue::updateDiagnosticUpdateContent((int) $parsedId, (int) $user['id'], $resolvedBody);
            ContentEditorService::pruneUnusedInlineMedia($id);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'diagnostic_update', 'تم تعديل تحديث التشخيص',
                $resolvedBody, 'diagnostic-update-edited:' . (int) $parsedId . ':' . time()
            );
            $this->flash((int) $stored['failed'] > 0 ? 'info' : 'success',
                (int) $stored['failed'] > 0 ? 'تم تعديل التحديث، لكن تعذر حفظ بعض المرفقات.' : 'تم تعديل تحديث التشخيص وحفظ نسخته السابقة.');
        } catch (\DomainException) {
            $this->flash('error', 'يمكن لصاحب طلب المساعدة تعديل تحديثاته فقط أثناء استمرار الطلب.');
        }
        return $this->redirect('/issues/' . $id . '#diagnostic-updates');
    }

    public function deleteDiagnosticUpdate(int $id, string $updateId): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $parsedId = filter_var($updateId, FILTER_VALIDATE_INT);
        if (!$parsedId || $parsedId < 1) return $this->fail('/issues/' . $id, 'تحديث التشخيص غير صحيح.');
        try {
            Issue::deleteDiagnosticUpdate($id, (int) $parsedId, (int) $user['id']);
            ContentEditorService::pruneUnusedInlineMedia($id);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'diagnostic_update', 'تم تحديث سجل التشخيص',
                'حذف صاحب الطلب أحد تحديثات التشخيص.', 'diagnostic-update-deleted:' . (int) $parsedId
            );
            $this->flash('success', 'تم حذف تحديث التشخيص من العرض مع الاحتفاظ بسجل التعديلات.');
        } catch (\DomainException) {
            $this->flash('error', 'يمكن لصاحب طلب المساعدة حذف تحديثاته فقط أثناء استمرار الطلب.');
        }
        return $this->redirect('/issues/' . $id . '#diagnostic-updates');
    }

    public function confirmRepair(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $cause = trim($this->string('final_cause'));
        $action = trim($this->string('repair_action'));
        if (mb_strlen($cause) < 10 || mb_strlen($cause) > 5000 || mb_strlen($action) < 5 || mb_strlen($action) > 5000) {
            return $this->fail('/issues/' . $id, 'اكتب السبب النهائي للعطل وإجراء الإصلاح قبل تأكيد نجاحه.');
        }
        try {
            Issue::confirmRepair($id, (int) $user['id'], $cause, $action);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'repair_confirmed', 'تم تأكيد نجاح الإصلاح',
                'أكد صاحب الطلب نجاح الإصلاح، وأُغلق طلب المساعدة.', 'repair-confirmed:' . $id . ':' . time()
            );
            $this->flash('success', 'أكدت نجاح الإصلاح. أُغلق الطلب بعد اعتماد الرد وتسجيل النتيجة للمراجعة.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن تأكيد الإصلاح إلا لصاحب الطلب بعد اعتماد رد صالح.');
        }
        return $this->redirect('/issues/' . $id);
    }

    public function rejectRepairConfirmation(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $reason = trim($this->string('reason'));
        if (mb_strlen($reason) > 2000) return $this->fail('/issues/' . $id, 'سبب عدم نجاح الإصلاح أطول من المسموح.');
        try {
            Issue::rejectRepairConfirmation($id, (int) $user['id'], $reason);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'status_changed', 'الطلب رجع للتشخيص',
                $reason !== '' ? $reason : 'صاحب الطلب أكد أن الإصلاح لم ينجح بعد.', 'repair-rejected:' . $id . ':' . time()
            );
            $this->flash('info', 'رجع الطلب إلى قيد التشخيص، ويمكنك إضافة قياسات وتجارب جديدة.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن رفض التأكيد إلا لصاحب الطلب عندما يكون بانتظار تأكيد الإصلاح.');
        }
        return $this->redirect('/issues/' . $id . '#diagnostic-updates');
    }

    public function editReply(int $id, string $replyId): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $parsedReplyId = filter_var($replyId, FILTER_VALIDATE_INT);
        $body = trim($this->string('body'));
        if (!$parsedReplyId || $parsedReplyId < 1 || Issue::replyIssueId((int) $parsedReplyId) !== $id
            || mb_strlen($body) < 2 || mb_strlen($body) > 5000) {
            return $this->fail('/issues/' . $id, 'راجع نص التعليق وتأكد أنه تابع لنفس المنشور.');
        }
        try {
            $uploads = ContentEditorService::validateUploads($_FILES['attachments'] ?? null, $this->request->input('attachment_tokens', []));
        } catch (\DomainException) {
            return $this->fail('/issues/' . $id, 'راجع المرفقات وحاول مرة أخرى.');
        }
        try {
            Issue::editReply((int) $parsedReplyId, (int) $user['id'], $body);
            $stored = ContentEditorService::storeUploads($id, (int) $user['id'], $uploads, $body);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $stored['map']);
            Issue::updateReplyContent((int) $parsedReplyId, (int) $user['id'], $resolvedBody);
            ContentEditorService::pruneUnusedInlineMedia($id);
            $this->flash((int) $stored['failed'] > 0 ? 'info' : 'success',
                (int) $stored['failed'] > 0 ? 'تم تعديل التعليق، لكن تعذر حفظ بعض المرفقات.' : 'تم تعديل تعليقك وحفظ النسخة السابقة في السجل.');
        } catch (\DomainException) {
            $this->flash('error', 'يمكنك تعديل تعليقك فقط أثناء استمرار النقاش.');
        }
        return $this->redirect('/issues/' . $id . '#reply-' . (int) $parsedReplyId);
    }

    public function deleteReply(int $id, string $replyId): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $parsedReplyId = filter_var($replyId, FILTER_VALIDATE_INT);
        if (!$parsedReplyId || $parsedReplyId < 1 || Issue::replyIssueId((int) $parsedReplyId) !== $id) {
            return $this->fail('/issues/' . $id, 'التعليق غير صحيح.');
        }
        try {
            Issue::deleteReply((int) $parsedReplyId, (int) $user['id']);
            ContentEditorService::pruneUnusedInlineMedia($id);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'status_changed', 'تم تحديث النقاش',
                'تم حذف تعليق بواسطة صاحبه. إذا كان هو الحل المعتمد، عاد الطلب إلى قيد التشخيص.',
                'reply-deleted:' . (int) $parsedReplyId
            );
            $this->flash('success', 'تم حذف التعليق من النقاش.');
        } catch (\DomainException) {
            $this->flash('error', 'يمكنك حذف تعليقك فقط أثناء استمرار النقاش.');
        }
        return $this->redirect('/issues/' . $id . '#discussion');
    }

    public function reply(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        $body = trim($this->string('body'));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) return $this->fail('/issues/' . $id, 'الرد يجب أن يكون بين حرفين و5000 حرف.');
        $parentRaw = $this->string('parent_reply_id');
        $parentReplyId = $parentRaw === '' ? null : filter_var($parentRaw, FILTER_VALIDATE_INT);
        if ($parentRaw !== '' && (!$parentReplyId || $parentReplyId < 1 || Issue::replyIssueId((int) $parentReplyId) !== $id)) {
            return $this->fail('/issues/' . $id, 'التعليق الذي تحاول الرد عليه غير صحيح.');
        }
        try {
            $uploads = ContentEditorService::validateUploads($_FILES['attachments'] ?? null, $this->request->input('attachment_tokens', []));
        } catch (\DomainException) {
            return $this->fail('/issues/' . $id, 'راجع المرفقات وحاول مرة أخرى.');
        }
        try {
            $parentAuthorId = $parentReplyId ? Issue::replyAuthor((int) $parentReplyId) : null;
            $replyId = Issue::addReply($id, (int) $user['id'], $body, $parentReplyId ? (int) $parentReplyId : null);
            $stored = ContentEditorService::storeUploads($id, (int) $user['id'], $uploads, $body);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $stored['map']);
            Issue::updateReplyContent($replyId, (int) $user['id'], $resolvedBody);
            ContentEditorService::pruneUnusedInlineMedia($id);
            $issue = Issue::find($id);
            $replyTitle = $issue && $issue['content_type'] === 'help_request' ? 'رد جديد على طلب المساعدة' : 'تعليق جديد على مشاركتك';

            if ($parentReplyId && $parentAuthorId) {
                NotificationService::notify(
                    $parentAuthorId, (int) $user['id'], $id, $replyId, 'reply_to_comment',
                    'رد جديد على تعليقك', $resolvedBody,
                    '/issues/' . $id . '#reply-' . (int) $parentReplyId,
                    'reply-to-comment:' . $replyId
                );
                if ($issue && (int) $issue['user_id'] !== (int) $parentAuthorId) NotificationService::notify(
                    (int) $issue['user_id'], (int) $user['id'], $id, $replyId, 'new_reply',
                    $replyTitle, $resolvedBody, '/issues/' . $id . '#discussion',
                    'new-reply:' . $replyId
                );
            } else {
                NotificationService::notifyIssueParticipants(
                    $id, (int) $user['id'], 'new_reply', $replyTitle,
                    $resolvedBody, 'new-reply:' . $replyId
                );
            }
            $this->flash((int) $stored['failed'] > 0 ? 'info' : 'success',
                (int) $stored['failed'] > 0 ? 'تم نشر الرد، لكن تعذر حفظ بعض المرفقات.' : 'تم نشر ردك الفني.');
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
        if (mb_strlen($title) < 10 || mb_strlen($title) > 180 || mb_strlen($description) < 20
            || mb_strlen($description) > 10000 || mb_strlen($measurements) > 5000) {
            return $this->fail('/issues/' . $id, 'راجع العنوان والوصف والقياسات. يوجد حقل خارج الحدود المسموحة.');
        }
        try {
            $uploads = ContentEditorService::validateUploads($_FILES['attachments'] ?? null, $this->request->input('attachment_tokens', []));
        } catch (\DomainException) {
            return $this->fail('/issues/' . $id, 'راجع المرفقات وحاول مرة أخرى.');
        }
        try {
            Issue::edit($id, (int) $user['id'], $title, $description, $measurements);
            $stored = ContentEditorService::storeUploads($id, (int) $user['id'], $uploads, $description);
            $resolvedDescription = ContentEditorService::resolveInlineTokens($description, $stored['map']);
            Issue::updateDescriptionContent($id, (int) $user['id'], $resolvedDescription);
            ContentEditorService::pruneUnusedInlineMedia($id);
            $this->flash((int) $stored['failed'] > 0 ? 'info' : 'success',
                (int) $stored['failed'] > 0 ? 'تم تحديث المنشور، لكن تعذر حفظ بعض المرفقات.' : 'تم تحديث المنشور وحفظ النسخة السابقة في سجل التعديلات.');
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
            if (Issue::replyIssueId((int) $replyId) !== $id) {
                return $this->fail('/issues/' . $id, 'الرد لا يتبع هذا المنشور.');
            }
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
            $acceptedAuthorId = Issue::replyAuthor((int) $replyId);
            if ($acceptedAuthorId !== null) {
                NotificationService::notify(
                    $acceptedAuthorId, (int) $user['id'], $id, (int) $replyId,
                    'accepted_solution', 'تم اعتماد ردك كحل',
                    'اعتمد صاحب طلب المساعدة ردك كحل مقترح، والطلب بانتظار تأكيد نجاح الإصلاح.',
                    '/issues/' . $id . '#reply-' . (int) $replyId,
                    'accepted-solution:' . (int) $replyId . ':' . time()
                );
            }
            foreach (Issue::participantIds($id) as $recipientId) {
                if ($recipientId === (int) $user['id'] || $recipientId === $acceptedAuthorId) continue;
                NotificationService::notify(
                    $recipientId, (int) $user['id'], $id, (int) $replyId,
                    'status_changed', 'تم اعتماد حل مقترح',
                    'اختار صاحب الطلب ردًا كحل مقترح. الطلب الآن بانتظار تأكيد نجاح الإصلاح.',
                    '/issues/' . $id,
                    'accepted-status:' . (int) $replyId . ':' . $recipientId . ':' . time()
                );
            }
            $this->flash('success', 'تم اعتماد الرد. الطلب الآن بانتظار تأكيد نجاح الإصلاح.');
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
        if ($repaired) return $this->fail('/issues/' . $id, 'لا يمكن تسجيل الإصلاح كمنتهٍ إلا بعد اعتماد رد فني وتأكيد نجاح الإصلاح.');
        $cause = trim($this->string('final_cause'));
        $action = trim($this->string('repair_action'));
        $replyRaw = $this->string('helpful_reply_id');
        $replyId = $replyRaw === '' ? null : filter_var($replyRaw, FILTER_VALIDATE_INT);
        if (mb_strlen($cause) < 10 || mb_strlen($cause) > 5000 || ($repaired && (mb_strlen($action) < 5 || mb_strlen($action) > 5000)) || ($replyRaw !== '' && (!$replyId || $replyId < 1))) {
            return $this->fail('/issues/' . $id, 'اكتب سبب العطل النهائي، وأضف إجراء الإصلاح إذا تم إصلاحه.');
        }
        try {
            Issue::closeWithResult($id, (int) $user['id'], $repaired, $cause, $action, $replyId ? (int) $replyId : null);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'status_changed', 'أُغلق طلب المساعدة دون حل',
                'سجّل صاحب الطلب أن العطل لم يُحل بعد.', 'closed-unresolved:' . $id . ':' . time()
            );
            $this->flash('success', 'تم تسجيل أن العطل لم يُحل وإرسال النتيجة للمراجعة.');
        } catch (\DomainException) {
            $this->flash('error', 'لا يمكن إغلاق هذا النقاش. تأكد أنك صاحبه وأنه ما زال مفتوحًا.');
        } catch (\PDOException $e) {
            $this->flash('error', 'تم إرسال نتيجة لهذا العطل من قبل، أو تعذر حفظ النتيجة.');
        }
        return $this->redirect('/issues/' . $id);
    }



    public function reviewResult(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!in_array($user['role'] ?? '', ['admin', 'moderator'], true)) {
            return $this->fail('/issues/' . $id, 'هذه العملية متاحة للإدارة والمشرفين فقط.');
        }
        $resultId = filter_var($this->request->input('result_id'), FILTER_VALIDATE_INT);
        $decision = $this->string('decision');
        if (!$resultId || $resultId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            return $this->fail('/issues/' . $id, 'بيانات المراجعة غير صحيحة.');
        }
        $issue = Issue::find($id);
        if (!$issue) return $this->notFound();
        try {
            Issue::reviewResult($id, (int) $resultId, (int) $user['id'], $decision === 'approve');
            NotificationService::notify(
                (int) $issue['user_id'], (int) $user['id'], $id, null, 'result_reviewed',
                $decision === 'approve' ? 'تم اعتماد نتيجة الإصلاح' : 'لم يتم اعتماد نتيجة الإصلاح',
                $decision === 'approve' ? 'راجعت الإدارة النتيجة واعتمدتها.' : 'راجعت الإدارة النتيجة ولم تعتمدها.',
                '/issues/' . $id, 'result-reviewed:' . (int) $resultId . ':' . $decision
            );
            $this->flash('success', $decision === 'approve' ? ((int) ($issue['latest_was_repaired'] ?? 0) === 1 ? 'تم اعتماد النتيجة وإضافة نقاط السمعة مرة واحدة.' : 'تم اعتماد النتيجة دون إضافة نقاط سمعة لأن العطل لم يُحل.') : 'تم رفض النتيجة. لم تُضف نقاط سمعة.');
        } catch (\DomainException) {
            $this->flash('error', 'النتيجة غير موجودة أو تمت مراجعتها من قبل.');
        }
        return $this->redirect('/issues/' . $id);
    }

    public function reopen(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/issues/' . $id, 'انتهت صلاحية الصفحة. أعد المحاولة.');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        try {
            Issue::reopen($id, (int) $user['id']);
            NotificationService::notifyIssueParticipants(
                $id, (int) $user['id'], 'status_changed', 'أُعيد فتح طلب المساعدة',
                'أعاد صاحب الطلب فتح النقاش لمتابعة التشخيص.', 'reopened:' . $id . ':' . time()
            );
            $this->flash('success', 'تم فتح النقاش من جديد. يمكنك إضافة آخر تحديث واستقبال ردود جديدة.');
        } catch (\DomainException) {
            $this->flash('error', 'يمكن إعادة فتح النقاش لصاحبه فقط بعد مراجعة النتيجة السابقة من الإدارة.');
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
