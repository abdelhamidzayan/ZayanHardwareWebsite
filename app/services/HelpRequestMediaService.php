<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\HelpRequest;

final class HelpRequestMediaService
{
    public static function validate(mixed $files): array
    {
        if ($files === null || !is_array($files) || !isset($files['name']) || !is_array($files['name'])) return [];
        if (count($files['name']) > 8) throw new \DomainException('too_many_attachments');
        $allowed = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $uploads = [];
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $tmp = (string) ($files['tmp_name'][$index] ?? '');
            $size = (int) ($files['size'][$index] ?? 0);
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 8 * 1024 * 1024) {
                throw new \DomainException('invalid_attachment_size');
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowed[$mime])) throw new \DomainException('invalid_attachment_type');
            $uploads[] = ['tmp' => $tmp, 'size' => $size, 'mime' => $mime, 'ext' => $allowed[$mime]];
        }
        return $uploads;
    }

    public static function store(int $requestId, int $messageId, int $userId, array $uploads): int
    {
        if (!$uploads) return 0;
        $directory = App::root('storage/private') . DIRECTORY_SEPARATOR . 'help-requests' . DIRECTORY_SEPARATOR . $requestId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return count($uploads);
        $failed = 0;
        foreach ($uploads as $upload) {
            $filename = bin2hex(random_bytes(20)) . '.' . $upload['ext'];
            $target = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($upload['tmp'], $target)) {
                $failed++;
                continue;
            }
            try {
                HelpRequest::addAttachment(
                    $requestId, $messageId, $userId,
                    'help-requests/' . $requestId . '/' . $filename,
                    $upload['mime'], (int) $upload['size']
                );
            } catch (\Throwable $e) {
                @unlink($target);
                App::log('error', 'Unable to save personal help attachment: ' . $e->getMessage());
                $failed++;
            }
        }
        return $failed;
    }
}
