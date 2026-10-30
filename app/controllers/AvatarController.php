<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\AuthService;

final class AvatarController extends Controller
{
    public function show(int $userId): Response
    {
        $user = User::findById($userId);
        if (!$user || $user['account_status'] !== 'active') return $this->notFound();
        if (empty($user['avatar_path'])) {
            $allowed = ['technician-blue', 'technician-cyan', 'technician-violet', 'technician-green', 'technician-orange', 'technician-red'];
            $key = in_array((string) ($user['avatar_key'] ?? ''), $allowed, true)
                ? (string) $user['avatar_key'] : 'technician-blue';
            $defaultFile = \App\Core\App::root('public/assets/avatars/' . $key . '.svg');
            if (!is_file($defaultFile)) return $this->notFound();
            return new Response((string) file_get_contents($defaultFile), 200, [
                'Content-Type' => 'image/svg+xml; charset=utf-8',
                'Content-Length' => (string) filesize($defaultFile),
                'Cache-Control' => 'public, max-age=300, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ]);
        }
        $relative = (string) $user['avatar_path'];
        if (!preg_match('~^avatars/' . $userId . '/[a-f0-9]{40}\.(?:jpg|png|webp)$~', $relative)) {
            return $this->notFound();
        }
        $root = realpath(\App\Core\App::root('storage/private'));
        if ($root === false) return $this->notFound();
        $file = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return $this->notFound();
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return $this->notFound();
        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($file),
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function update(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/settings');
        }

        $avatarKey = $this->request->input('avatar_key');
        $avatarKey = is_string($avatarKey) ? $avatarKey : '';
        $allowed = ['technician-blue', 'technician-cyan', 'technician-violet', 'technician-green', 'technician-orange', 'technician-red'];
        try {
            if (isset($_FILES['avatar_file']) && (int) ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $path = AvatarService::store((int) $user['id'], $_FILES['avatar_file']);
                User::saveAvatar((int) $user['id'], 'custom', $path);
            } elseif (in_array($avatarKey, $allowed, true)) {
                User::saveAvatar((int) $user['id'], $avatarKey);
            } else {
                throw new \DomainException('avatar_choice_invalid');
            }
            $this->flash('success', 'تم تحديث الصورة الشخصية.');
        } catch (\DomainException $e) {
            $message = match ($e->getMessage()) {
                'avatar_invalid_size' => 'الصورة يجب ألا تتجاوز 5 ميجابايت.',
                'avatar_invalid_type' => 'استخدم صورة JPG أو PNG أو WEBP.',
                'avatar_invalid_dimensions' => 'أبعاد الصورة غير مناسبة. اختر صورة أوضح.',
                'avatar_must_be_cropped' => 'قصّ الصورة إلى شكل مربع قبل الحفظ.',
                'avatar_storage_unavailable', 'avatar_storage_failed' => 'تعذر حفظ الصورة على الخادم.',
                default => 'تعذر تحديث الصورة. اختر Avatar أو صورة أخرى.',
            };
            $this->flash('error', $message);
        }
        return $this->redirect('/settings');
    }
}
