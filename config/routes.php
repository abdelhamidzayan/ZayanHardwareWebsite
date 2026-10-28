<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminFeaturedPostsController;
use App\Controllers\AdminSkillTestController;
use App\Controllers\FeedController;
use App\Controllers\HomeController;
use App\Controllers\IssueController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\SkillTestController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/help', [PageController::class, 'help']);
    $router->get('/policies', [PageController::class, 'policies']);
    $router->get('/settings', [PageController::class, 'settings']);
    $router->get('/feed', [FeedController::class, 'index']);
    $router->get('/issues/create', [IssueController::class, 'create']);
    $router->post('/issues/create', [IssueController::class, 'create']);

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
    $router->get('/register/usernames', [AuthController::class, 'usernames']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->post('/account/deletion-request', [AccountController::class, 'requestDeletion']);
    $router->get('/skills/tests', [SkillTestController::class, 'index']);
    $router->get('/admin/skill-tests', [AdminSkillTestController::class, 'index']);
    $router->post('/admin/skill-tests', [AdminSkillTestController::class, 'create']);
    $router->post('/admin/skill-tests/{id}/status', [AdminSkillTestController::class, 'setStatus']);
    $router->get('/skills/tests/{id}', [SkillTestController::class, 'show']);
    $router->post('/skills/tests/{id}/submit', [SkillTestController::class, 'submit']);
    $router->get('/admin', [AdminFeaturedPostsController::class, 'index']);
    $router->post('/admin/featured-posts/{id}', [AdminFeaturedPostsController::class, 'update']);
    $router->get('/admin/users', [AdminUserController::class, 'index']);
    $router->post('/admin/users/{id}/password-reset', [AdminUserController::class, 'resetPassword']);
    $router->post('/admin/users/{id}/status', [AdminUserController::class, 'setStatus']);
    $router->post('/admin/users/{id}/role', [AdminUserController::class, 'setRole']);

    $router->get('/profile/{username}', [ProfileController::class, 'show']);

    // Navigation targets that are not built yet.
    foreach (['/search', '/market', '/notifications'] as $path) {
        $router->get($path, [PageController::class, 'soon']);
    }
};
