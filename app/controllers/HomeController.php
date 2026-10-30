<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\DemoData;
use App\Services\AuthService;

final class HomeController extends Controller
{
    /** Public landing page for guests; members go straight to the feed. */
    public function index(): Response
    {
        if (AuthService::user() !== null) {
            return $this->redirect('/feed');
        }

        return $this->view('home/index', [
            'title'         => '',
            'previewIssues' => array_slice(DemoData::issues(), 0, 2),
        ], 200, 'guest');
    }
}
