<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\PostDraft;
use App\Services\AuthService;

final class DraftController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        return $this->view('drafts/index', [
            'title' => 'مسوداتي',
            'active' => 'profile',
            'currentUser' => $user,
            'drafts' => PostDraft::listForUser((int) $user['id']),
        ]);
    }

    public function save(): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) {
            return Response::json(['error' => 'csrf_failed', 'message' => 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.'], 419);
        }

        $payloadJson = $this->request->input('payload_json');
        if (!is_string($payloadJson) || strlen($payloadJson) > 120000) {
            return Response::json(['error' => 'payload_invalid', 'message' => 'حجم المسودة أكبر من المسموح.'], 422);
        }

        try {
            $payload = json_decode($payloadJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Response::json(['error' => 'payload_invalid', 'message' => 'بيانات المسودة غير صالحة.'], 422);
        }
        if (!is_array($payload)) {
            return Response::json(['error' => 'payload_invalid'], 422);
        }

        $payload = $this->normalizePayload($payload);
        $draftId = $this->request->input('draft_id');
        $draftId = is_string($draftId) && preg_match('/^[a-f0-9-]{36}$/i', $draftId) ? strtolower($draftId) : null;
        $revision = filter_var($this->request->input('revision', 0), FILTER_VALIDATE_INT);
        if ($revision === false || $revision < 0) {
            return Response::json(['error' => 'revision_invalid'], 422);
        }

        $result = PostDraft::save($draftId, (int) $user['id'], $payload, $revision);
        if ($result['conflict']) {
            return Response::json([
                'error' => 'revision_conflict',
                'message' => 'المسودة اتعدلت من جهاز آخر. احتفظنا بنسختك ولم نستبدل النسخة الأحدث.',
                'server' => $result,
            ], 409);
        }

        return Response::json([
            'id' => $result['id'],
            'revision' => $result['revision'],
            'saved_at' => gmdate('c'),
        ]);
    }

    public function upload(string $draftId): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) {
            return Response::json(['error' => 'csrf_failed'], 419);
        }
        $draft = PostDraft::findForUser($draftId, (int) $user['id']);
        if (!$draft) return Response::json(['error' => 'draft_not_found'], 404);

        $files = $_FILES['media'] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            return Response::json(['error' => 'file_missing'], 422);
        }

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
        $errors = is_array($files['error'] ?? null) ? $files['error'] : [$files['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];

        $existing = $draft['media'];
        if (count($existing) + count($names) > 8) {
            return Response::json(['error' => 'media_limit', 'message' => 'الحد الأقصى 8 مرفقات للمنشور.'], 422);
        }

        $existingAudio = count(array_filter($existing, static fn (array $item): bool => $item['media_kind'] === 'audio')) > 0;
        $privateRoot = App::root('storage/private/drafts/' . $draftId);
        if (!is_dir($privateRoot) && !mkdir($privateRoot, 0750, true) && !is_dir($privateRoot)) {
            return Response::json(['error' => 'storage_unavailable'], 500);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $saved = [];
        try {
            foreach ($names as $index => $name) {
                $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                $tmp = (string) ($tmpNames[$index] ?? '');
                $size = (int) ($sizes[$index] ?? 0);
                if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $size < 1) {
                    throw new \DomainException('upload_invalid');
                }

                $mime = $finfo->file($tmp) ?: '';
                $kind = '';
                $extension = '';
                $maxBytes = 0;
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $kind = 'image';
                    $extension = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'webp' };
                    $maxBytes = 12 * 1024 * 1024;
                    $imageInfo = @getimagesize($tmp);
                    if (!is_array($imageInfo) || (int) $imageInfo[0] > 12000 || (int) $imageInfo[1] > 12000) {
                        throw new \DomainException('image_dimensions_invalid');
                    }
                } elseif (in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true)) {
                    $kind = 'video';
                    $extension = match ($mime) { 'video/mp4' => 'mp4', 'video/webm' => 'webm', default => 'mov' };
                    $maxBytes = 50 * 1024 * 1024;
                } elseif (in_array($mime, ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/aac'], true)) {
                    $kind = 'audio';
                    $extension = match ($mime) {
                        'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3',
                        'audio/wav', 'audio/x-wav' => 'wav', 'audio/mp4' => 'm4a', default => 'aac'
                    };
                    $maxBytes = 25 * 1024 * 1024;
                } else {
                    throw new \DomainException('mime_invalid');
                }
                if ($size > $maxBytes) throw new \DomainException('file_too_large');
                if ($kind === 'audio' && ($existingAudio || count(array_filter($saved, static fn (array $item): bool => $item['kind'] === 'audio')) > 0)) {
                    throw new \DomainException('single_audio_only');
                }

                $uuid = PostDraft::newId();
                $filename = $uuid . '.' . $extension;
                $destination = $privateRoot . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($tmp, $destination)) throw new \DomainException('storage_failed');

                $sha256 = hash_file('sha256', $destination);
                if (!is_string($sha256)) {
                    @unlink($destination);
                    throw new \DomainException('storage_failed');
                }

                try {
                    PostDraft::addMedia(
                        $draftId,
                        $uuid,
                        $kind,
                        'drafts/' . $draftId . '/' . $filename,
                        $mime,
                        $size,
                        $sha256,
                        null,
                        count($existing) + count($saved)
                    );
                } catch (\Throwable $e) {
                    @unlink($destination);
                    throw $e;
                }

                $saved[] = [
                    'media_uuid' => $uuid,
                    'media_kind' => $kind,
                    'mime_type' => $mime,
                    'file_size' => $size,
                    'sort_order' => count($existing) + count($saved),
                    'url' => $this->mediaUrl($draftId, $uuid),
                ];
            }
        } catch (\DomainException $e) {
            $message = match ($e->getMessage()) {
                'mime_invalid' => 'نوع الملف غير مدعوم. استخدم صور JPG/PNG/WEBP أو فيديو MP4/WEBM أو تسجيلًا صوتيًا.',
                'file_too_large' => 'حجم الملف تجاوز الحد المسموح لنوعه.',
                'image_dimensions_invalid' => 'أبعاد الصورة غير صالحة أو كبيرة جدًا.',
                'single_audio_only' => 'مسموح بتسجيل صوتي واحد فقط لكل منشور.',
                'storage_unavailable', 'storage_failed' => 'تعذر حفظ الملف على الخادم.',
                default => 'تعذر رفع الملف. حاول مرة أخرى.',
            };
            return Response::json(['error' => $e->getMessage(), 'message' => $message], 422);
        }

        return Response::json(['media' => $saved], 201);
    }

    public function updateMediaEditor(string $draftId, string $mediaUuid): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) return Response::json(['error' => 'csrf_failed'], 419);

        $stateJson = $this->request->input('editor_state');
        if (!is_string($stateJson) || strlen($stateJson) > 350000) {
            return Response::json(['error' => 'editor_state_invalid'], 422);
        }
        try {
            $state = json_decode($stateJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Response::json(['error' => 'editor_state_invalid'], 422);
        }
        if (!is_array($state)) return Response::json(['error' => 'editor_state_invalid'], 422);

        $sortOrder = filter_var($this->request->input('sort_order', 0), FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 100) return Response::json(['error' => 'sort_order_invalid'], 422);

        if (!PostDraft::updateMediaEditorState($draftId, $mediaUuid, (int) $user['id'], $state, $sortOrder)) {
            return Response::json(['error' => 'media_not_found'], 404);
        }
        return Response::json(['saved' => true]);
    }

    public function deleteMedia(string $draftId, string $mediaUuid): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) return Response::json(['error' => 'csrf_failed'], 419);
        if (!PostDraft::deleteMedia($draftId, $mediaUuid, (int) $user['id'])) {
            return Response::json(['error' => 'media_not_found'], 404);
        }
        return Response::json(['deleted' => true]);
    }

    public function media(string $draftId, string $mediaUuid): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        $media = PostDraft::ownedMedia($draftId, $mediaUuid, (int) $user['id']);
        if (!$media) return $this->notFound();

        $root = realpath(App::root('storage/private'));
        if ($root === false) return $this->notFound();
        $file = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $media['storage_path']));
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return $this->notFound();
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp',
            'video/mp4', 'video/webm', 'video/quicktime',
            'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/aac',
        ];
        if (!in_array($mime, $allowed, true)) return $this->notFound();

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($file),
            'Content-Disposition' => 'inline; filename="draft-media-' . preg_replace('/[^a-f0-9-]/i', '', $mediaUuid) . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function delete(string $draftId): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) return Response::json(['error' => 'csrf_failed'], 419);
        if (!PostDraft::deleteForUser($draftId, (int) $user['id'])) {
            return Response::json(['error' => 'draft_not_found'], 404);
        }
        return Response::json(['deleted' => true]);
    }

    private function normalizePayload(array $payload): array
    {
        $stringFields = ['content_type', 'title', 'manufacturer_id', 'device_model_id', 'device_model_label', 'details_mode', 'description'];
        $clean = [];
        foreach ($stringFields as $field) {
            $value = $payload[$field] ?? '';
            $clean[$field] = is_scalar($value) ? trim((string) $value) : '';
        }
        if (!in_array($clean['content_type'], ['technical_post', 'help_request'], true)) $clean['content_type'] = 'technical_post';
        if (!in_array($clean['details_mode'], ['text', 'audio'], true)) $clean['details_mode'] = 'text';
        if (mb_strlen($clean['title']) > 180) $clean['title'] = mb_substr($clean['title'], 0, 180);
        if (mb_strlen($clean['description']) > 10000) $clean['description'] = mb_substr($clean['description'], 0, 10000);

        $steps = $payload['steps'] ?? [];
        if (!is_array($steps)) $steps = [];
        $clean['steps'] = [];
        foreach (array_slice($steps, 0, 50) as $step) {
            if (!is_string($step)) continue;
            $step = trim($step);
            if ($step !== '') $clean['steps'][] = mb_substr($step, 0, 2000);
        }
        $clean['updated_at_local'] = gmdate('c');
        return $clean;
    }

    private function mediaUrl(string $draftId, string $mediaUuid): string
    {
        return \App\Core\Request::basePath() . '/drafts/' . rawurlencode($draftId) . '/media/' . rawurlencode($mediaUuid);
    }
}
