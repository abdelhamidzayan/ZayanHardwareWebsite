<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\AuthService;

final class PageController extends Controller
{
    /** Public help and support page. */
    public function help(): Response
    {
        return $this->view('pages/help', [
            'title' => 'المساعدة والدعم',
        ], 200, 'guest');
    }

    /** Pages from the navigation that are not built yet. */
    public function soon(): Response
    {
        if (!AuthService::user()) return $this->redirect('/login');

        $pages = [
            '/search'         => ['بحث', 'search'],
            '/market'         => ['السوق', 'market'],
            '/issues/create'  => ['إضافة عطل', 'add'],
            '/notifications'  => ['الإشعارات', 'home'],
        ];
        [$title, $active] = $pages[$this->request->path] ?? ['قريبًا', 'home'];

        return $this->view('pages/soon', [
            'title'      => $title,
            'active'     => $active,
            'showHeader' => false,
        ]);
    }
}
