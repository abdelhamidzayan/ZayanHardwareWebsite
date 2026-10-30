<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Router.php';

use App\Core\Router;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$router = new Router();
$add = new ReflectionMethod(Router::class, 'add');
$add->invoke($router, 'GET', '/avatar/{userId}', static fn () => null);
$add->invoke($router, 'POST', '/issues/{id}/replies/{replyId}/edit', static fn () => null);
$add->invoke($router, 'POST', '/issues/{id}/diagnostic-updates/{updateId}/edit', static fn () => null);
$add->invoke($router, 'GET', '/drafts/{draftId}/media/{mediaUuid}', static fn () => null);

$routesProperty = new ReflectionProperty(Router::class, 'routes');
$routes = $routesProperty->getValue($router);
$avatarPattern = $routes[0][1];
$draftPattern = $routes[3][1];

check(preg_match($avatarPattern, '/avatar/42', $avatarMatches) === 1, 'Numeric userId should match the avatar route.');
check(($avatarMatches['userId'] ?? null) === '42', 'The route should capture userId by its declared name.');
check(preg_match($avatarPattern, '/avatar/not-a-number') === 0, 'Non-numeric userId must not match the avatar route.');
$replyPattern = $routes[1][1];
$diagnosticPattern = $routes[2][1];
check(preg_match($replyPattern, '/issues/7/replies/19/edit', $replyMatches) === 1, 'Numeric replyId should match the reply route.');
check(preg_match($replyPattern, '/issues/7/replies/not-a-number/edit') === 0, 'Non-numeric replyId must not match the reply route.');
check(preg_match($diagnosticPattern, '/issues/7/diagnostic-updates/23/edit', $diagnosticMatches) === 1, 'Numeric updateId should match the diagnostic route.');
check(preg_match($diagnosticPattern, '/issues/7/diagnostic-updates/not-a-number/edit') === 0, 'Non-numeric updateId must not match the diagnostic route.');
check(
    preg_match($draftPattern, '/drafts/11111111-1111-4111-8111-111111111111/media/22222222-2222-4222-8222-222222222222', $draftMatches) === 1,
    'Draft and media UUID route parameters must remain unrestricted path strings.'
);

$normalize = new ReflectionMethod(Router::class, 'normalizeParams');
$normalized = $normalize->invoke($router, [
    'id' => '7',
    'userId' => '42',
    'replyId' => '19',
    'updateId' => '23',
    'draftId' => '11111111-1111-4111-8111-111111111111',
    'mediaUuid' => '22222222-2222-4222-8222-222222222222',
    'username' => 'technician_1',
]);

check(is_int($normalized['id']) && $normalized['id'] === 7, 'id must be passed to controllers as an integer.');
check(is_int($normalized['userId']) && $normalized['userId'] === 42, 'userId must be passed to controllers as an integer.');
check(is_int($normalized['replyId']) && $normalized['replyId'] === 19, 'replyId must be passed to controllers as an integer.');
check(is_int($normalized['updateId']) && $normalized['updateId'] === 23, 'updateId must be passed to controllers as an integer.');
check(is_string($normalized['draftId']), 'draftId must remain a string.');
check(is_string($normalized['mediaUuid']), 'mediaUuid must remain a string.');
check(is_string($normalized['username']), 'username must remain a string.');

echo "Router route-parameter checks passed.\n";
