<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\AuthService;
use App\Models\User;
use App\Core\Csrf;

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

    /** Persist the member's personal-help availability preference. */
    public function saveHelpAvailability(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/settings');
        }
        User::setHelpRequestsEnabled((int) $user['id'], $this->request->input('accept_help_requests') === '1');
        $this->flash('success', 'تم حفظ تفضيل استقبال طلبات المساعدة.');
        return $this->redirect('/settings');
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
