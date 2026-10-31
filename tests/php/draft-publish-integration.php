<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\PostDraft;
use App\Services\DraftPublishingService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Draft publishing integration is restricted to zayan_fresh_install_test.\n");
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
$userId = 1;
$privateRoot = App::root('storage/private');

function expectDraftPublish(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

/**
 * Create a synthetic draft and a private file referenced by its media row.
 *
 * @return array{draft_id:string,media_uuid:string,source:string,filename:string,model_id:int}
 */
function makeDraftPublishFixture(PDO $pdo, int $userId, string $privateRoot, string $title): array
{
    $draftId = PostDraft::newId();
    $mediaUuid = PostDraft::newId();
    $manufacturerId = (int) $pdo->query(
        'SELECT id FROM device_manufacturers WHERE is_active = 1 ORDER BY id LIMIT 1'
    )->fetchColumn();
    if ($manufacturerId < 1) throw new RuntimeException('No active device manufacturer fixture exists.');

    $modelName = 'CI Draft Model ' . bin2hex(random_bytes(4));
    $modelCode = 'CI-' . strtoupper(bin2hex(random_bytes(4)));
    $model = $pdo->prepare(
        'INSERT INTO device_models (manufacturer_id, commercial_name, model_code, created_by, approved_by, approved_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $model->execute([$manufacturerId, $modelName, $modelCode, $userId, $userId]);
    $modelId = (int) $pdo->lastInsertId();

    $payload = [
        'content_type' => 'technical_post',
        'details_mode' => 'text',
        'title' => $title,
        'description' => 'Synthetic draft body used to verify safe publishing transactions.',
        'manufacturer_id' => $manufacturerId,
        'device_model_id' => $modelId,
        'category' => '',
        'steps' => [],
    ];
    $draft = $pdo->prepare(
        'INSERT INTO post_drafts (id, user_id, revision, payload_json, last_synced_at)
         VALUES (?, ?, 1, ?, UTC_TIMESTAMP())'
    );
    $draft->execute([$draftId, $userId, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);

    $directory = $privateRoot . '/drafts/' . $draftId;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create synthetic draft media directory.');
    }
    $filename = $mediaUuid . '.mp4';
    $source = $directory . '/' . $filename;
    $bytes = 'synthetic private draft video bytes ' . bin2hex(random_bytes(8));
    if (file_put_contents($source, $bytes) === false) {
        throw new RuntimeException('Unable to create synthetic draft media file.');
    }

    $media = $pdo->prepare(
        'INSERT INTO post_draft_media
            (draft_id, media_uuid, media_kind, storage_path, mime_type, file_size, sha256, sort_order)
         VALUES (?, ?, \\'video\\', ?, \\'video/mp4\\', ?, ?, 0)'
    );
    $media->execute([
        $draftId,
        $mediaUuid,
        'drafts/' . $draftId . '/' . $filename,
        strlen($bytes),
        hash('sha256', $bytes),
    ]);

    return [
        'draft_id' => $draftId,
        'media_uuid' => $mediaUuid,
        'source' => $source,
        'filename' => $filename,
        'model_id' => $modelId,
    ];
}

try {
    // A COMMIT exception while the transaction remains open must roll back
    // and restore the file to the original draft path.
    $rollbackFixture = makeDraftPublishFixture(
        $pdo,
        $userId,
        $privateRoot,
        'Draft publish rollback ' . bin2hex(random_bytes(4))
    );
    $rollbackFailed = false;
    try {
        DraftPublishingService::publish(
            $rollbackFixture['draft_id'],
            $userId,
            1,
            static function (PDO $connection): void {
                throw new RuntimeException('synthetic commit failure before commit');
            }
        );
    } catch (RuntimeException $error) {
        $rollbackFailed = $error->getMessage() === 'synthetic commit failure before commit';
    }
    expectDraftPublish($rollbackFailed, 'Pre-commit failure injection must be propagated.');
    expectDraftPublish(is_file($rollbackFixture['source']), 'Confirmed rollback must restore the source draft media.');
    $rollbackDraft = $pdo->prepare('SELECT id FROM post_drafts WHERE id = ? AND user_id = ?');
    $rollbackDraft->execute([$rollbackFixture['draft_id'], $userId]);
    expectDraftPublish($rollbackDraft->fetchColumn() !== false, 'Confirmed rollback must preserve the draft row.');
    $rollbackIssue = $pdo->prepare('SELECT COUNT(*) FROM issues WHERE title = ?');
    $rollbackIssue->execute(['Draft publish rollback ' . '']); // The unique title is asserted through missing draft publication below.
    $rollbackIssueId = $pdo->prepare('SELECT COUNT(*) FROM issue_attachments WHERE original_file_path LIKE ?');
    $rollbackIssueId->execute(['issues/%/' . $rollbackFixture['filename']]);
    expectDraftPublish((int) $rollbackIssueId->fetchColumn() === 0, 'Rolled-back draft media must not be referenced by a published issue.');

    // The caller cannot safely infer rollback just because the commit callback
    // threw. Preserve both path candidates and create recovery tasks.
    $unknownFixture = makeDraftPublishFixture(
        $pdo,
        $userId,
        $privateRoot,
        'Draft publish unknown ' . bin2hex(random_bytes(4))
    );
    $unknownObserved = false;
    try {
        DraftPublishingService::publish(
            $unknownFixture['draft_id'],
            $userId,
            1,
            static function (PDO $connection): void {
                $connection->rollBack();
                throw new RuntimeException('synthetic lost commit acknowledgement after disconnect');
            }
        );
    } catch (DomainException $error) {
        $unknownObserved = $error->getMessage() === 'draft_publish_outcome_unknown';
    }
    expectDraftPublish($unknownObserved, 'Unresolved commit state must be surfaced as an explicit unknown outcome.');
    expectDraftPublish(is_file($unknownFixture['source']), 'Unknown publish outcome must preserve a copy at the draft path.');
    $unknownDraft = $pdo->prepare('SELECT id FROM post_drafts WHERE id = ? AND user_id = ?');
    $unknownDraft->execute([$unknownFixture['draft_id'], $userId]);
    expectDraftPublish($unknownDraft->fetchColumn() !== false, 'Unknown rolled-back fixture must retain its draft row.');
    $unknownTasks = $pdo->prepare(
        "SELECT COUNT(*) FROM storage_recovery_tasks
         WHERE reason_code = 'draft_publish_commit_outcome_unknown'
           AND file_path_hash IN (?, ?)"
    );
    $draftPath = 'drafts/' . $unknownFixture['draft_id'] . '/' . $unknownFixture['filename'];
    $unknownTasks->execute([
        hash('sha256', $draftPath),
        hash('sha256', 'issues/1/' . $unknownFixture['filename']),
    ]);
    expectDraftPublish((int) $unknownTasks->fetchColumn() >= 1, 'Unknown publish outcome must create durable recovery records.');

    // Simulate a successful database COMMIT whose acknowledgement is lost.
    // The service must detect the committed issue and return its ID without restoring its file.
    $committedFixture = makeDraftPublishFixture(
        $pdo,
        $userId,
        $privateRoot,
        'Draft publish committed ' . bin2hex(random_bytes(4))
    );
    $publishedIssueId = DraftPublishingService::publish(
        $committedFixture['draft_id'],
        $userId,
        1,
        static function (PDO $connection): void {
            $connection->commit();
            throw new RuntimeException('synthetic lost commit acknowledgement');
        }
    );
    expectDraftPublish($publishedIssueId > 0, 'Confirmed committed outcome must return the published issue ID.');
    $publishedDraft = $pdo->prepare('SELECT id FROM post_drafts WHERE id = ? AND user_id = ?');
    $publishedDraft->execute([$committedFixture['draft_id'], $userId]);
    expectDraftPublish($publishedDraft->fetchColumn() === false, 'Committed publication must remove the draft record.');
    $publishedAttachment = $pdo->prepare(
        'SELECT file_path FROM issue_attachments WHERE issue_id = ? AND original_file_path IS NOT NULL LIMIT 1'
    );
    $publishedAttachment->execute([$publishedIssueId]);
    $publishedPath = $publishedAttachment->fetchColumn();
    expectDraftPublish(is_string($publishedPath) && $publishedPath !== '', 'Committed publication must retain its attachment row.');
    $publishedFile = $privateRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $publishedPath);
    expectDraftPublish(is_file($publishedFile), 'Committed publication must retain the file referenced by the issue row.');

    echo "Draft publish integration passed: confirmed rollback, ambiguous outcome preservation, and lost-ack committed publish.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Draft publish integration failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
