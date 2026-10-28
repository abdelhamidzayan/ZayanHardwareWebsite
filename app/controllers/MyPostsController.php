<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Issue;
use App\Services\AuthService;

final class MyPostsController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $type = $this->request->query['type'] ?? '';
        $status = $this->request->query['status'] ?? '';
        $type = is_string($type) ? $type : '';
        $status = is_string($status) ? $status : '';

        return $this->view('my-posts/index', [
            'title' => 'مشاركاتي',
            'active' => 'profile',
            'currentUser' => $user,
            'items' => Issue::myPosts((int) $user['id'], $type, $status),
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }
}
