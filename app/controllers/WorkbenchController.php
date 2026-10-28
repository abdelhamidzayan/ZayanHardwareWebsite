<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Issue;
use App\Services\AuthService;

final class WorkbenchController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        return $this->view('workbench/index', [
            'title' => 'طاولتي',
            'active' => 'profile',
            'currentUser' => $user,
            'items' => Issue::workbench((int) $user['id']),
        ]);
    }
}
