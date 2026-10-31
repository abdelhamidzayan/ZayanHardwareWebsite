<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\PostDraft;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Draft media recovery integration is restricted to zayan_fresh_install_test.\n");
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
$tempRoot = sys_get_temp_dir() . '/zayan-draft-media-recovery-' . bin2hex(random_bytes(6));
if (!mkdir($tempRoot, 0700, true)) {
    fwrite(STDERR, "Unable to create draft-media recovery test directory.\n");
    exit(1);
}

function expectDraftMediaRecovery(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$taskHashes = [];
$draftStorageRootMissing = $tempRoot . '/missing-drafts';
$draftDirectoryMissingRoot = $tempRoot . '/available-drafts';

$exitCode = 0;
try {
    if (realpath($draftStorageRootMissing) !== false) {
        throw new RuntimeException('Missing-root fixture unexpectedly exists.');
    }
    if (!mkdir($draftDirectoryMissingRoot, 0700, true)) {
        throw new RuntimeException('Unable to create the accessible-root fixture.');
    }

    $cleanup = new ReflectionMethod(PostDraft::class, 'removePrivateFile');
    $cleanup->setAccessible(true);

    $rootUnavailablePath = 'drafts/' . PostDraft::newId() . '/root-unavailable.pdf';
    $rootUnavailableHash = hash('sha256', $rootUnavailablePath);
    $taskHashes[] = $rootUnavailableHash;
    $rootUnavailableResult = $cleanup->invoke(null, $rootUnavailablePath, $draftStorageRootMissing);
    expectDraftMediaRecovery($rootUnavailableResult === false, 'Unavailable storage root must not be reported as a completed cleanup.');

    $rootTask = $pdo->prepare(
        'SELECT reason_code, status FROM storage_recovery_tasks WHERE file_path_hash = ? LIMIT 1'
    );
    $rootTask->execute([$rootUnavailableHash]);
    $rootTaskRow = $rootTask->fetch(PDO::FETCH_ASSOC);
    expectDraftMediaRecovery(
        is_array($rootTaskRow)
            && $rootTaskRow['reason_code'] === 'draft_media_storage_root_unavailable'
            && $rootTaskRow['status'] === 'pending',
        'Unavailable storage root must create a pending durable recovery task.'
    );

    $directoryUnavailablePath = 'drafts/' . PostDraft::newId() . '/directory-unavailable.pdf';
    $directoryUnavailableHash = hash('sha256', $directoryUnavailablePath);
    $taskHashes[] = $directoryUnavailableHash;
    $directoryUnavailableResult = $cleanup->invoke(null, $directoryUnavailablePath, $draftDirectoryMissingRoot);
    expectDraftMediaRecovery($directoryUnavailableResult === false, 'Unresolvable draft directory must not be reported as a completed cleanup.');

    $directoryTask = $pdo->prepare(
        'SELECT reason_code, status FROM storage_recovery_tasks WHERE file_path_hash = ? LIMIT 1'
    );
    $directoryTask->execute([$directoryUnavailableHash]);
    $directoryTaskRow = $directoryTask->fetch(PDO::FETCH_ASSOC);
    expectDraftMediaRecovery(
        is_array($directoryTaskRow)
            && $directoryTaskRow['reason_code'] === 'draft_media_directory_unavailable'
            && $directoryTaskRow['status'] === 'pending',
        'Unresolvable draft directory must create a pending durable recovery task.'
    );

    // Whole-draft directory cleanup must also journal when its storage root
    // cannot be resolved, even when no individual media paths are available.
    $directoryCleanup = new ReflectionMethod(PostDraft::class, 'removePrivateDirectory');
    $directoryCleanup->setAccessible(true);
    $unavailableDraftId = PostDraft::newId();
    $directoryTaskPath = 'drafts/' . $unavailableDraftId . '/__directory__';
    $directoryTaskHash = hash('sha256', $directoryTaskPath);
    $taskHashes[] = $directoryTaskHash;
    $directoryCleanup->invoke(null, $unavailableDraftId, $draftStorageRootMissing);
    $wholeDirectoryTask = $pdo->prepare(
        'SELECT reason_code, status FROM storage_recovery_tasks WHERE file_path_hash = ? LIMIT 1'
    );
    $wholeDirectoryTask->execute([$directoryTaskHash]);
    $wholeDirectoryRow = $wholeDirectoryTask->fetch(PDO::FETCH_ASSOC);
    expectDraftMediaRecovery(
        is_array($wholeDirectoryRow)
            && $wholeDirectoryRow['reason_code'] === 'draft_media_directory_root_unavailable'
            && $wholeDirectoryRow['status'] === 'pending',
        'Unavailable root during whole-draft cleanup must create a pending recovery task.'
    );

    // A UUID folder symlink must not let file cleanup enter another draft.
    $symlinkTargetId = PostDraft::newId();
    $symlinkDraftId = PostDraft::newId();
    $symlinkTargetDirectory = $draftDirectoryMissingRoot . '/' . $symlinkTargetId;
    $symlinkDraftDirectory = $draftDirectoryMissingRoot . '/' . $symlinkDraftId;
    if (!mkdir($symlinkTargetDirectory, 0700, true)) {
        throw new RuntimeException('Unable to create symlink target draft directory.');
    }
    $protectedFile = $symlinkTargetDirectory . '/keep-me.pdf';
    if (file_put_contents($protectedFile, 'keep another draft file') === false) {
        throw new RuntimeException('Unable to create symlink sentinel file.');
    }
    if (!symlink($symlinkTargetDirectory, $symlinkDraftDirectory)) {
        throw new RuntimeException('Unable to create directory symlink fixture.');
    }

    $symlinkRelative = 'drafts/' . $symlinkDraftId . '/keep-me.pdf';
    $symlinkHash = hash('sha256', $symlinkRelative);
    $taskHashes[] = $symlinkHash;
    $symlinkResult = $cleanup->invoke(null, $symlinkRelative, $draftDirectoryMissingRoot);
    expectDraftMediaRecovery($symlinkResult === false, 'Draft file cleanup must reject a UUID directory symlink.');
    expectDraftMediaRecovery(is_file($protectedFile), 'Cleanup must never remove another draft file through a symlink.');

    $symlinkTask = $pdo->prepare(
        'SELECT reason_code, status FROM storage_recovery_tasks WHERE file_path_hash = ? LIMIT 1'
    );
    $symlinkTask->execute([$symlinkHash]);
    $symlinkTaskRow = $symlinkTask->fetch(PDO::FETCH_ASSOC);
    expectDraftMediaRecovery(
        is_array($symlinkTaskRow)
            && $symlinkTaskRow['reason_code'] === 'draft_media_directory_rejected'
            && $symlinkTaskRow['status'] === 'pending',
        'Rejected UUID directory symlink must be recorded for inspection.'
    );

    // The whole-directory remover must reject a cross-draft directory symlink
    // rather than calling rmdir() against its real target.
    $emptyTargetId = PostDraft::newId();
    $emptySymlinkId = PostDraft::newId();
    $emptyTargetDirectory = $draftDirectoryMissingRoot . '/' . $emptyTargetId;
    $emptySymlinkDirectory = $draftDirectoryMissingRoot . '/' . $emptySymlinkId;
    if (!mkdir($emptyTargetDirectory, 0700, true) || !symlink($emptyTargetDirectory, $emptySymlinkDirectory)) {
        throw new RuntimeException('Unable to create empty-directory symlink fixture.');
    }
    $directorySymlinkTaskPath = 'drafts/' . $emptySymlinkId . '/__directory__';
    $directorySymlinkTaskHash = hash('sha256', $directorySymlinkTaskPath);
    $taskHashes[] = $directorySymlinkTaskHash;
    $directoryCleanup->invoke(null, $emptySymlinkId, $draftDirectoryMissingRoot);
    expectDraftMediaRecovery(is_dir($emptyTargetDirectory), 'Whole-directory cleanup must not remove another draft directory through a symlink.');

    $directorySymlinkTask = $pdo->prepare(
        'SELECT reason_code, status FROM storage_recovery_tasks WHERE file_path_hash = ? LIMIT 1'
    );
    $directorySymlinkTask->execute([$directorySymlinkTaskHash]);
    $directorySymlinkRow = $directorySymlinkTask->fetch(PDO::FETCH_ASSOC);
    expectDraftMediaRecovery(
        is_array($directorySymlinkRow)
            && $directorySymlinkRow['reason_code'] === 'draft_media_directory_rejected'
            && $directorySymlinkRow['status'] === 'pending',
        'Rejected whole-directory symlink must be recorded for inspection.'
    );

    echo "Draft media recovery integration passed: unavailable roots/directories are journaled and cross-draft symlinks are rejected.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Draft media recovery integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($taskHashes !== []) {
        $placeholders = implode(',', array_fill(0, count($taskHashes), '?'));
        $pdo->prepare(
            "UPDATE storage_recovery_tasks
             SET status = 'completed', completed_at = UTC_TIMESTAMP(),
                 last_attempt_at = UTC_TIMESTAMP(), last_error_code = NULL
             WHERE file_path_hash IN ($placeholders) AND status = 'pending'"
        )->execute($taskHashes);
    }
    $removeTree = static function (string $path) use (&$removeTree): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    };
    $removeTree($tempRoot);
}
exit($exitCode);
