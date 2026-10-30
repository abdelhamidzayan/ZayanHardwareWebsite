<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\AuthService;

final class HomeController extends Controller
{
    /** Send members to the feed and guests to the login interface. */
    public function index(): Response
    {
        if (AuthService::user() !== null) {
            return $this->redirect('/feed');
        }

        return $this->redirect('/login');
    }
}
