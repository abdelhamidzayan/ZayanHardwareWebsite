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

    echo "Atomic issue/reply write integration passed: successful commit, DB failure rollback, and second-file move compensation.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Content-write integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $removeTree($tempRoot);
}
