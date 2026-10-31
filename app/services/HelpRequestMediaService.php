<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Database;
use App\Models\HelpRequest;
use PDO;
use Throwable;

final class HelpRequestMediaService
{
    public static function validate(mixed $files): array
    {
        if ($files === null) return [];
        if (!is_array($files) || !isset($files['name'], $files['error'], $files['tmp_name'], $files['size'])
            || !is_array($files['name']) || !is_array($files['error'])
            || !is_array($files['tmp_name']) || !is_array($files['size'])) {
            throw new \DomainException('invalid_attachment_structure');
        }
        $fileCount = count($files['name']);
        if (count($files['error']) !== $fileCount || count($files['tmp_name']) !== $fileCount
            || count($files['size']) !== $fileCount) {
            throw new \DomainException('invalid_attachment_structure');
        }
        foreach (array_keys($files['name']) as $index) {
            if (!array_key_exists($index, $files['error'])
                || !array_key_exists($index, $files['tmp_name'])
                || !array_key_exists($index, $files['size'])) {
                throw new \DomainException('invalid_attachment_structure');
            }
        }
        if ($fileCount > 8) throw new \DomainException('too_many_attachments');
        $allowed = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $uploads = [];
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $tmp = (string) ($files['tmp_name'][$index] ?? '');
            $size = (int) ($files['size'][$index] ?? 0);
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 8 * 1024 * 1024) {
                throw new \DomainException('invalid_attachment_size');
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!isset($allowed[$mime])) throw new \DomainException('invalid_attachment_type');
            if (str_starts_with($mime, 'image/')) {
                try {
                    ImageUploadPolicy::inspect($tmp);
                } catch (\DomainException $error) {
                    throw new \DomainException('invalid_attachment_dimensions', 0, $error);
                }
            }
            $uploads[] = ['tmp' => $tmp, 'size' => $size, 'mime' => $mime, 'ext' => $allowed[$mime]];
        }
        return $uploads;
    }

    /**
     * Save each private attachment with a DB transaction and filesystem compensation.
     * The surrounding message remains valid when an individual attachment fails;
     * the returned count lets the controller report that partial result accurately.
     *
     * @param array<int,array{tmp:string,size:int,mime:string,ext:string}> $uploads
     */
    public static function store(
        int $requestId,
        int $messageId,
        int $userId,
        array $uploads,
        ?callable $moveFile = null,
        ?callable $removeFile = null,
        ?callable $afterAttachment = null,
        ?callable $commitTransaction = null,
        ?string $storageRoot = null
    ): int {
        if (!$uploads) return 0;

        $move = $moveFile !== null
            ? \Closure::fromCallable($moveFile)
            : static fn (string $source, string $target): bool => move_uploaded_file($source, $target);
        $remove = $removeFile !== null
            ? \Closure::fromCallable($removeFile)
            : static fn (string $path): bool => !is_file($path) || unlink($path);
        $afterInsert = $afterAttachment !== null ? \Closure::fromCallable($afterAttachment) : null;
        $commit = $commitTransaction !== null
            ? \Closure::fromCallable($commitTransaction)
            : static function (PDO $pdo): void { $pdo->commit(); };

        $privateRoot = rtrim($storageRoot ?? App::root('storage/private'), '/\\');
        if ($privateRoot === '') return count($uploads);
        $directory = $privateRoot . DIRECTORY_SEPARATOR . 'help-requests' . DIRECTORY_SEPARATOR . $requestId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            return count($uploads);
        }

        $pdo = Database::connection();
        $failed = 0;
        foreach ($uploads as $upload) {
            $filename = bin2hex(random_bytes(20)) . '.' . $upload['ext'];
            $target = $directory . DIRECTORY_SEPARATOR . $filename;
            $relativePath = 'help-requests/' . $requestId . '/' . $filename;
            $commitStarted = false;
            $moved = false;

            try {
                $pdo->beginTransaction();
                if (($move)($upload['tmp'], $target) !== true) {
                    $moved = is_file($target);
                    throw new \RuntimeException('help_attachment_move_failed');
                }
                $moved = true;

                HelpRequest::addAttachment(
                    $requestId,
                    $messageId,
                    $userId,
                    $relativePath,
                    $upload['mime'],
                    (int) $upload['size']
                );
                if ($afterInsert !== null) ($afterInsert)($requestId, $messageId, $relativePath);

                $commitStarted = true;
                ($commit)($pdo);
            } catch (Throwable $error) {
                $rollbackConfirmed = !$commitStarted;
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                        $rollbackConfirmed = true;
                    } catch (Throwable $rollbackError) {
                        $rollbackConfirmed = false;
                        App::logException('CRITICAL', 'help_request.media_rollback_failed', $rollbackError, [
                            'request_id' => $requestId,
                            'message_id' => $messageId,
                            'user_id' => $userId,
                        ]);
                    }
                }

                // The move callback may have completed the move before throwing.
                $moved = $moved || is_file($target);
                if ($moved && !$rollbackConfirmed) {
                    self::recordRecoveryTask($requestId, $relativePath, 'commit_outcome_unknown');
                } elseif ($moved) {
                    try {
                        if (is_file($target) && ($remove)($target) !== true) {
                            self::recordRecoveryTask($requestId, $relativePath, 'compensation_failed');
                        }
                    } catch (Throwable $cleanupError) {
                        App::logException('ERROR', 'help_request.media_compensation_failed', $cleanupError, [
                            'request_id' => $requestId,
                            'message_id' => $messageId,
                            'user_id' => $userId,
                        ]);
                        self::recordRecoveryTask($requestId, $relativePath, 'compensation_failed');
                    }
                }

                App::logException('ERROR', 'help_request.attachment_save_failed', $error, [
                    'request_id' => $requestId,
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'commit_started' => $commitStarted,
                    'rollback_confirmed' => $rollbackConfirmed,
                ]);
                $failed++;
            }
        }
        return $failed;
    }

    private static function recordRecoveryTask(int $requestId, string $relativePath, string $reason): void
    {
        $expectedPrefix = 'help-requests/' . $requestId . '/';
        if (!str_starts_with($relativePath, $expectedPrefix)
            || str_contains($relativePath, '..')
            || str_contains($relativePath, '\\')) {
            App::log('CRITICAL', 'help_request.media_recovery_path_rejected', [
                'request_id' => $requestId,
                'reason_code' => $reason,
            ]);
            return;
        }

        try {
            Database::connection()->prepare(
                "INSERT INTO storage_recovery_tasks
                    (aggregate_type, aggregate_id, file_path, file_path_hash, reason_code,
                     status, attempts, last_attempt_at, last_error_code)
                 VALUES ('help_request', ?, ?, ?, ?, 'pending', 1, UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE
                    aggregate_id = VALUES(aggregate_id),
                    reason_code = VALUES(reason_code),
                    status = 'pending',
                    attempts = attempts + 1,
                    last_attempt_at = UTC_TIMESTAMP(),
                    last_error_code = VALUES(last_error_code),
                    completed_at = NULL"
            )->execute([
                $requestId,
                $relativePath,
                hash('sha256', $relativePath),
                $reason,
                $reason === 'commit_outcome_unknown' ? 'commit_unknown' : 'unlink_failed',
            ]);
        } catch (Throwable $error) {
            App::logException('CRITICAL', 'help_request.media_recovery_task_write_failed', $error, [
                'request_id' => $requestId,
                'reason_code' => $reason,
            ]);
        }
    }
}
