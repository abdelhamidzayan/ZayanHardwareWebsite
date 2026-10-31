<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Services\AuthService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Auth session version integration is restricted to zayan_fresh_install_test.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
require $root . '/app/core/App.php';
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = [
            'App\\Core\\' => '/app/core/',
            'App\\Controllers\\' => '/app/controllers/',
            'App\\Models\\' => '/app/models/',
            'App\\Services\\' => '/app/services/',
            'App\\Middleware\\' => '/app/middleware/',
        ];
        foreach ($map as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) continue;
            $file = $root . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    });
}

function expectSessionVersion(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

App::boot($root);
$pdo = Database::connection();
$userId = 3; // Second active synthetic member from the fresh-install fixture.

$loadedProperty = new ReflectionProperty(AuthService::class, 'loaded');
$currentProperty = new ReflectionProperty(AuthService::class, 'current');
$resetRequestCache = static function () use ($loadedProperty, $currentProperty): void {
    $loadedProperty->setValue(null, false);
    $currentProperty->setValue(null, null);
};

if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
    fwrite(STDERR, "Unable to start isolated CLI session.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $account = $pdo->prepare('SELECT account_status, auth_version FROM users WHERE id = ? FOR UPDATE');
    $account->execute([$userId]);
    $row = $account->fetch(PDO::FETCH_ASSOC);
    expectSessionVersion(
        is_array($row) && $row['account_status'] === 'active',
        'The synthetic session test member must be active.'
    );

    $_SESSION = [];
    expectSessionVersion(AuthService::login($userId), 'An active member should be able to establish a version-bound session.');
    $initialVersion = (int) $_SESSION['auth_version'];
    expectSessionVersion($initialVersion === (int) $row['auth_version'], 'The login session must store the current database auth_version.');
    expectSessionVersion((int) (AuthService::user()['id'] ?? 0) === $userId, 'The current session should resolve to its active member.');

    // Simulate the auth_version increment performed by the admin password-reset flow.
    $pdo->prepare('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?')->execute([$userId]);
    $resetRequestCache();
    expectSessionVersion(AuthService::user() === null, 'A session issued before password reset must be rejected on the next request.');
    expectSessionVersion(!isset($_SESSION['user_id']) && !isset($_SESSION['auth_version']), 'A stale session must be cleared after rejection.');

    expectSessionVersion(AuthService::login($userId), 'The member should be able to establish a new session after using the new credentials.');
    expectSessionVersion((int) $_SESSION['auth_version'] === $initialVersion + 1, 'The new login must store the incremented auth_version.');
    expectSessionVersion((int) (AuthService::user()['id'] ?? 0) === $userId, 'The new version-bound session should be accepted.');

    $pdo->rollBack();
    AuthService::logout();
    echo "Auth session-version integration passed: password reset invalidates the previous session.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    AuthService::logout();
    fwrite(STDERR, "Auth session-version integration failed: " . $error::class . " — " . $error->getMessage() . "\n");
    exit(1);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}
