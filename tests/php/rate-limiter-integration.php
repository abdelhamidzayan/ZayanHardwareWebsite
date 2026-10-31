<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Services\RateLimiter;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'App\Core\\' => '/app/core/',
        'App\Controllers\\' => '/app/controllers/',
        'App\Models\\' => '/app/models/',
        'App\Services\\' => '/app/services/',
        'App\Middleware\\' => '/app/middleware/',
    ];
    foreach ($map as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $root . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
});

function expectRateLimit(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

App::boot($root);
$_SERVER['REMOTE_ADDR'] = '203.0.113.44';
$pdo = Database::connection();
$pdo->exec("DELETE FROM rate_limit_buckets WHERE bucket LIKE 'ci.%'");

try {
    expectRateLimit(RateLimiter::allowForIp('ci.ip-only', 1, 600), 'First IP-only request should be allowed.');
    expectRateLimit(!RateLimiter::allowForIp('ci.ip-only', 1, 600), 'Second IP-only request should be blocked.');

    expectRateLimit(
        RateLimiter::allowForUser('ci.user-limit', 987654321, 1, 600, 10),
        'First per-user request should be allowed.'
    );
    expectRateLimit(
        !RateLimiter::allowForUser('ci.user-limit', 987654321, 1, 600, 10),
        'Second per-user request should be blocked.'
    );

    $failedAttempt = static fn (): array => ['user' => null, 'error' => 'invalid'];
    $firstFailure = RateLimiter::attemptForIpAndIdentifier('ci.login-id', 'tech@example.test', 10, 2, 600, $failedAttempt);
    $secondFailure = RateLimiter::attemptForIpAndIdentifier('ci.login-id', 'tech@example.test', 10, 2, 600, $failedAttempt);
    $callbackCalled = false;
    $thirdFailure = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-id',
        'tech@example.test',
        10,
        2,
        600,
        static function () use (&$callbackCalled): array {
            $callbackCalled = true;
            return ['user' => null, 'error' => 'invalid'];
        }
    );
    expectRateLimit($firstFailure['allowed'] && $secondFailure['allowed'], 'Two invalid attempts should fit the configured identifier limit.');
    expectRateLimit(!$thirdFailure['allowed'] && !$callbackCalled, 'The attempt callback must not run after the identifier limit is reached.');

    $successfulLogin = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-success',
        'good@example.test',
        10,
        1,
        600,
        static fn (): array => ['user' => ['id' => 1], 'error' => null]
    );
    $failureAfterSuccess = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-success',
        'good@example.test',
        10,
        1,
        600,
        $failedAttempt
    );
    $blockedAfterFailure = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-success',
        'good@example.test',
        10,
        1,
        600,
        $failedAttempt
    );
    expectRateLimit($successfulLogin['allowed'], 'Successful login should be allowed.');
    expectRateLimit($failureAfterSuccess['allowed'], 'Successful login must not consume a failed-attempt slot.');
    expectRateLimit(!$blockedAfterFailure['allowed'], 'One failed login should consume the configured identifier slot.');

    $ipGuardFirst = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-ip',
        'first@example.test',
        1,
        10,
        600,
        $failedAttempt
    );
    $ipGuardSecond = RateLimiter::attemptForIpAndIdentifier(
        'ci.login-ip',
        'second@example.test',
        1,
        10,
        600,
        $failedAttempt
    );
    expectRateLimit($ipGuardFirst['allowed'] && !$ipGuardSecond['allowed'], 'IP limit must apply across different identifiers.');

    echo "RateLimiter integration checks passed.\\n";
} finally {
    $pdo->exec("DELETE FROM rate_limit_buckets WHERE bucket LIKE 'ci.%'");
}
