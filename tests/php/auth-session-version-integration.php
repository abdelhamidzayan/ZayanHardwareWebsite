<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\User;
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

    // Simulate the atomic password/hash version update performed by the admin reset flow.
    $newPasswordHash = password_hash('synthetic-new-password', PASSWORD_DEFAULT);
    $staleRehash = password_hash('synthetic-old-password-rehash', PASSWORD_DEFAULT);
    $pdo->prepare(
        'UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?'
    )->execute([$newPasswordHash, $userId]);

    $resetRequestCache();
    expectSessionVersion(AuthService::user() === null, 'A session issued before password reset must be rejected on the next request.');
    expectSessionVersion(!isset($_SESSION['user_id']) && !isset($_SESSION['auth_version']), 'A stale session must be cleared after rejection.');

    expectSessionVersion(
        !User::markLoggedIn($userId, $staleRehash, $initialVersion),
        'A stale in-flight login must not be allowed to write its password rehash after an administrator reset.'
    );
    $storedHash = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $storedHash->execute([$userId]);
    expectSessionVersion(
        hash_equals($newPasswordHash, (string) $storedHash->fetchColumn()),
        'The administrator-reset password hash must not be overwritten by a stale login.'
    );

    expectSessionVersion(
        !AuthService::login($userId, $initialVersion),
        'A login that verified the old password before the reset must not create a session afterward.'
    );
    expectSessionVersion(AuthService::login($userId), 'The member should be able to establish a new session after using the new credentials.');
    expectSessionVersion((int) $_SESSION['auth_version'] === $initialVersion + 1, 'The new login must store the incremented auth_version.');
    expectSessionVersion((int) (AuthService::user()['id'] ?? 0) === $userId, 'The new version-bound session should be accepted.');

    // A role/status transition must invalidate every other device session too.
    $preSuspensionVersion = (int) $_SESSION['auth_version'];
    $pdo->prepare(
        "UPDATE users SET account_status = 'suspended', auth_version = auth_version + 1 WHERE id = ?"
    )->execute([$userId]);
    $resetRequestCache();
    expectSessionVersion(AuthService::user() === null, 'A session must be rejected immediately after account suspension.');
    expectSessionVersion(!isset($_SESSION['user_id']), 'The suspended account session must be cleared.');

    // Simulate another device presenting its old cookie after an administrator reactivates the account.
    $pdo->prepare(
        "UPDATE users SET account_status = 'active', auth_version = auth_version + 1 WHERE id = ?"
    )->execute([$userId]);
    $_SESSION = ['user_id' => $userId, 'auth_version' => $preSuspensionVersion];
    $resetRequestCache();
    expectSessionVersion(AuthService::user() === null, 'A stale cookie must not become valid again after account reactivation.');
    expectSessionVersion(!isset($_SESSION['user_id']), 'The pre-suspension session must remain invalid after reactivation.');

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
