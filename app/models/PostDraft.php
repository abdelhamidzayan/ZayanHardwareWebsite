<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\App;
use App\Core\Model;

final class PostDraft extends Model
{
    public static function newId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public static function listForUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, revision, payload_json, created_at, updated_at
             FROM post_drafts WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 100'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode((string) $row['payload_json'], true);
            $payload = is_array($payload) ? $payload : [];
            $row['title'] = (string) ($payload['title'] ?? '');
            $row['content_type'] = (string) ($payload['content_type'] ?? 'technical_post');
            $row['details_mode'] = (string) ($payload['details_mode'] ?? 'text');
            unset($row['payload_json']);
        }
        return $rows;
    }

    public static function findForUser(string $id, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, revision, payload_json, created_at, updated_at
             FROM post_drafts WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $payload = json_decode((string) $row['payload_json'], true);
        $row['payload'] = is_array($payload) ? $payload : [];
        unset($row['payload_json']);
        $row['media'] = self::mediaForUser($id, $userId);
        return $row;
    }

    /**
     * Optimistic concurrency: a stale device never silently replaces a newer draft.
     * Returns either the saved revision or the current server payload on conflict.
     */
    public static function save(?string $id, int $userId, array $payload, int $expectedRevision): array
    {
        $id = $id ?? self::newId();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT revision, payload_json FROM post_drafts WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$id, $userId]);
            $current = $stmt->fetch();

            if (!$current) {
                if ($expectedRevision !== 0) {
                    $pdo->rollBack();
                    return ['conflict' => true, 'missing' => true, 'id' => $id, 'revision' => 0, 'payload' => null];
                }
                $pdo->prepare(
                    'INSERT INTO post_drafts (id, user_id, revision, payload_json, last_synced_at)
                     VALUES (?, ?, 1, ?, UTC_TIMESTAMP())'
                )->execute([$id, $userId, $json]);
                $pdo->commit();
                return ['conflict' => false, 'id' => $id, 'revision' => 1];
            }

            $revision = (int) $current['revision'];
            if ($expectedRevision !== $revision) {
                $serverPayload = json_decode((string) $current['payload_json'], true);
                $pdo->commit();
                return [
                    'conflict' => true,
                    'missing' => false,
                    'id' => $id,
                    'revision' => $revision,
                    'payload' => is_array($serverPayload) ? $serverPayload : [],
                ];
            }

            $next = $revision + 1;
            $pdo->prepare(
                'UPDATE post_drafts SET payload_json = ?, revision = ?, last_synced_at = UTC_TIMESTAMP(),
                    updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?'
            )->execute([$json, $next, $id, $userId]);
            $pdo->commit();
            return ['conflict' => false, 'id' => $id, 'revision' => $next];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function mediaForUser(string $draftId, int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT m.media_uuid, m.media_kind, m.mime_type, m.file_size, m.editor_state,
                    m.sort_order, m.duration_ms, m.rendered_path, m.created_at
             FROM post_draft_media m
             JOIN post_drafts d ON d.id = m.draft_id
             WHERE m.draft_id = ? AND d.user_id = ?
             ORDER BY m.sort_order, m.id'
        );
        $stmt->execute([$draftId, $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $state = $row['editor_state'] !== null ? json_decode((string) $row['editor_state'], true) : null;
            $row['editor_state'] = is_array($state) ? $state : null;
        }
        return $rows;
    }

    public static function addMedia(
        string $draftId,
        string $mediaUuid,
        string $kind,
        string $path,
        string $mime,
        int $size,
        string $sha256,
        ?int $durationMs,
        int $sortOrder,
        int $userId,
        int $expectedRevision
    ): int {
        $pdo = self::db();
        self::beginRevisionMutation($draftId, $userId, $expectedRevision);
        try {
            $pdo->prepare(
                'INSERT INTO post_draft_media
                    (draft_id, media_uuid, media_kind, storage_path, mime_type, file_size, sha256, duration_ms, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$draftId, $mediaUuid, $kind, $path, $mime, $size, $sha256, $durationMs, $sortOrder]);

            return self::finishRevisionMutation($draftId, $userId);
        } catch (\Throwable $e) {
            self::rollbackRevisionMutation();
            throw $e;
        }
    }

    public static function setRenderedPath(
        string $draftId,
        string $mediaUuid,
        int $userId,
        string $path,
        string $mimeType,
        string $sha256,
        int $expectedRevision
    ): ?int {
        $pdo = self::db();
        self::beginRevisionMutation($draftId, $userId, $expectedRevision);
        try {
            $stmt = $pdo->prepare(
                'SELECT media_kind FROM post_draft_media WHERE draft_id = ? AND media_uuid = ? LIMIT 1'
            );
            $stmt->execute([$draftId, $mediaUuid]);
            if ($stmt->fetchColumn() !== 'image') {
                self::rollbackRevisionMutation();
                return null;
            }
            $pdo->prepare(
                'UPDATE post_draft_media SET rendered_path = ?, rendered_mime_type = ?, rendered_sha256 = ?
                 WHERE draft_id = ? AND media_uuid = ?'
            )->execute([$path, $mimeType, $sha256, $draftId, $mediaUuid]);

            return self::finishRevisionMutation($draftId, $userId);
        } catch (\Throwable $e) {
            self::rollbackRevisionMutation();
            throw $e;
        }
    }

    public static function ownedMedia(string $draftId, string $mediaUuid, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT m.* FROM post_draft_media m
             JOIN post_drafts d ON d.id = m.draft_id
             WHERE m.draft_id = ? AND m.media_uuid = ? AND d.user_id = ? LIMIT 1'
        );
        $stmt->execute([$draftId, $mediaUuid, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function updateMediaEditorState(
        string $draftId,
        string $mediaUuid,
        int $userId,
        array $state,
        int $sortOrder,
        int $expectedRevision
    ): ?int {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo = self::db();
        self::beginRevisionMutation($draftId, $userId, $expectedRevision);
        try {
            $exists = $pdo->prepare(
                'SELECT id FROM post_draft_media WHERE draft_id = ? AND media_uuid = ? LIMIT 1'
            );
            $exists->execute([$draftId, $mediaUuid]);
            if (!$exists->fetchColumn()) {
                self::rollbackRevisionMutation();
                return null;
            }
            $pdo->prepare(
                'UPDATE post_draft_media SET editor_state = ?, sort_order = ?
                 WHERE draft_id = ? AND media_uuid = ?'
            )->execute([$json, $sortOrder, $draftId, $mediaUuid]);

            return self::finishRevisionMutation($draftId, $userId);
        } catch (\Throwable $e) {
            self::rollbackRevisionMutation();
            throw $e;
        }
    }

    public static function deleteMedia(
        string $draftId,
        string $mediaUuid,
        int $userId,
        int $expectedRevision
    ): ?int {
        $pdo = self::db();
        self::beginRevisionMutation($draftId, $userId, $expectedRevision);
        $media = null;
        try {
            $stmt = $pdo->prepare(
                'SELECT storage_path, rendered_path FROM post_draft_media
                 WHERE draft_id = ? AND media_uuid = ? LIMIT 1'
            );
            $stmt->execute([$draftId, $mediaUuid]);
            $media = $stmt->fetch();
            if (!$media) {
                self::rollbackRevisionMutation();
                return null;
            }

            $pdo->prepare(
                'DELETE FROM post_draft_media WHERE draft_id = ? AND media_uuid = ?'
            )->execute([$draftId, $mediaUuid]);
            $nextRevision = self::finishRevisionMutation($draftId, $userId);

            self::removePrivateFile((string) $media['storage_path']);
            if (!empty($media['rendered_path'])) self::removePrivateFile((string) $media['rendered_path']);
            return $nextRevision;
        } catch (\Throwable $e) {
            self::rollbackRevisionMutation();
            if (is_array($media) && self::cleanupDeletedMediaIfUnreferenced($draftId, $mediaUuid, $userId, $media)) {
                // A lost COMMIT acknowledgement must not make the UI retry a
                // deletion that has already removed the media row.
                return $expectedRevision + 1;
            }
            throw $e;
        }
    }

    /** Internal rollback for a partially completed upload request. */
    public static function deleteMediaForRollback(string $draftId, string $mediaUuid, int $userId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        $media = null;
        try {
            $draft = $pdo->prepare('SELECT id FROM post_drafts WHERE id = ? AND user_id = ? FOR UPDATE');
            $draft->execute([$draftId, $userId]);
            if (!$draft->fetchColumn()) {
                $pdo->rollBack();
                return;
            }
            $stmt = $pdo->prepare(
                'SELECT storage_path, rendered_path FROM post_draft_media
                 WHERE draft_id = ? AND media_uuid = ? LIMIT 1'
            );
            $stmt->execute([$draftId, $mediaUuid]);
            $media = $stmt->fetch();
            if (!$media) {
                $pdo->rollBack();
                return;
            }
            $pdo->prepare('DELETE FROM post_draft_media WHERE draft_id = ? AND media_uuid = ?')
                ->execute([$draftId, $mediaUuid]);
            $pdo->prepare(
                'UPDATE post_drafts SET revision = revision + 1, last_synced_at = UTC_TIMESTAMP(),
                    updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?'
            )->execute([$draftId, $userId]);
            $pdo->commit();

            self::removePrivateFile((string) $media['storage_path']);
            if (!empty($media['rendered_path'])) self::removePrivateFile((string) $media['rendered_path']);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) self::rollbackRevisionMutation();
            if (is_array($media)
                && self::cleanupDeletedMediaIfUnreferenced($draftId, $mediaUuid, $userId, $media)) {
                return;
            }
            throw $e;
        }
    }

    private static function cleanupDeletedMediaIfUnreferenced(
        string $draftId,
        string $mediaUuid,
        int $userId,
        array $media
    ): bool {
        $paths = [
            'storage_path' => (string) ($media['storage_path'] ?? ''),
            'rendered_path' => (string) ($media['rendered_path'] ?? ''),
        ];
        $checked = false;
        foreach ($paths as $column => $relativePath) {
            if ($relativePath === '') continue;
            $checked = true;
            if (self::cleanupUploadIfUnreferenced($draftId, $mediaUuid, $userId, $relativePath, $column) !== 'removed') {
                return false;
            }
        }
        return $checked;
    }

    private static function beginRevisionMutation(string $draftId, int $userId, int $expectedRevision): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT revision FROM post_drafts WHERE id = ? AND user_id = ? FOR UPDATE'
            );
            $stmt->execute([$draftId, $userId]);
            $revision = $stmt->fetchColumn();
            if ($revision === false) {
                $pdo->rollBack();
                throw new \DomainException('draft_not_found');
            }
            if ((int) $revision !== $expectedRevision) {
                $pdo->rollBack();
                throw new \DomainException('draft_revision_conflict');
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function finishRevisionMutation(string $draftId, int $userId): int
    {
        $pdo = self::db();
        $pdo->prepare(
            'UPDATE post_drafts SET revision = revision + 1, last_synced_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?'
        )->execute([$draftId, $userId]);
        $stmt = $pdo->prepare('SELECT revision FROM post_drafts WHERE id = ? AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        $revision = (int) $stmt->fetchColumn();
        $pdo->commit();
        return $revision;
    }

    private static function rollbackRevisionMutation(): bool
    {
        $pdo = self::db();
        if (!$pdo->inTransaction()) return true;
        try {
            $pdo->rollBack();
            return true;
        } catch (\Throwable $error) {
            App::logException('CRITICAL', 'draft.revision_rollback_failed', $error);
            return false;
        }
    }

    public static function currentRevision(string $draftId, int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT revision FROM post_drafts WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$draftId, $userId]);
        $revision = $stmt->fetchColumn();
        return $revision === false ? 0 : (int) $revision;
    }

    public static function deleteForUser(string $id, int $userId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare(
            'SELECT m.storage_path, m.rendered_path
             FROM post_draft_media m
             JOIN post_drafts d ON d.id = m.draft_id
             WHERE d.id = ? AND d.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $paths = $stmt->fetchAll();

        try {
            $delete = $pdo->prepare('DELETE FROM post_drafts WHERE id = ? AND user_id = ?');
            $delete->execute([$id, $userId]);
            if ($delete->rowCount() !== 1) return false;
        } catch (\Throwable $error) {
            // A single-statement autocommit can lose its acknowledgement after
            // the row was deleted. Confirm through an independent connection
            // before touching any file.
            try {
                $check = Database::newConnection()->prepare(
                    'SELECT id FROM post_drafts WHERE id = ? AND user_id = ? LIMIT 1'
                );
                $check->execute([$id, $userId]);
                $draftStillExists = $check->fetchColumn() !== false;
                if (!$draftStillExists) {
                    foreach ($paths as $item) {
                        self::removePrivateFile((string) $item['storage_path']);
                        if (!empty($item['rendered_path'])) self::removePrivateFile((string) $item['rendered_path']);
                    }
                    self::removePrivateDirectory($id);
                    return true;
                }
            } catch (\Throwable $inspectionError) {
                App::logException('CRITICAL', 'draft.delete_outcome_inspection_failed', $inspectionError, [
                    'draft_id' => $id,
                    'user_id' => $userId,
                ]);
            }

            foreach ($paths as $item) {
                self::recordStorageRecoveryTask((string) $item['storage_path'], 'draft_delete_outcome_unknown');
                if (!empty($item['rendered_path'])) {
                    self::recordStorageRecoveryTask((string) $item['rendered_path'], 'draft_delete_outcome_unknown');
                }
            }
            App::logException('ERROR', 'draft.delete_outcome_unknown', $error, [
                'draft_id' => $id,
                'user_id' => $userId,
            ]);
            throw $error;
        }

        foreach ($paths as $item) {
            self::removePrivateFile((string) $item['storage_path']);
            if (!empty($item['rendered_path'])) self::removePrivateFile((string) $item['rendered_path']);
        }
        self::removePrivateDirectory($id);
        return true;
    }

    public static function cleanupPrivateMedia(string $relative): void
    {
        self::removePrivateFile($relative);
    }

    /**
     * Remove a just-uploaded draft file only after proving that the database
     * does not reference it. Returns referenced, removed, or unknown.
     */
    public static function cleanupUploadIfUnreferenced(
        string $draftId,
        string $mediaUuid,
        int $userId,
        string $relative,
        string $column
    ): string {
        if (!in_array($column, ['storage_path', 'rendered_path'], true)) {
            throw new \InvalidArgumentException('Invalid draft media path column.');
        }
        try {
            $pdo = self::db();
            if ($pdo->inTransaction()) {
                self::recordStorageRecoveryTask($relative, 'draft_media_commit_outcome_unknown');
                return 'unknown';
            }

            $stmt = $pdo->prepare(
                "SELECT m.{$column} FROM post_draft_media m
                 JOIN post_drafts d ON d.id = m.draft_id
                 WHERE m.draft_id = ? AND m.media_uuid = ? AND d.user_id = ? LIMIT 1"
            );
            $stmt->execute([$draftId, $mediaUuid, $userId]);
            $storedPath = $stmt->fetchColumn();
            if (is_string($storedPath) && hash_equals($storedPath, $relative)) {
                return 'referenced';
            }

            return self::removePrivateFile($relative) ? 'removed' : 'unknown';
        } catch (\Throwable $error) {
            self::recordStorageRecoveryTask($relative, 'draft_media_commit_outcome_unknown');
            App::logException('ERROR', 'draft.media_upload_outcome_unknown', $error, [
                'draft_id' => $draftId,
                'media_uuid' => $mediaUuid,
                'user_id' => $userId,
                'path_column' => $column,
            ]);
            return 'unknown';
        }
    }

    private static function removePrivateFile(string $relative): bool
    {
        $normalized = str_replace('\\', '/', ltrim($relative, '/'));
        if (preg_match('#^drafts/([a-f0-9-]{36})/([a-z0-9._-]{1,120})$#i', $normalized, $match) !== 1) {
            App::log('WARNING', 'draft.media_cleanup_path_rejected', [
                'reason' => 'invalid_relative_path',
            ]);
            return false;
        }

        $root = realpath(App::root('storage/private/drafts'));
        if ($root === false) {
            App::log('WARNING', 'draft.media_cleanup_root_unavailable', [
                'draft_id' => strtolower($match[1]),
            ]);
            return false;
        }
        $draftDirectory = realpath($root . DIRECTORY_SEPARATOR . strtolower($match[1]));
        if ($draftDirectory === false) {
            self::completeStorageRecoveryTask($normalized);
            return true;
        }
        if (!str_starts_with($draftDirectory, $root . DIRECTORY_SEPARATOR) || !is_dir($draftDirectory)) {
            App::log('WARNING', 'draft.media_cleanup_directory_rejected', [
                'draft_id' => strtolower($match[1]),
            ]);
            return false;
        }

        $file = $draftDirectory . DIRECTORY_SEPARATOR . $match[2];
        if (!is_file($file) && !is_link($file)) {
            self::completeStorageRecoveryTask($normalized);
            return true;
        }
        if (!@unlink($file)) {
            self::recordStorageRecoveryTask($normalized, 'draft_media_cleanup_failed');
            return false;
        }
        self::completeStorageRecoveryTask($normalized);
        return true;
    }

    private static function recordStorageRecoveryTask(string $relativePath, string $reason): void
    {
        try {
            Database::newConnection()->prepare(
                "INSERT INTO storage_recovery_tasks
                    (aggregate_type, aggregate_id, file_path, file_path_hash, reason_code,
                     status, attempts, last_attempt_at, last_error_code)
                 VALUES ('draft', NULL, ?, ?, ?, 'pending', 1, UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE
                    reason_code = VALUES(reason_code),
                    status = 'pending',
                    attempts = attempts + 1,
                    last_attempt_at = UTC_TIMESTAMP(),
                    last_error_code = VALUES(last_error_code),
                    completed_at = NULL"
            )->execute([
                $relativePath,
                hash('sha256', $relativePath),
                $reason,
                (str_contains($reason, 'outcome_unknown') || str_contains($reason, 'commit')) ? 'commit_unknown' : 'unlink_failed',
            ]);
        } catch (\Throwable $error) {
            App::logException('CRITICAL', 'draft.media_recovery_task_write_failed', $error, [
                'reason_code' => $reason,
            ]);
        }
    }

    private static function completeStorageRecoveryTask(string $relativePath): void
    {
        try {
            self::db()->prepare(
                "UPDATE storage_recovery_tasks
                 SET status = 'completed', completed_at = UTC_TIMESTAMP(),
                     last_attempt_at = UTC_TIMESTAMP(), last_error_code = NULL
                 WHERE file_path_hash = ? AND status <> 'completed'"
            )->execute([hash('sha256', $relativePath)]);
        } catch (\Throwable $error) {
            App::logException('WARNING', 'draft.media_recovery_completion_failed', $error, [
                'file_path_hash' => hash('sha256', $relativePath),
            ]);
        }
    }

    private static function removePrivateDirectory(string $id): void
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) return;
        $root = realpath(App::root('storage/private/drafts'));
        if ($root === false) return;
        $dir = realpath($root . DIRECTORY_SEPARATOR . $id);
        if ($dir === false) return;
        if (!str_starts_with($dir, $root . DIRECTORY_SEPARATOR) || !is_dir($dir)) return;

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) || is_link($path)) {
                self::removePrivateFile('drafts/' . strtolower($id) . '/' . $entry);
            } else {
                App::log('WARNING', 'draft.media_cleanup_unexpected_entry', [
                    'draft_id' => strtolower($id),
                ]);
            }
        }

        $remaining = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        if ($remaining === []) {
            if (!@rmdir($dir)) {
                App::log('WARNING', 'draft.media_directory_remove_failed', [
                    'draft_id' => strtolower($id),
                ]);
            }
            return;
        }
        App::log('WARNING', 'draft.media_directory_not_empty', [
            'draft_id' => strtolower($id),
            'remaining_entries' => count($remaining),
        ]);
    }

}
