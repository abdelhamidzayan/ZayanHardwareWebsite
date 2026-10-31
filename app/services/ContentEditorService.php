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
        if (!is_array($files) || !isset($files['name'], $files['error'], $files['tmp_name'], $files['size'])
            || !is_array($files['name']) || !is_array($files['error'])
            || !is_array($files['tmp_name']) || !is_array($files['size'])) {
            throw new \DomainException('invalid_upload_structure');
        }
        $fileCount = count($files['name']);
        if (count($files['error']) !== $fileCount || count($files['tmp_name']) !== $fileCount
            || count($files['size']) !== $fileCount) {
            throw new \DomainException('invalid_upload_structure');
        }
        foreach (array_keys($files['name']) as $index) {
            if (!array_key_exists($index, $files['error'])
                || !array_key_exists($index, $files['tmp_name'])
                || !array_key_exists($index, $files['size'])) {
                throw new \DomainException('invalid_upload_structure');
            }
        }
        if ($fileCount > 8) throw new \DomainException('too_many_uploads');

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
        $seenTokens = [];
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
            if (str_starts_with($mime, 'image/')) {
                try {
                    ImageUploadPolicy::inspect($tmp);
                } catch (\DomainException $error) {
                    throw new \DomainException('invalid_image_dimensions', 0, $error);
                }
            }

            $token = $tokenList[$index] ?? ('upload-' . $index);
            if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $token)
                || isset($seenTokens[$token])) {
                throw new \DomainException('invalid_upload_token');
            }
            $seenTokens[$token] = true;
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
                App::logException('ERROR', 'issue.attachment_save_failed', $e, [
                    'issue_id' => $issueId,
                    'user_id' => $userId,
                ]);
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
            $html .= self::renderFormattedText($part);
        }
        return $html;
    }

    /**
     * Render the small formatting vocabulary used by the technical composer.
     * All user text is escaped before the only supported markup is introduced.
     */
    private static function renderFormattedText(string $text): string
    {
        $lines = preg_split('/\\r?\\n/', $text) ?: [];
        $html = '';
        $listType = null;
        $paragraph = [];

        $closeList = static function () use (&$html, &$listType): void {
            if ($listType !== null) {
                $html .= $listType === 'ol' ? '</ol>' : '</ul>';
                $listType = null;
            }
        };
        $flushParagraph = static function () use (&$html, &$paragraph, $closeList): void {
            if (!$paragraph) return;
            $closeList();
            $escaped = htmlspecialchars(implode("\n", $paragraph), ENT_QUOTES, 'UTF-8');
            $escaped = preg_replace('/\\*\\*(.+?)\\*\\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
            $html .= '<p>' . nl2br($escaped) . '</p>';
            $paragraph = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^#{1,3}\\s+(.+)$/u', $trimmed, $heading)) {
                $flushParagraph();
                $closeList();
                $safe = htmlspecialchars(trim($heading[1]), ENT_QUOTES, 'UTF-8');
                $html .= '<h3>' . $safe . '</h3>';
                continue;
            }
            if (preg_match('/^[-─]{5,}$/u', $trimmed)) {
                $flushParagraph();
                $closeList();
                $html .= '<hr>';
                continue;
            }
            if (preg_match('/^\\d+\\.\\s+(.+)$/u', $trimmed, $ordered)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $closeList();
                    $html .= '<ol>';
                    $listType = 'ol';
                }
                $safe = htmlspecialchars($ordered[1], ENT_QUOTES, 'UTF-8');
                $safe = preg_replace('/\\*\\*(.+?)\\*\\*/s', '<strong>$1</strong>', $safe) ?? $safe;
                $html .= '<li>' . $safe . '</li>';
                continue;
            }
            if (preg_match('/^[•*-]\\s+(.+)$/u', $trimmed, $bullet)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $closeList();
                    $html .= '<ul>';
                    $listType = 'ul';
                }
                $safe = htmlspecialchars($bullet[1], ENT_QUOTES, 'UTF-8');
                $safe = preg_replace('/\\*\\*(.+?)\\*\\*/s', '<strong>$1</strong>', $safe) ?? $safe;
                $html .= '<li>' . $safe . '</li>';
                continue;
            }

            $closeList();
            $paragraph[] = $line;
        }
        $flushParagraph();
        $closeList();
        return $html;
    }

    /**
     * Published inline media is retained even when an edit removes its token
     * from the current body. Previous body versions may still reference the
     * attachment, so automatic pruning would destroy revision history.
     */
    public static function pruneUnusedInlineMedia(int $issueId): void
    {
        // Intentionally retain attachment rows and their files.
    }
}
