<?php
declare(strict_types=1);

use App\Controllers\AvatarController;
use App\Core\Request;
use App\Core\Router;

$root = dirname(__DIR__, 2);
require $root . '/app/core/Response.php';
require $root . '/app/core/Request.php';
require $root . '/app/services/AuthService.php';
require $root . '/app/core/Router.php';
require $root . '/app/core/Controller.php';
require $root . '/app/controllers/AvatarController.php';

$_SESSION = [];

$router = new Router();
$registerRoutes = require $root . '/config/routes.php';
$registerRoutes($router);

$policy = new ReflectionMethod(Router::class, 'isGuestRouteAllowed');
$cases = [
    ['GET', '/', true],
    ['GET', '/login', true],
    ['POST', '/login', true],
    ['GET', '/register', true],
    ['POST', '/register', true],
    ['GET', '/register/usernames', true],
    ['GET', '/help', true],
    ['GET', '/policies', true],
    ['GET', '/profile/board_tech', true],
    ['GET', '/avatar/42', true],
    ['GET', '/help-library', true],
    ['GET', '/help-library/51', true],
    ['GET', '/feed', false],
    ['GET', '/issues/51', false],
    ['GET', '/attachments/51', false],
    ['GET', '/drafts', false],
    ['GET', '/help-requests', false],
    ['GET', '/help-requests/attachments/51', false],
    ['POST', '/help-requests/51/messages', false],
    ['POST', '/profile/avatar', false],
    ['GET', '/admin', false],
    ['GET', '/help-library/not-a-number', false],
    ['POST', '/help-library/51', false],
];

foreach ($cases as [$method, $path, $expected]) {
    $actual = $policy->invoke($router, new Request($method, $path, [], []));
    if ($actual !== $expected) {
        fwrite(STDERR, "Guest route policy mismatch for {$method} {$path}: expected "
            . ($expected ? 'public' : 'private') . ', got ' . ($actual ? 'public' : 'private') . PHP_EOL);
        exit(1);
    }
}

$avatarParameter = (new ReflectionMethod(AvatarController::class, 'show'))->getParameters()[0];
$avatarType = $avatarParameter->getType();
if (!$avatarType instanceof ReflectionNamedType || $avatarType->getName() !== 'string') {
    fwrite(STDERR, "AvatarController::show() must accept the raw route segment as a string and validate it explicitly." . PHP_EOL);
    exit(1);
}

echo "Public route policy and avatar route parameter contract passed." . PHP_EOL;
