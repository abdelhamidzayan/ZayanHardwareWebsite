<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Notification;
use App\Models\Issue;
use App\Services\AuthService;

final class NotificationController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        return $this->view('notifications/index', [
            'title' => 'الإشعارات',
            'active' => 'home',
            'currentUser' => $user,
            'notifications' => Notification::forUser((int) $user['id']),
            'unreadCount' => Notification::unreadCount((int) $user['id']),
        ]);
    }

    public function open(int $id): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/notifications');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $path = Notification::markRead($id, (int) $user['id']);
        if (!is_string($path) || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return $this->redirect('/notifications');
        }
        if (preg_match('#^/issues/(\\d+)(?:$|[#?])#', $path, $match) && !Issue::find((int) $match[1])) {
            return $this->redirect('/notifications');
        }
        return $this->redirect($path);
    }

    public function readAll(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->redirect('/notifications');
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        Notification::markAllRead((int) $user['id']);
        $this->flash('success', 'تم تعليم الإشعارات كمقروءة.');
        return $this->redirect('/notifications');
    }
}
