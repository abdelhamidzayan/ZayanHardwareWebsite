<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\Issue;

$databaseName = (string) (getenv('DB_NAME') ?: '');
if (PHP_SAPI !== 'cli' || $databaseName !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Reply tombstone integration test is restricted to zayan_fresh_install_test.\n");
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
$ownerId = 1;

$issueId = Issue::create(
    $ownerId,
    'technical_post',
    'Reply tombstone integration fixture',
    null,
    null,
    'Disposable integration test content for preserving reply ancestry.',
    ''
);
$parentId = Issue::addReply($issueId, $ownerId, 'Disposable parent reply');
$childId = Issue::addReply($issueId, $ownerId, 'Disposable nested reply', $parentId);

Issue::deleteReply($parentId, $ownerId);

$parentQuery = $pdo->prepare('SELECT parent_reply_id, deleted_at FROM issue_replies WHERE id = ?');
$parentQuery->execute([$parentId]);
$parent = $parentQuery->fetch(PDO::FETCH_ASSOC);

$childQuery = $pdo->prepare('SELECT parent_reply_id, deleted_at FROM issue_replies WHERE id = ?');
$childQuery->execute([$childId]);
$child = $childQuery->fetch(PDO::FETCH_ASSOC);

if (!$parent || $parent['deleted_at'] === null || $parent['parent_reply_id'] !== null) {
    fwrite(STDERR, "The parent reply must remain as a soft-deleted tombstone.\n");
    exit(1);
}
if (!$child || (int) $child['parent_reply_id'] !== $parentId || $child['deleted_at'] !== null) {
    fwrite(STDERR, "The active child reply must retain its original parent_reply_id.\n");
    exit(1);
}

$visibleTree = Issue::replies($issueId, 'newest');
$byId = [];
foreach ($visibleTree as $reply) $byId[(int) $reply['id']] = $reply;

if (!isset($byId[$parentId], $byId[$childId])
    || empty($byId[$parentId]['deleted_at'])
    || empty($byId[$childId]['parent_deleted_at'])
    || (int) $byId[$childId]['parent_reply_id'] !== $parentId) {
    fwrite(STDERR, "The discussion query must return the tombstone and its child with the ancestry intact.\n");
    exit(1);
}

$hardDeleteBlocked = false;
try {
    $pdo->prepare('DELETE FROM issue_replies WHERE id = ?')->execute([$parentId]);
} catch (PDOException) {
    $hardDeleteBlocked = true;
}
if (!$hardDeleteBlocked) {
    fwrite(STDERR, "The database must reject physical deletion of a parent reply that has a child.\\n");
    exit(1);
}

$storedCount = $pdo->prepare(
    'SELECT COUNT(*) FROM issue_replies WHERE id IN (?, ?)'
);
$storedCount->execute([$parentId, $childId]);
if ((int) $storedCount->fetchColumn() !== 2) {
    fwrite(STDERR, "Both reply records must remain in the database.\n");
    exit(1);
}

echo "Reply tombstone integration test passed; parent-child ancestry is retained.\n";
