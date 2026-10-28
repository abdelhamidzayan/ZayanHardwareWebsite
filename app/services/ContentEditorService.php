<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Issue;

final class ContentEditorService
{
    /** Validate upload metadata before the related post/comment is inserted. */
    public static function validateUploads(mixed $files, mixed $tokens): array
    {
        if ($files === null) return [];
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            throw new \DomainException('invalid_upload_structure');
        }
        if (count($files['name']) > 8) throw new \DomainException('too_many_uploads');

        $tokenList = is_array($tokens) ? $tokens : [];
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $uploads = [];
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $tmp = (string) ($files['tmp_name'][$index] ?? '');
            $size = (int) ($files['size'][$index] ?? 0);
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)
                || $size < 1 || $size > 8 * 1024 * 1024) {
                throw new \DomainException('invalid_upload_size');
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowed[$mime])) throw new \DomainException('invalid_upload_type');

            $token = $tokenList[$index] ?? ('upload-' . $index);
            if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $token)) {
                throw new \DomainException('invalid_upload_token');
            }
            $uploads[] = [
                'tmp' => $tmp, 'size' => $size, 'mime' => $mime,
                'ext' => $allowed[$mime], 'token' => $token,
            ];
        }
        return $uploads;
    }

    /**
     * Store validated uploads privately. Only media tokens actually present in the body
     * are marked inline; uninserted files remain ordinary attachments.
     *
     * @return array{map: array<string,int>, failed: int}
     */
    public static function storeUploads(int $issueId, int $userId, array $uploads, string $body): array
    {
        $referencedTokens = [];
        if (preg_match_all('/\[\[image:([A-Za-z0-9_-]{1,80})\]\]/', $body, $matches)) {
            foreach ($matches[1] as $token) $referencedTokens[$token] = true;
        }

        $map = [];
        $failed = 0;
        if (!$uploads) return ['map' => $map, 'failed' => 0];

        $privateRoot = App::root('storage/private');
        $targetDir = $privateRoot . DIRECTORY_SEPARATOR . 'issues' . DIRECTORY_SEPARATOR . $issueId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            return ['map' => [], 'failed' => count($uploads)];
        }

        foreach ($uploads as $upload) {
            $filename = bin2hex(random_bytes(16)) . '.' . $upload['ext'];
            $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($upload['tmp'], $destination)) {
                $failed++;
                continue;
            }
            $isInline = isset($referencedTokens[$upload['token']]);
            try {
                $attachmentId = Issue::addAttachment(
                    $issueId, $userId, 'issues/' . $issueId . '/' . $filename,
                    $upload['mime'], (int) $upload['size'], $isInline
                );
                if ($isInline) $map[$upload['token']] = $attachmentId;
            } catch (\Throwable $e) {
                @unlink($destination);
                App::log('error', 'Unable to save content media: ' . $e->getMessage());
                $failed++;
            }
        }
        return ['map' => $map, 'failed' => $failed];
    }

    /** Replace temporary editor tokens with database IDs; drop tokens for failed uploads. */
    public static function resolveInlineTokens(string $body, array $map): string
    {
        return (string) preg_replace_callback(
            '/\[\[image:([A-Za-z0-9_-]{1,80})\]\]/',
            static function (array $match) use ($map): string {
                $token = $match[1];
                if (isset($map[$token])) return '[[image:' . (int) $map[$token] . ']]';
                return ctype_digit($token) ? '[[image:' . (int) $token . ']]' : '';
            },
            $body
        );
    }

    /** Render only known attachment IDs and escaped text; arbitrary HTML is never trusted. */
    public static function renderInlineContent(string $body, array $attachments, string $baseUrl): string
    {
        $byId = [];
        foreach ($attachments as $attachment) $byId[(int) $attachment['id']] = $attachment;
        $parts = preg_split('/(\[\[image:\d+\]\])/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) return nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[\[image:(\d+)\]\]$/', $part, $match)) {
                $id = (int) $match[1];
                if (!isset($byId[$id])) continue;
                $mime = (string) ($byId[$id]['mime_type'] ?? '');
                $url = htmlspecialchars($baseUrl . '/attachments/' . $id, ENT_QUOTES, 'UTF-8');
                if (str_starts_with($mime, 'image/')) {
                    $html .= '<figure class="inline-media"><a href="' . $url . '" class="attachment-image-link" data-image-viewer data-image-src="' . $url . '" aria-label="تكبير الصورة"><img src="' . $url . '" alt="صورة فنية داخل النص" loading="lazy"></a></figure>';
                } elseif ($mime === 'application/pdf') {
                    $html .= '<p class="inline-media__document"><a href="' . $url . '" target="_blank" rel="noopener">عرض المخطط PDF</a></p>';
                }
                continue;
            }
            $html .= nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }

    /** Remove orphaned inline media after a body is edited; ordinary attachments are untouched. */
    public static function pruneUnusedInlineMedia(int $issueId): void
    {
        $referenced = [];
        foreach (Issue::allInlineContent($issueId) as $body) {
            if (preg_match_all('/\[\[image:(\d+)\]\]/', $body, $matches)) {
                foreach ($matches[1] as $id) $referenced[(int) $id] = true;
            }
        }

        $privateRoot = realpath(App::root('storage/private'));
        foreach (Issue::inlineAttachments($issueId) as $attachment) {
            $id = (int) $attachment['id'];
            if (isset($referenced[$id])) continue;
            if ($privateRoot !== false) {
                $relative = ltrim(str_replace('\\', '/', (string) $attachment['file_path']), '/');
                if (!str_contains($relative, '..')) {
                    $target = realpath($privateRoot . DIRECTORY_SEPARATOR . $relative);
                    if ($target !== false && str_starts_with($target, $privateRoot . DIRECTORY_SEPARATOR) && is_file($target)) {
                        @unlink($target);
                    }
                }
            }
            Issue::deleteInlineAttachment($issueId, $id);
        }
    }
}
