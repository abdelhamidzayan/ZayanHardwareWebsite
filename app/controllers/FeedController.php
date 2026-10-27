<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\DemoData;
use App\Services\AuthService;

final class FeedController extends Controller
{
    public function index(): Response
    {
        if (!AuthService::user()) return $this->redirect('/login');

        // Keep the feed focused on the full stream; category chips were removed from the header.
        $issues = DemoData::issues();

        return $this->view('feed/index', [
            'title'  => 'الرئيسية',
            'active' => 'home',
            'issues' => $issues,
        ]);
    }
}
