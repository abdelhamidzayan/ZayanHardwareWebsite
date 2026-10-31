<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Services\AdminRosterService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Admin roster integration is restricted to zayan_fresh_install_test.\n");
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

App::boot($root);
$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $ownerId = 1;
    $secondUserId = 3;

    $owner = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ? FOR UPDATE');
    $owner->execute([$ownerId]);
    $ownerRow = $owner->fetch(PDO::FETCH_ASSOC);
    if (!$ownerRow || $ownerRow['role'] !== 'admin' || $ownerRow['account_status'] !== 'active') {
        throw new RuntimeException('Fresh-install owner fixture must be the only active administrator.');
    }

    $onlyAdminStatusRejected = false;
    try {
        AdminRosterService::assertCanChangeStatus($pdo, 'admin', 'active', 'banned');
    } catch (DomainException $error) {
        $onlyAdminStatusRejected = $error->getMessage() === 'last_admin';
    }
    if (!$onlyAdminStatusRejected) {
        throw new RuntimeException('The only active administrator must not be deactivated.');
    }

    $onlyAdminRoleRejected = false;
    try {
        AdminRosterService::assertCanChangeRole($pdo, 'admin', 'active', 'member');
    } catch (DomainException $error) {
        $onlyAdminRoleRejected = $error->getMessage() === 'last_admin';
    }
    if (!$onlyAdminRoleRejected) {
        throw new RuntimeException('The only active administrator must not be demoted.');
    }

    $promoteSecond = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND account_status = 'active'");
    $promoteSecond->execute([$secondUserId]);
    if ($promoteSecond->rowCount() !== 1) {
        throw new RuntimeException('The second active account could not be promoted for the fixture.');
    }

    AdminRosterService::assertActorActive($pdo, $secondUserId);
    AdminRosterService::assertCanChangeStatus($pdo, 'admin', 'active', 'suspended');
    AdminRosterService::assertCanChangeRole($pdo, 'admin', 'active', 'member');

    $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ?")->execute([$secondUserId]);
    $inactiveActorRejected = false;
    try {
        AdminRosterService::assertActorActive($pdo, $secondUserId);
    } catch (DomainException $error) {
        $inactiveActorRejected = $error->getMessage() === 'admin_not_active';
    }
    if (!$inactiveActorRejected) {
        throw new RuntimeException('A suspended administrator must lose administrative mutation access.');
    }

    $pdo->rollBack();
    echo "Admin roster invariants passed: last-admin protection and active-actor checks.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Admin roster integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
