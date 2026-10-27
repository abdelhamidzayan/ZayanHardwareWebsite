<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FeedController;
use App\Controllers\HomeController;
use App\Controllers\IssueController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/feed', [FeedController::class, 'index']);

    $router->get('/issues/{id}', [IssueController::class, 'show']);
    $router->get('/attachments/{id}', [IssueController::class, 'attachment']);
    $router->post('/issues/{id}/reply', [IssueController::class, 'reply']);
    $router->post('/issues/{id}/edit', [IssueController::class, 'edit']);
    $router->post('/issues/{id}/vote', [IssueController::class, 'vote']);
    $router->post('/issues/{id}/accept', [IssueController::class, 'accept']);
    $router->post('/issues/{id}/close', [IssueController::class, 'close']);
    $router->post('/issues/{id}/reopen', [IssueController::class, 'reopen']);
    $router->post('/issues/{id}/review-result', [IssueController::class, 'reviewResult']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/profile/{username}', [ProfileController::class, 'show']);

    // Navigation targets that are not built yet.
    foreach (['/search', '/market', '/issues/create', '/notifications'] as $path) {
        $router->get($path, [PageController::class, 'soon']);
    }
};
