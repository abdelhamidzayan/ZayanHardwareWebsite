<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\HelpRequest;
use App\Services\HelpRequestMediaService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Personal-help media integration is restricted to zayan_fresh_install_test.\n");
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
$tempRoot = sys_get_temp_dir() . '/zayan-help-media-' . bin2hex(random_bytes(6));
$storageRoot = $tempRoot . '/private';
$sourceRoot = $tempRoot . '/sources';
if (!mkdir($storageRoot, 0700, true) || !mkdir($sourceRoot, 0700, true)) {
    fwrite(STDERR, "Unable to create personal-help integration directories.\n");
    exit(1);
}

function expectHelpMedia(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function makeHelpMediaUpload(string $sourceRoot, string $name): array
{
    $path = $sourceRoot . '/' . $name;
    if (file_put_contents($path, "synthetic private help attachment: {$name}") === false) {
        throw new RuntimeException('Unable to create a personal-help attachment fixture.');
    }
    return ['tmp' => $path, 'size' => filesize($path), 'mime' => 'application/pdf', 'ext' => 'pdf'];
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

try {
    $requesterId = 1;
    $helperId = 3;
    $pdo->prepare("UPDATE users SET account_status = 'active', accept_help_requests = 1 WHERE id = ?")
        ->execute([$helperId]);

    $created = HelpRequest::createWithInitialMessage(
        $requesterId,
        $helperId,
        'Help media integration ' . bin2hex(random_bytes(4)),
        'Synthetic initial message for private attachment transaction testing.'
    );
    $requestId = $created['request_id'];
    $messageId = $created['message_id'];
    $targetDirectory = $storageRoot . '/help-requests/' . $requestId;
    $normalMove = static fn (string $from, string $to): bool => rename($from, $to);
    $normalRemove = static fn (string $path): bool => !is_file($path) || unlink($path);
    $attachmentCount = $pdo->prepare('SELECT COUNT(*) FROM help_request_attachments WHERE request_id = ?');

    $successUpload = makeHelpMediaUpload($sourceRoot, 'help-success.pdf');
    $successFailed = HelpRequestMediaService::store(
        $requestId, $messageId, $requesterId, [$successUpload],
        $normalMove, $normalRemove, null, null, $storageRoot
    );
    expectHelpMedia($successFailed === 0, 'Successful help attachment must not report a failure.');
    $attachmentCount->execute([$requestId]);
    expectHelpMedia((int) $attachmentCount->fetchColumn() === 1, 'Successful help attachment row must commit.');
    $successRow = $pdo->prepare('SELECT id, file_path FROM help_request_attachments WHERE request_id = ? ORDER BY id DESC LIMIT 1');
    $successRow->execute([$requestId]);
    $successAttachment = $successRow->fetch(PDO::FETCH_ASSOC);
    expectHelpMedia(is_array($successAttachment), 'Successful help attachment metadata must be available.');
    $successAttachmentId = (int) $successAttachment['id'];
    $successRelative = (string) $successAttachment['file_path'];
    $successFile = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $successRelative);
    expectHelpMedia(is_file($successFile), 'Successful help attachment file must remain present.');

    expectHelpMedia(HelpRequest::findForParticipant($requestId, $requesterId) !== null, 'Requester must be able to find their own help request.');
    expectHelpMedia(HelpRequest::findForParticipant($requestId, $helperId) !== null, 'Assigned helper must be able to find the help request.');
    expectHelpMedia(HelpRequest::findForParticipant($requestId, 999999) === null, 'A nonparticipant must not find a private help request.');
    expectHelpMedia(HelpRequest::attachmentForParticipant($successAttachmentId, $requesterId) !== null, 'Requester must be able to access their private attachment metadata.');
    expectHelpMedia(HelpRequest::attachmentForParticipant($successAttachmentId, $helperId) !== null, 'Assigned helper must be able to access private attachment metadata.');
    expectHelpMedia(HelpRequest::attachmentForParticipant($successAttachmentId, 999999) === null, 'A nonparticipant must not access private attachment metadata.');

    $unauthorizedAttachmentRejected = false;
    try {
        HelpRequest::addAttachment($requestId, $messageId, 999999, 'help-requests/invalid/forbidden.pdf', 'application/pdf', 10);
    } catch (DomainException $error) {
        $unauthorizedAttachmentRejected = $error->getMessage() === 'attachment_message_mismatch';
    }
    expectHelpMedia($unauthorizedAttachmentRejected, 'A nonparticipant must not attach files to another user\'s help conversation.');

    $insertionFailure = HelpRequestMediaService::store(
        $requestId, $messageId, $requesterId,
        [makeHelpMediaUpload($sourceRoot, 'help-insert-failure.pdf')],
        $normalMove, $normalRemove,
        static function (): void { throw new RuntimeException('synthetic after-insert failure'); },
        null,
        $storageRoot
    );
    expectHelpMedia($insertionFailure === 1, 'Injected attachment failure must be reported.');
    $attachmentCount->execute([$requestId]);
    expectHelpMedia((int) $attachmentCount->fetchColumn() === 1, 'Failed attachment transaction must roll back its row.');
    expectHelpMedia(count(glob($targetDirectory . '/*') ?: []) === 1, 'Confirmed rollback must remove the moved attachment file.');

    $cleanupFailure = HelpRequestMediaService::store(
        $requestId, $messageId, $requesterId,
        [makeHelpMediaUpload($sourceRoot, 'help-cleanup-failure.pdf')],
        $normalMove,
        static fn (string $path): bool => false,
        static function (): void { throw new RuntimeException('synthetic after-insert failure with unlink failure'); },
        null,
        $storageRoot
    );
    expectHelpMedia($cleanupFailure === 1, 'Attachment cleanup failure must be reported.');
    $pendingCleanup = $pdo->prepare(
        "SELECT file_path, status FROM storage_recovery_tasks
         WHERE aggregate_type = 'help_request' AND aggregate_id = ?
           AND reason_code = 'compensation_failed' ORDER BY id DESC LIMIT 1"
    );
    $pendingCleanup->execute([$requestId]);
    $cleanupTask = $pendingCleanup->fetch(PDO::FETCH_ASSOC);
    expectHelpMedia(is_array($cleanupTask) && $cleanupTask['status'] === 'pending', 'Failed private-file compensation must create a pending recovery task.');
    $orphanFile = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanupTask['file_path']);
    expectHelpMedia(is_file($orphanFile), 'A file with failed compensation must be preserved for reconciliation.');

    $preCommitFailure = HelpRequestMediaService::store(
        $requestId, $messageId, $requesterId,
        [makeHelpMediaUpload($sourceRoot, 'help-commit-failure.pdf')],
        $normalMove, $normalRemove, null,
        static function (PDO $connection): void { throw new RuntimeException('synthetic commit failure before commit'); },
        $storageRoot
    );
    expectHelpMedia($preCommitFailure === 1, 'Injected pre-commit failure must be reported.');
    $attachmentCount->execute([$requestId]);
    expectHelpMedia((int) $attachmentCount->fetchColumn() === 1, 'Pre-commit exception with confirmed rollback must not keep an attachment row.');
    expectHelpMedia(count(glob($targetDirectory . '/*') ?: []) === 2, 'Pre-commit exception with confirmed rollback must remove its moved file and retain only successful/orphan files.');

    $ambiguousCommit = HelpRequestMediaService::store(
        $requestId, $messageId, $requesterId,
        [makeHelpMediaUpload($sourceRoot, 'help-ambiguous-commit.pdf')],
        $normalMove, $normalRemove, null,
        static function (PDO $connection): void {
            $connection->commit();
            throw new RuntimeException('synthetic lost commit acknowledgement');
        },
        $storageRoot
    );
    expectHelpMedia($ambiguousCommit === 1, 'Ambiguous commit result must be reported for reconciliation.');
    $attachmentCount->execute([$requestId]);
    expectHelpMedia((int) $attachmentCount->fetchColumn() === 2, 'Ambiguous commit fixture must retain the committed attachment row.');
    $ambiguousTask = $pdo->prepare(
        "SELECT file_path, status FROM storage_recovery_tasks
         WHERE aggregate_type = 'help_request' AND aggregate_id = ?
           AND reason_code = 'commit_outcome_unknown' ORDER BY id DESC LIMIT 1"
    );
    $ambiguousTask->execute([$requestId]);
    $ambiguousRow = $ambiguousTask->fetch(PDO::FETCH_ASSOC);
    expectHelpMedia(is_array($ambiguousRow) && $ambiguousRow['status'] === 'pending', 'Ambiguous private attachment commit must be journaled as pending.');
    $ambiguousFile = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ambiguousRow['file_path']);
    expectHelpMedia(is_file($ambiguousFile), 'Ambiguous committed attachment file must not be deleted.');

    echo "Personal-help media integration passed: attachment transactions, rollback, compensation and ambiguous commit recovery.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Personal-help media integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $removeTree($tempRoot);
}
