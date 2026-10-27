<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FeedController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/feed', [FeedController::class, 'index']);

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
