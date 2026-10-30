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
        $layout = AuthService::user() !== null ? 'app' : 'guest';
        return $this->view('pages/help', [
            'title' => 'المساعدة والدعم',
            'active' => 'profile',
        ], 200, $layout);
    }

    /** Public policies page for platform rules and privacy information. */
    public function policies(): Response
    {
        $layout = AuthService::user() !== null ? 'app' : 'guest';
        return $this->view('pages/policies', [
            'title' => 'سياسات الموقع',
            'active' => 'profile',
        ], 200, $layout);
    }

    /** Account settings page. */
    public function settings(): Response
    {
        if (!AuthService::user()) return $this->redirect('/login');

        return $this->view('pages/settings', [
            'title' => 'الإعدادات',
            'active' => 'profile',
            'currentUser' => AuthService::user(),
        ]);
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
