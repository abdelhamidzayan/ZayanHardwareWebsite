<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\Issue;
use App\Services\ContentWriteService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Content-write integration is restricted to zayan_fresh_install_test.\n");
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
$tempRoot = sys_get_temp_dir() . '/zayan-content-write-' . bin2hex(random_bytes(6));
$storageRoot = $tempRoot . '/private';
$sourceRoot = $tempRoot . '/sources';
if (!mkdir($storageRoot, 0700, true) || !mkdir($sourceRoot, 0700, true)) {
    fwrite(STDERR, "Unable to create content-write integration directories.\n");
    exit(1);
}

function expectContentWrite(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function makeUploadFile(string $sourceRoot, string $name): string
{
    $path = $sourceRoot . '/' . $name;
    if (file_put_contents($path, "synthetic technical attachment: {$name}") === false) {
        throw new RuntimeException('Unable to create an upload fixture.');
    }
    return $path;
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

$ownerId = 1;
$createdIssueIds = [];
try {
    $source = makeUploadFile($sourceRoot, 'success.pdf');
    $service = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot
    );

    $successTitle = 'Content write success ' . bin2hex(random_bytes(4));
    $issueId = $service->createIssue(
        $ownerId,
        'technical_post',
        $successTitle,
        null,
        null,
        'A synthetic technical post with a committed attachment for integration testing.',
        '',
        [['tmp' => $source, 'size' => filesize($source), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
    );
    $createdIssueIds[] = $issueId;

    $attachmentQuery = $pdo->prepare('SELECT file_path FROM issue_attachments WHERE issue_id = ?');
    $attachmentQuery->execute([$issueId]);
    $relativePath = $attachmentQuery->fetchColumn();
    expectContentWrite(is_string($relativePath), 'Successful post must commit its attachment row.');
    $storedFile = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    expectContentWrite(is_file($storedFile), 'Successful post must keep its attachment file.');
    expectContentWrite(!is_file($source), 'The uploaded temporary file should have moved to private storage.');

    $failingSource = makeUploadFile($sourceRoot, 'fail-after-insert.pdf');
    $failureTitle = 'Content write rollback ' . bin2hex(random_bytes(4));
    $failingService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic failure after attachment insert');
        },
        $storageRoot
    );

    $insertFailureObserved = false;
    try {
        $failingService->createIssue(
            $ownerId,
            'technical_post',
            $failureTitle,
            null,
            null,
            'This issue must roll back when the attachment insert operation fails.',
            '',
            [['tmp' => $failingSource, 'size' => filesize($failingSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $insertFailureObserved = $error->getMessage() === 'synthetic failure after attachment insert';
    }
    expectContentWrite($insertFailureObserved, 'Injected post-attachment failure must be propagated.');
    $issueCount = $pdo->prepare('SELECT COUNT(*) FROM issues WHERE title = ?');
    $issueCount->execute([$failureTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 0, 'Issue row must roll back after attachment insert failure.');
    expectContentWrite(!is_file($failingSource), 'Temporary upload should have moved before the injected failure.');
    $issueRows = $pdo->prepare('SELECT COUNT(*) FROM issue_attachments a JOIN issues i ON i.id = a.issue_id WHERE i.title = ?');
    $issueRows->execute([$failureTitle]);
    expectContentWrite((int) $issueRows->fetchColumn() === 0, 'Attachment DB row must roll back with the issue.');
    $storageFiles = glob($storageRoot . '/issues/*/*') ?: [];
    expectContentWrite(count($storageFiles) === 1, 'Rollback must remove the uploaded file and leave only the successful attachment.');

    $moveOne = makeUploadFile($sourceRoot, 'move-first.pdf');
    $moveTwo = makeUploadFile($sourceRoot, 'move-second.pdf');
    $moveCalls = 0;
    $moveFailureService = new ContentWriteService(
        $pdo,
        static function (string $from, string $to) use (&$moveCalls): bool {
            $moveCalls++;
            if ($moveCalls === 2) return false;
            return rename($from, $to);
        },
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot
    );
    $moveFailureTitle = 'Content write second move failure ' . bin2hex(random_bytes(4));
    $moveFailureObserved = false;
    try {
        $moveFailureService->createIssue(
            $ownerId,
            'technical_post',
            $moveFailureTitle,
            null,
            null,
            'The complete operation must roll back when a later file move fails.',
            '',
            [
                ['tmp' => $moveOne, 'size' => filesize($moveOne), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0'],
                ['tmp' => $moveTwo, 'size' => filesize($moveTwo), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-1'],
            ]
        );
    } catch (DomainException $error) {
        $moveFailureObserved = $error->getMessage() === 'media_move_failed';
    }
    expectContentWrite($moveFailureObserved && $moveCalls === 2, 'Second file move failure must stop publication.');
    $issueCount->execute([$moveFailureTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 0, 'Issue row must roll back after a later file move fails.');
    expectContentWrite(!is_file($moveOne), 'The first moved file must be compensated after the second move fails.');
    expectContentWrite(is_file($moveTwo), 'The second source file must remain available when its move fails.');

    $duplicateOne = makeUploadFile($sourceRoot, 'duplicate-first.pdf');
    $duplicateTwo = makeUploadFile($sourceRoot, 'duplicate-second.pdf');
    $duplicateTitle = 'Content write duplicate token ' . bin2hex(random_bytes(4));
    $duplicateObserved = false;
    try {
        $service->createIssue(
            $ownerId,
            'technical_post',
            $duplicateTitle,
            null,
            null,
            'Duplicate media tokens must be rejected atomically.',
            '',
            [
                ['tmp' => $duplicateOne, 'size' => filesize($duplicateOne), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'same-token'],
                ['tmp' => $duplicateTwo, 'size' => filesize($duplicateTwo), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'same-token'],
            ]
        );
    } catch (DomainException $error) {
        $duplicateObserved = $error->getMessage() === 'upload_metadata_invalid';
    }
    expectContentWrite($duplicateObserved, 'Duplicate upload tokens must be rejected by the write service.');
    $issueCount->execute([$duplicateTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 0, 'Duplicate token rejection must roll back the issue.');
    expectContentWrite(is_file($duplicateTwo), 'The second duplicate-token source file must not move.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 1, 'The first duplicate-token file must be compensated.');

    $parentId = Issue::addReply($issueId, $ownerId, 'Atomic reply parent fixture');
    $replySource = makeUploadFile($sourceRoot, 'reply-fail.pdf');
    $replyService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic reply attachment insert failure');
        },
        $storageRoot
    );
    $replyFailureObserved = false;
    try {
        $replyService->createReply(
            $issueId,
            $ownerId,
            'This reply must roll back when media persistence fails.',
            $parentId,
            [['tmp' => $replySource, 'size' => filesize($replySource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $replyFailureObserved = $error->getMessage() === 'synthetic reply attachment insert failure';
    }
    expectContentWrite($replyFailureObserved, 'Injected reply media failure must be propagated.');
    $replyCount = $pdo->prepare('SELECT COUNT(*) FROM issue_replies WHERE issue_id = ?');
    $replyCount->execute([$issueId]);
    expectContentWrite((int) $replyCount->fetchColumn() === 1, 'Reply row must roll back when media persistence fails.');
    expectContentWrite(!is_file($replySource), 'Reply upload should have moved before the injected failure.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 1, 'Reply rollback must remove its moved media file.');

    $orphanSource = makeUploadFile($sourceRoot, 'recovery-task.pdf');
    $orphanTitle = 'Content write recovery task ' . bin2hex(random_bytes(4));
    $orphanService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => false,
        static function (): void {
            throw new RuntimeException('synthetic failure to force recovery task');
        },
        $storageRoot
    );
    $orphanFailureObserved = false;
    try {
        $orphanService->createIssue(
            $ownerId,
            'technical_post',
            $orphanTitle,
            null,
            null,
            'The moved file must remain tracked when compensation fails.',
            '',
            [['tmp' => $orphanSource, 'size' => filesize($orphanSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $orphanFailureObserved = $error->getMessage() === 'synthetic failure to force recovery task';
    }
    expectContentWrite($orphanFailureObserved, 'Compensation failure injection must be observed.');
    $issueCount->execute([$orphanTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 0, 'Issue row must roll back before a recovery task is recorded.');

    $recoveryQuery = $pdo->prepare(
        "SELECT id, file_path, status FROM storage_recovery_tasks
         WHERE aggregate_type = 'issue' AND reason_code = 'compensation_failed'
           AND status = 'pending' ORDER BY id DESC LIMIT 1"
    );
    $recoveryQuery->execute();
    $recoveryTask = $recoveryQuery->fetch(PDO::FETCH_ASSOC);
    expectContentWrite(is_array($recoveryTask), 'Failed compensation must create a durable recovery task.');
    $orphanPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $recoveryTask['file_path']);
    expectContentWrite(is_file($orphanPath), 'The file must be preserved while its recovery task is pending.');

    if (!unlink($orphanPath)) {
        throw new RuntimeException('Unable to clean the synthetic orphan file.');
    }
    $pdo->prepare(
        "UPDATE storage_recovery_tasks SET status = 'completed', completed_at = UTC_TIMESTAMP()
         WHERE id = ?"
    )->execute([(int) $recoveryTask['id']]);

    $diagnosticIssueId = (new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot
    ))->createIssue(
        $ownerId,
        'help_request',
        'Atomic diagnostic timeline fixture',
        'Fixture Device',
        'power',
        'Synthetic help request for diagnostic update transaction integration.',
        'Power test baseline',
        []
    );
    $diagnosticSource = makeUploadFile($sourceRoot, 'diagnostic-committed.pdf');
    $diagnosticService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot
    );
    $diagnosticCreated = $diagnosticService->createDiagnosticUpdate(
        $diagnosticIssueId,
        $ownerId,
        'Measured current draw and confirmed the power rail value.',
        [['tmp' => $diagnosticSource, 'size' => filesize($diagnosticSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
    );
    expectContentWrite($diagnosticCreated['id'] > 0, 'Diagnostic update ID must be returned after commit.');
    expectContentWrite(str_contains($diagnosticCreated['body'], 'Measured current draw'), 'Diagnostic body must be committed.');
    $diagnosticState = $pdo->prepare('SELECT status FROM issues WHERE id = ?');
    $diagnosticState->execute([$diagnosticIssueId]);
    expectContentWrite($diagnosticState->fetchColumn() === 'in_progress', 'First diagnostic update must move an open help request to in_progress.');
    expectContentWrite(!is_file($diagnosticSource), 'Committed diagnostic media must move to private storage.');

    $diagnosticFailSource = makeUploadFile($sourceRoot, 'diagnostic-rollback.pdf');
    $diagnosticFailService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic diagnostic attachment insert failure');
        },
        $storageRoot
    );
    $diagnosticFailureObserved = false;
    try {
        $diagnosticFailService->createDiagnosticUpdate(
            $diagnosticIssueId,
            $ownerId,
            'This diagnostic update must roll back when attachment persistence fails.',
            [['tmp' => $diagnosticFailSource, 'size' => filesize($diagnosticFailSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $diagnosticFailureObserved = $error->getMessage() === 'synthetic diagnostic attachment insert failure';
    }
    expectContentWrite($diagnosticFailureObserved, 'Injected diagnostic media failure must be observed.');
    $diagnosticCount = $pdo->prepare('SELECT COUNT(*) FROM issue_diagnostic_updates WHERE issue_id = ?');
    $diagnosticCount->execute([$diagnosticIssueId]);
    expectContentWrite((int) $diagnosticCount->fetchColumn() === 1, 'Diagnostic row must roll back when attachment persistence fails.');
    $diagnosticState->execute([$diagnosticIssueId]);
    expectContentWrite($diagnosticState->fetchColumn() === 'in_progress', 'Failed diagnostic update must not alter the issue status.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 2, 'Diagnostic rollback must remove only its failed media file.');

    $originalIssueTitle = $pdo->prepare('SELECT title FROM issues WHERE id = ?');
    $originalIssueTitle->execute([$issueId]);
    $originalIssueTitleValue = (string) $originalIssueTitle->fetchColumn();
    $issueEditSource = makeUploadFile($sourceRoot, 'issue-edit-rollback.pdf');
    $issueEditFailureService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic issue edit attachment failure');
        },
        $storageRoot
    );
    $issueEditFailed = false;
    try {
        $issueEditFailureService->editIssue(
            $issueId,
            $ownerId,
            'Updated title that must roll back',
            'Updated description that must roll back because attachment persistence failed.',
            '',
            [['tmp' => $issueEditSource, 'size' => filesize($issueEditSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $issueEditFailed = $error->getMessage() === 'synthetic issue edit attachment failure';
    }
    expectContentWrite($issueEditFailed, 'Issue edit failure injection must be observed.');
    $originalIssueTitle->execute([$issueId]);
    expectContentWrite($originalIssueTitle->fetchColumn() === $originalIssueTitleValue, 'Issue title must remain unchanged after failed edit.');
    $issueHistoryCount = $pdo->prepare('SELECT COUNT(*) FROM issue_edit_history WHERE issue_id = ?');
    $issueHistoryCount->execute([$issueId]);
    expectContentWrite((int) $issueHistoryCount->fetchColumn() === 0, 'Issue edit history insert must roll back after media failure.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 2, 'Failed issue edit must compensate the moved file.');

    $originalReplyBody = $pdo->prepare('SELECT body FROM issue_replies WHERE id = ?');
    $originalReplyBody->execute([$parentId]);
    $originalReplyBodyValue = (string) $originalReplyBody->fetchColumn();
    $replyEditSource = makeUploadFile($sourceRoot, 'reply-edit-rollback.pdf');
    $replyEditFailureService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic reply edit attachment failure');
        },
        $storageRoot
    );
    $replyEditFailed = false;
    try {
        $replyEditFailureService->editReply(
            $issueId,
            $parentId,
            $ownerId,
            'Edited reply body that must roll back.',
            [['tmp' => $replyEditSource, 'size' => filesize($replyEditSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $replyEditFailed = $error->getMessage() === 'synthetic reply edit attachment failure';
    }
    expectContentWrite($replyEditFailed, 'Reply edit failure injection must be observed.');
    $originalReplyBody->execute([$parentId]);
    expectContentWrite($originalReplyBody->fetchColumn() === $originalReplyBodyValue, 'Reply body must remain unchanged after failed edit.');
    $replyHistoryCount = $pdo->prepare('SELECT COUNT(*) FROM issue_reply_edit_history WHERE issue_reply_id = ?');
    $replyHistoryCount->execute([$parentId]);
    expectContentWrite((int) $replyHistoryCount->fetchColumn() === 0, 'Reply edit history insert must roll back after media failure.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 2, 'Failed reply edit must compensate the moved file.');

    $originalDiagnosticBody = $pdo->prepare('SELECT body FROM issue_diagnostic_updates WHERE id = ?');
    $originalDiagnosticBody->execute([$diagnosticCreated['id']]);
    $originalDiagnosticBodyValue = (string) $originalDiagnosticBody->fetchColumn();
    $diagnosticEditSource = makeUploadFile($sourceRoot, 'diagnostic-edit-rollback.pdf');
    $diagnosticEditFailureService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        static function (): void {
            throw new RuntimeException('synthetic diagnostic edit attachment failure');
        },
        $storageRoot
    );
    $diagnosticEditFailed = false;
    try {
        $diagnosticEditFailureService->editDiagnosticUpdate(
            $diagnosticIssueId,
            $diagnosticCreated['id'],
            $ownerId,
            'Edited diagnostic body that must roll back.',
            [['tmp' => $diagnosticEditSource, 'size' => filesize($diagnosticEditSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $diagnosticEditFailed = $error->getMessage() === 'synthetic diagnostic edit attachment failure';
    }
    expectContentWrite($diagnosticEditFailed, 'Diagnostic edit failure injection must be observed.');
    $originalDiagnosticBody->execute([$diagnosticCreated['id']]);
    expectContentWrite($originalDiagnosticBody->fetchColumn() === $originalDiagnosticBodyValue, 'Diagnostic update body must remain unchanged after failed edit.');
    $diagnosticHistoryCount = $pdo->prepare('SELECT COUNT(*) FROM issue_diagnostic_update_edit_history WHERE diagnostic_update_id = ?');
    $diagnosticHistoryCount->execute([$diagnosticCreated['id']]);
    expectContentWrite((int) $diagnosticHistoryCount->fetchColumn() === 0, 'Diagnostic edit history insert must roll back after media failure.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 2, 'Failed diagnostic edit must compensate the moved file.');

    $wrongTypeRejected = false;
    try {
        $diagnosticService->createDiagnosticUpdate(
            $issueId,
            $ownerId,
            'A technical post cannot receive diagnostic timeline events.',
            []
        );
    } catch (DomainException $error) {
        $wrongTypeRejected = $error->getMessage() === 'diagnostic_only_for_help_request';
    }
    expectContentWrite($wrongTypeRejected, 'Diagnostic timeline must only accept help_request posts.');

    // A failed COMMIT followed by a successful ROLLBACK is a confirmed rollback:
    // remove the moved file and do not create an unnecessary recovery task.
    $commitRollbackSource = makeUploadFile($sourceRoot, 'commit-failure-rollback.pdf');
    $commitRollbackTitle = 'Commit failure rollback ' . bin2hex(random_bytes(4));
    $commitRollbackService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot,
        static function (\PDO $connection): void {
            throw new RuntimeException('synthetic commit failure before commit');
        }
    );
    $commitRollbackObserved = false;
    try {
        $commitRollbackService->createIssue(
            $ownerId,
            'technical_post',
            $commitRollbackTitle,
            null,
            null,
            'The moved file must be compensated after a confirmed rollback.',
            '',
            [['tmp' => $commitRollbackSource, 'size' => filesize($commitRollbackSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $commitRollbackObserved = $error->getMessage() === 'synthetic commit failure before commit';
    }
    expectContentWrite($commitRollbackObserved, 'Injected pre-commit failure must be propagated.');
    $issueCount->execute([$commitRollbackTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 0, 'Confirmed commit rollback must leave no issue row.');
    expectContentWrite(count(glob($storageRoot . '/issues/*/*') ?: []) === 2, 'Confirmed commit rollback must remove its moved file.');
    $unknownTaskCount = $pdo->query(
        "SELECT COUNT(*) FROM storage_recovery_tasks WHERE reason_code = 'commit_outcome_unknown' AND status = 'pending'"
    );
    expectContentWrite((int) $unknownTaskCount->fetchColumn() === 0, 'A confirmed rollback must not create an ambiguous-commit recovery task.');

    // Simulate a COMMIT accepted by MySQL while its acknowledgement is lost.
    // The service must preserve the committed file and record a reconciliation task.
    $ambiguousCommitSource = makeUploadFile($sourceRoot, 'ambiguous-commit.pdf');
    $ambiguousCommitTitle = 'Ambiguous commit outcome ' . bin2hex(random_bytes(4));
    $ambiguousCommitService = new ContentWriteService(
        $pdo,
        static fn (string $from, string $to): bool => rename($from, $to),
        static fn (string $path): bool => !is_file($path) || unlink($path),
        null,
        $storageRoot,
        static function (\PDO $connection): void {
            $connection->commit();
            throw new RuntimeException('synthetic lost commit acknowledgement');
        }
    );
    $ambiguousCommitObserved = false;
    try {
        $ambiguousCommitService->createIssue(
            $ownerId,
            'technical_post',
            $ambiguousCommitTitle,
            null,
            null,
            'A committed attachment must survive an ambiguous commit response.',
            '',
            [['tmp' => $ambiguousCommitSource, 'size' => filesize($ambiguousCommitSource), 'mime' => 'application/pdf', 'ext' => 'pdf', 'token' => 'upload-0']]
        );
    } catch (RuntimeException $error) {
        $ambiguousCommitObserved = $error->getMessage() === 'synthetic lost commit acknowledgement';
    }
    expectContentWrite($ambiguousCommitObserved, 'Lost commit acknowledgement injection must be observed.');
    $issueCount->execute([$ambiguousCommitTitle]);
    expectContentWrite((int) $issueCount->fetchColumn() === 1, 'The ambiguous commit fixture must demonstrate a committed issue.');
    $ambiguousAttachment = $pdo->prepare(
        "SELECT i.id, a.file_path FROM issues i
         JOIN issue_attachments a ON a.issue_id = i.id
         WHERE i.title = ? LIMIT 1"
    );
    $ambiguousAttachment->execute([$ambiguousCommitTitle]);
    $ambiguousRow = $ambiguousAttachment->fetch(\PDO::FETCH_ASSOC);
    expectContentWrite(is_array($ambiguousRow), 'The committed attachment row must remain present after an ambiguous result.');
    $ambiguousStoredFile = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ambiguousRow['file_path']);
    expectContentWrite(is_file($ambiguousStoredFile), 'The file referenced by a committed row must not be removed.');
    $ambiguousTask = $pdo->prepare(
        "SELECT id, status FROM storage_recovery_tasks
         WHERE aggregate_type = 'issue' AND aggregate_id = ?
           AND reason_code = 'commit_outcome_unknown' ORDER BY id DESC LIMIT 1"
    );
    $ambiguousTask->execute([(int) $ambiguousRow['id']]);
    $ambiguousTaskRow = $ambiguousTask->fetch(\PDO::FETCH_ASSOC);
    expectContentWrite(is_array($ambiguousTaskRow) && $ambiguousTaskRow['status'] === 'pending', 'Ambiguous commit must create a pending reconciliation task.');

    echo "Atomic issue/reply/diagnostic write integration passed: commit, rollback, media compensation and durable recovery.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Content-write integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $removeTree($tempRoot);
}
