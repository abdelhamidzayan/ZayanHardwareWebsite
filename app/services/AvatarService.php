<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

final class AvatarService
{
    public static function store(int $userId, mixed $file): string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \DomainException('avatar_upload_failed');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new \DomainException('avatar_invalid_size');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \DomainException('avatar_invalid_type');
        }
        $image = @getimagesize($tmp);
        if (!is_array($image) || $image[0] < 160 || $image[1] < 160 || $image[0] > 6000 || $image[1] > 6000) {
            throw new \DomainException('avatar_invalid_dimensions');
        }
        $ratio = $image[0] / max(1, $image[1]);
        if ($ratio < 0.97 || $ratio > 1.03) {
            throw new \DomainException('avatar_must_be_cropped');
        }

        $root = App::root('storage/private');
        $directory = $root . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \DomainException('avatar_storage_unavailable');
        }
        $filename = bin2hex(random_bytes(20)) . '.jpg';
        $target = $directory . DIRECTORY_SEPARATOR . $filename;

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $source = @imagecreatefromstring((string) file_get_contents($tmp));
            if (!$source) throw new \DomainException('avatar_invalid_image');
            $width = imagesx($source);
            $height = imagesy($source);
            $side = min($width, $height);
            $x = (int) floor(($width - $side) / 2);
            $y = (int) floor(($height - $side) / 2);
            $canvas = imagecreatetruecolor(512, 512);
            imagecopyresampled($canvas, $source, 0, 0, $x, $y, 512, 512, $side, $side);
            $saved = imagejpeg($canvas, $target, 88);
            imagedestroy($canvas);
            imagedestroy($source);
            if (!$saved) throw new \DomainException('avatar_storage_failed');
        } elseif (!move_uploaded_file($tmp, $target)) {
            throw new \DomainException('avatar_storage_failed');
        }

        return 'avatars/' . $userId . '/' . $filename;
    }
}
