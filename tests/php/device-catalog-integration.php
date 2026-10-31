<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\DeviceCatalog;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Device catalog integration is restricted to zayan_fresh_install_test.\n");
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
$adminId = 1;
$ordinaryUserId = 3;

$roles = $pdo->prepare('SELECT id, role, account_status FROM users WHERE id IN (?, ?) ORDER BY id');
$roles->execute([$adminId, $ordinaryUserId]);
$roleRows = $roles->fetchAll(PDO::FETCH_ASSOC);
if (count($roleRows) !== 2
    || $roleRows[0]['role'] !== 'admin' || $roleRows[0]['account_status'] !== 'active'
    || $roleRows[1]['role'] === 'admin' || $roleRows[1]['account_status'] !== 'active') {
    fwrite(STDERR, "Fresh-install catalog fixtures require active admin id 1 and active non-admin id 3.\n");
    exit(2);
}

$manufacturerId = (int) $pdo->query(
    'SELECT id FROM device_manufacturers WHERE is_active = 1 ORDER BY id LIMIT 1'
)->fetchColumn();
if ($manufacturerId < 1) {
    fwrite(STDERR, "Fresh-install catalog fixture has no active manufacturer.\n");
    exit(2);
}

$suffix = strtoupper(bin2hex(random_bytes(5)));
$commercialName = 'CI Catalog Model ' . $suffix;
$modelCode = 'CI-' . $suffix;
$pdo->prepare(
    "INSERT INTO device_model_suggestions
        (manufacturer_id, submitted_by, commercial_name, model_code, aliases, note)
     VALUES (?, ?, ?, ?, NULL, 'Disposable model-level authorization fixture')"
)->execute([$manufacturerId, $ordinaryUserId, $commercialName, $modelCode]);
$suggestionId = (int) $pdo->lastInsertId();

$unauthorizedRejected = false;
try {
    DeviceCatalog::moderateSuggestion($suggestionId, $ordinaryUserId, true);
} catch (DomainException $error) {
    $unauthorizedRejected = $error->getMessage() === 'admin_not_active';
}
if (!$unauthorizedRejected) {
    fwrite(STDERR, "A non-admin must not approve a device-model suggestion through the model.\n");
    exit(1);
}

$status = $pdo->prepare('SELECT status, approved_model_id FROM device_model_suggestions WHERE id = ?');
$status->execute([$suggestionId]);
$pending = $status->fetch(PDO::FETCH_ASSOC);
if (!$pending || $pending['status'] !== 'pending' || $pending['approved_model_id'] !== null) {
    fwrite(STDERR, "Rejected model-level authorization must leave the suggestion pending.\n");
    exit(1);
}

DeviceCatalog::moderateSuggestion($suggestionId, $adminId, true);
$status->execute([$suggestionId]);
$approved = $status->fetch(PDO::FETCH_ASSOC);
if (!$approved || $approved['status'] !== 'approved' || (int) $approved['approved_model_id'] < 1) {
    fwrite(STDERR, "An active administrator must approve the suggestion and link its catalog model.\n");
    exit(1);
}

$model = $pdo->prepare(
    'SELECT id, created_by, approved_by FROM device_models WHERE id = ? AND manufacturer_id = ?'
);
$model->execute([(int) $approved['approved_model_id'], $manufacturerId]);
$modelRow = $model->fetch(PDO::FETCH_ASSOC);
if (!$modelRow || (int) $modelRow['created_by'] !== $ordinaryUserId || (int) $modelRow['approved_by'] !== $adminId) {
    fwrite(STDERR, "Approved catalog model must retain submitter and approver attribution.\n");
    exit(1);
}

$audit = $pdo->prepare(
    "SELECT COUNT(*) FROM security_audit_logs
     WHERE actor_user_id = ? AND action = 'device_model_suggestion.approved'"
);
$audit->execute([$adminId]);
if ((int) $audit->fetchColumn() < 1) {
    fwrite(STDERR, "Catalog moderation must write an audit event in the same transaction.\n");
    exit(1);
}

echo "Device catalog integration passed: model-level authorization, approval and audit attribution.\n";
