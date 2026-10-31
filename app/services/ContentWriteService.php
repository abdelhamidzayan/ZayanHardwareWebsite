<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
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
    private string $storageRoot;

    public function __construct(
        private PDO $pdo,
        ?callable $moveFile = null,
        ?callable $removeFile = null,
        ?callable $afterAttachment = null,
        ?string $storageRoot = null
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
            $this->pdo->commit();

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
            $this->pdo->commit();

            return ['id' => $replyId, 'body' => $resolvedBody];
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
            if ($moved !== true || !is_file($destination)) {
                throw new \DomainException('media_move_failed');
            }

            // Track the file immediately after the successful move so all later
            // failures compensate it, including an attachment INSERT failure.
            $movedFiles[] = $destination;
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
                $rollbackConfirmed = !$commitStarted;
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
            return;
        }

        foreach (array_reverse($movedFiles) as $path) {
            try {
                if (is_file($path) && ($this->removeFile)($path) !== true) {
                    App::log('ERROR', 'content.write.file_compensation_failed', [
                        'issue_id' => $issueId,
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $cleanupError) {
                App::logException('ERROR', 'content.write.file_compensation_failed', $cleanupError, [
                    'issue_id' => $issueId,
                    'path' => $path,
                ]);
            }
        }
    }
}
