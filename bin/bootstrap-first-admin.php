<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the command line.\n");
    exit(1);
}

$options = getopt('', ['username:', 'confirm-database:', 'confirm-first-admin']);
$username = strtolower(trim((string) ($options['username'] ?? '')));
$confirmedDatabase = (string) ($options['confirm-database'] ?? '');

if (!isset($options['confirm-first-admin'])
    || preg_match('/^[a-z0-9_]{3,20}$/D', $username) !== 1
    || $confirmedDatabase === '') {
    fwrite(STDERR, "Usage: php bin/bootstrap-first-admin.php --username=OWNER --confirm-database=DB_NAME --confirm-first-admin\n");
    exit(2);
}

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
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

App::boot($root);
$configuredDatabase = (string) App::config('database.name', '');

if ($configuredDatabase === '' || !hash_equals($configuredDatabase, $confirmedDatabase)) {
    fwrite(STDERR, "Database confirmation does not match DB_NAME from the application's configuration. No changes were made.\n");
    exit(2);
}

$pdo = Database::connection();
$lockName = 'zayan_hardware_first_admin_bootstrap';
$lockAcquired = false;
$exitCode = 0;

try {
    $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('{$lockName}', 10)")->fetchColumn() === 1;
    if (!$lockAcquired) {
        throw new RuntimeException('Could not acquire the first-admin bootstrap lock.');
    }

    $pdo->beginTransaction();

    $userQuery = $pdo->prepare(
        'SELECT id, role, account_status FROM users WHERE username = ? LIMIT 1 FOR UPDATE'
    );
    $userQuery->execute([$username]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('The requested username does not exist.');
    }
    if ($user['account_status'] !== 'active') {
        throw new RuntimeException('Only an active account can become the first administrator.');
    }

    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount !== 0) {
        throw new RuntimeException('An administrator already exists. First-admin bootstrap is permanently closed.');
    }

    $userId = (int) $user['id'];
    $update = $pdo->prepare(
        "UPDATE users SET role = 'admin', updated_at = UTC_TIMESTAMP()
         WHERE id = ? AND role <> 'admin' AND account_status = 'active'"
    );
    $update->execute([$userId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The account was not promoted. No audit event was written.');
    }

    $details = json_encode([
        'bootstrap' => 'first_administrator',
        'username' => $username,
        'method' => 'operator_cli',
        'database' => $configuredDatabase,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $pdo->prepare(
        "INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
         VALUES (NULL, ?, 'account.first_admin_bootstrapped', ?, NULL)"
    )->execute([$userId, $details]);

    $pdo->commit();
    fwrite(STDOUT, "First administrator created for @{$username} (user ID {$userId}) in database {$configuredDatabase}.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "First-admin bootstrap failed: " . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($lockAcquired) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('{$lockName}')");
        } catch (Throwable $error) {
            App::log('WARNING', 'account.first_admin_lock_release_failed', [
                'exception' => $error::class,
            ]);
        }
    }
}

exit($exitCode);
