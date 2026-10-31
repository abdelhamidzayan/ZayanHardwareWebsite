<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Database;
use App\Models\Issue;
use PDO;
use Throwable;

/**
 * Coordinates a technical content DB transaction with its uploaded media files.
 *
 * The upload metadata must already have passed ContentEditorService::validateUploads().
 * File operations are injected to allow deterministic filesystem failure tests.
 */
final class ContentWriteService
{
    private \Closure $moveFile;
    private \Closure $removeFile;
    private ?\Closure $afterAttachment;
    private \Closure $commitTransaction;
    private string $storageRoot;

    public function __construct(
        private PDO $pdo,
        ?callable $moveFile = null,
        ?callable $removeFile = null,
        ?callable $afterAttachment = null,
        ?string $storageRoot = null,
        ?callable $commitTransaction = null
    ) {
        $this->moveFile = $moveFile !== null
            ? \Closure::fromCallable($moveFile)
            : static fn (string $source, string $destination): bool => move_uploaded_file($source, $destination);
        $this->removeFile = $removeFile !== null
            ? \Closure::fromCallable($removeFile)
            : static fn (string $path): bool => !is_file($path) || unlink($path);
        $this->afterAttachment = $afterAttachment !== null
            ? \Closure::fromCallable($afterAttachment)
            : null;
        $this->commitTransaction = $commitTransaction !== null
            ? \Closure::fromCallable($commitTransaction)
            : static function (PDO $pdo): void { $pdo->commit(); };
        $this->storageRoot = rtrim($storageRoot ?? App::root('storage/private'), '/\\');
        if ($this->storageRoot === '') {
            throw new \InvalidArgumentException('Private storage root is required.');
        }
    }

    public function createIssue(
        int $userId,
        string $type,
        string $title,
        ?string $deviceModel,
        ?string $category,
        string $description,
        string $measurements,
        array $uploads
    ): int {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;
        $issueId = null;

        try {
            $issueId = Issue::create(
                $userId,
                $type,
                $title,
                $deviceModel,
                $category,
                $description,
                $measurements
            );

            $map = $this->storeAttachments($issueId, $userId, $uploads, $description, $movedFiles);
            $resolvedDescription = ContentEditorService::resolveInlineTokens($description, $map);
            Issue::updateDescriptionContent($issueId, $userId, $resolvedDescription);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return $issueId;
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    /**
     * @return array{id:int,body:string}
     */
    public function createReply(
        int $issueId,
        int $userId,
        string $body,
        ?int $parentReplyId,
        array $uploads
    ): array {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;
        $replyId = null;

        try {
            $replyId = Issue::addReply($issueId, $userId, $body, $parentReplyId);
            $map = $this->storeAttachments($issueId, $userId, $uploads, $body, $movedFiles);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $map);
            Issue::updateReplyContent($replyId, $userId, $resolvedBody);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return ['id' => $replyId, 'body' => $resolvedBody];
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    /**
     * @return array{id:int,body:string}
     */
    public function createDiagnosticUpdate(
        int $issueId,
        int $userId,
        string $body,
        array $uploads
    ): array {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;
        $updateId = null;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT content_type, status FROM issues
                 WHERE id = ? AND user_id = ?
                   AND status IN ('open','in_progress','awaiting_confirmation')
                   AND deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$issueId, $userId]);
            $issue = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$issue) {
                throw new \DomainException('not_owner_or_closed');
            }
            if ($issue['content_type'] !== 'help_request') {
                throw new \DomainException('diagnostic_only_for_help_request');
            }
            $status = (string) $issue['status'];

            $this->pdo->prepare(
                'INSERT INTO issue_diagnostic_updates (issue_id, user_id, body) VALUES (?, ?, ?)'
            )->execute([$issueId, $userId, $body]);
            $updateId = (int) $this->pdo->lastInsertId();

            if ($status === 'open') {
                $this->pdo->prepare(
                    "UPDATE issues SET status = 'in_progress', updated_at = UTC_TIMESTAMP() WHERE id = ?"
                )->execute([$issueId]);
            } else {
                $this->pdo->prepare(
                    'UPDATE issues SET updated_at = UTC_TIMESTAMP() WHERE id = ?'
                )->execute([$issueId]);
            }

            $map = $this->storeAttachments($issueId, $userId, $uploads, $body, $movedFiles);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $map);
            Issue::updateDiagnosticUpdateContent($updateId, $userId, $resolvedBody);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return ['id' => $updateId, 'body' => $resolvedBody];
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    /**
     * @return array{body:string}
     */
    public function editIssue(
        int $issueId,
        int $userId,
        string $title,
        string $description,
        string $measurements,
        array $uploads
    ): array {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;

        try {
            Issue::edit($issueId, $userId, $title, $description, $measurements);
            $map = $this->storeAttachments($issueId, $userId, $uploads, $description, $movedFiles);
            $resolvedDescription = ContentEditorService::resolveInlineTokens($description, $map);
            Issue::updateDescriptionContent($issueId, $userId, $resolvedDescription);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return ['body' => $resolvedDescription];
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    /**
     * @return array{body:string}
     */
    public function editReply(
        int $issueId,
        int $replyId,
        int $userId,
        string $body,
        array $uploads
    ): array {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;

        try {
            if (Issue::replyIssueId($replyId) !== $issueId) {
                throw new \DomainException('reply_not_in_issue');
            }
            Issue::editReply($replyId, $userId, $body);
            $map = $this->storeAttachments($issueId, $userId, $uploads, $body, $movedFiles);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $map);
            Issue::updateReplyContent($replyId, $userId, $resolvedBody);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return ['body' => $resolvedBody];
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    /**
     * @return array{body:string}
     */
    public function editDiagnosticUpdate(
        int $issueId,
        int $updateId,
        int $userId,
        string $body,
        array $uploads
    ): array {
        $this->begin();
        $movedFiles = [];
        $commitStarted = false;

        try {
            Issue::editDiagnosticUpdate($issueId, $updateId, $userId, $body);
            $map = $this->storeAttachments($issueId, $userId, $uploads, $body, $movedFiles);
            $resolvedBody = ContentEditorService::resolveInlineTokens($body, $map);
            Issue::updateDiagnosticUpdateContent($updateId, $userId, $resolvedBody);

            $commitStarted = true;
            ($this->commitTransaction)($this->pdo);

            return ['body' => $resolvedBody];
        } catch (Throwable $error) {
            $this->rollbackAndCompensate($error, $issueId, $movedFiles, $commitStarted);
            throw $error;
        }
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('ContentWriteService must own the outer database transaction.');
        }
        $this->pdo->beginTransaction();
    }

    /**
     * @param list<array{tmp:string,size:int,mime:string,ext:string,token?:string}> $uploads
     * @param list<string> $movedFiles
     * @return array<string,int>
     */
    private function storeAttachments(
        int $issueId,
        int $userId,
        array $uploads,
        string $body,
        array &$movedFiles
    ): array {
        if (count($uploads) > 8) {
            throw new \DomainException('too_many_uploads');
        }
        if (!$uploads) return [];

        $referencedTokens = [];
        if (preg_match_all('/\[\[image:([A-Za-z0-9_-]{1,80})\]\]/', $body, $matches)) {
            foreach ($matches[1] as $token) $referencedTokens[$token] = true;
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        $directory = $this->storageRoot . DIRECTORY_SEPARATOR . 'issues' . DIRECTORY_SEPARATOR . $issueId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \DomainException('storage_unavailable');
        }

        $map = [];
        $seenTokens = [];
        foreach ($uploads as $index => $upload) {
            $mime = (string) ($upload['mime'] ?? '');
            $extension = (string) ($upload['ext'] ?? '');
            $temporaryPath = (string) ($upload['tmp'] ?? '');
            $size = (int) ($upload['size'] ?? 0);
            $token = $upload['token'] ?? ('upload-' . $index);

            if (!isset($extensions[$mime]) || $extension !== $extensions[$mime]
                || $temporaryPath === '' || !is_file($temporaryPath)
                || $size < 1 || $size > 8 * 1024 * 1024
                || !is_string($token) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $token)
                || isset($seenTokens[$token])) {
                throw new \DomainException('upload_metadata_invalid');
            }
            $seenTokens[$token] = true;

            if (str_starts_with($mime, 'image/')) {
                ImageUploadPolicy::inspect($temporaryPath);
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;
            $moved = ($this->moveFile)($temporaryPath, $destination);
            $destinationExists = is_file($destination);

            // Some filesystem adapters can finish the move and still report a
            // failure (for example, an error while finalizing the operation).
            // Track any file that actually appeared before raising the failure,
            // otherwise rollback would leave an unjournaled orphan behind.
            if ($destinationExists) {
                $movedFiles[] = $destination;
            }
            if ($moved !== true || !$destinationExists) {
                throw new \DomainException('media_move_failed');
            }
            $isInline = isset($referencedTokens[$token]);
            $attachmentId = Issue::addAttachment(
                $issueId,
                $userId,
                'issues/' . $issueId . '/' . $filename,
                $mime,
                $size,
                $isInline
            );
            if ($isInline) $map[$token] = $attachmentId;

            if ($this->afterAttachment !== null) {
                ($this->afterAttachment)($attachmentId, $index);
            }
        }

        return $map;
    }

    /**
     * If commit was attempted, its outcome can be ambiguous after a connection
     * failure. In that case keep every moved file and log for reconciliation;
     * deleting a file that a committed row references would be data loss.
     *
     * @param list<string> $movedFiles
     */
    private function rollbackAndCompensate(
        Throwable $error,
        ?int $issueId,
        array $movedFiles,
        bool $commitStarted
    ): void {
        $rollbackConfirmed = !$commitStarted;
        if ($this->pdo->inTransaction()) {
            try {
                $this->pdo->rollBack();
                // A successful explicit rollback confirms that the transaction
                // did not commit, even when the commit call itself threw.
                $rollbackConfirmed = true;
            } catch (Throwable $rollbackError) {
                $rollbackConfirmed = false;
                App::logException('CRITICAL', 'content.write.rollback_failed', $rollbackError, [
                    'issue_id' => $issueId,
                    'original_exception' => $error::class,
                ]);
            }
        }

        if (!$rollbackConfirmed) {
            App::logException('CRITICAL', 'content.write.commit_outcome_unknown', $error, [
                'issue_id' => $issueId,
                'moved_files' => $movedFiles,
            ]);
            foreach ($movedFiles as $path) {
                $this->recordRecoveryTask($issueId, $path, 'commit_outcome_unknown');
            }
            return;
        }

        foreach (array_reverse($movedFiles) as $path) {
            try {
                if (is_file($path) && ($this->removeFile)($path) !== true) {
                    App::log('ERROR', 'content.write.file_compensation_failed', [
                        'issue_id' => $issueId,
                        'path' => $path,
                    ]);
                    $this->recordRecoveryTask($issueId, $path, 'compensation_failed');
                }
            } catch (Throwable $cleanupError) {
                App::logException('ERROR', 'content.write.file_compensation_failed', $cleanupError, [
                    'issue_id' => $issueId,
                    'path' => $path,
                ]);
                $this->recordRecoveryTask($issueId, $path, 'compensation_failed');
            }
        }
    }

    private function recordRecoveryTask(?int $issueId, string $absolutePath, string $reason): void
    {
        $prefix = $this->storageRoot . DIRECTORY_SEPARATOR;
        if ($issueId === null || !str_starts_with($absolutePath, $prefix)) {
            App::log('CRITICAL', 'content.write.recovery_task_path_invalid', [
                'issue_id' => $issueId,
                'path' => $absolutePath,
                'reason_code' => $reason,
            ]);
            return;
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($prefix)));
        if (!str_starts_with($relativePath, 'issues/' . $issueId . '/')) {
            App::log('CRITICAL', 'content.write.recovery_task_path_outside_issue', [
                'issue_id' => $issueId,
                'path' => $relativePath,
                'reason_code' => $reason,
            ]);
            return;
        }

        try {
            // Use a separate connection so an ambiguous or still-open content
            // transaction cannot roll this recovery record back with itself.
            Database::newConnection()->prepare(
                "INSERT INTO storage_recovery_tasks
                    (aggregate_type, aggregate_id, file_path, file_path_hash, reason_code, status)
                 VALUES ('issue', ?, ?, ?, ?, 'pending')
                 ON DUPLICATE KEY UPDATE reason_code = VALUES(reason_code), status = 'pending',
                    attempts = attempts + 1, last_attempt_at = NULL, last_error_code = NULL, completed_at = NULL"
            )->execute([
                $issueId,
                $relativePath,
                hash('sha256', $relativePath),
                $reason,
            ]);
        } catch (Throwable $recordError) {
            App::logException('CRITICAL', 'content.write.recovery_task_record_failed', $recordError, [
                'issue_id' => $issueId,
                'path' => $relativePath,
                'reason_code' => $reason,
            ]);
        }
    }
}
