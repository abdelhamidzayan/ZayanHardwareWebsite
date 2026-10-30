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
                    m.sort_order, m.duration_ms, m.created_at
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
        int $sortOrder
    ): void {
        self::db()->prepare(
            'INSERT INTO post_draft_media
                (draft_id, media_uuid, media_kind, storage_path, mime_type, file_size, sha256, duration_ms, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$draftId, $mediaUuid, $kind, $path, $mime, $size, $sha256, $durationMs, $sortOrder]);
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

    public static function updateMediaEditorState(string $draftId, string $mediaUuid, int $userId, array $state, int $sortOrder): bool
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = self::db()->prepare(
            'UPDATE post_draft_media m
             JOIN post_drafts d ON d.id = m.draft_id
             SET m.editor_state = ?, m.sort_order = ?
             WHERE m.draft_id = ? AND m.media_uuid = ? AND d.user_id = ?'
        );
        $stmt->execute([$json, $sortOrder, $draftId, $mediaUuid, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteMedia(string $draftId, string $mediaUuid, int $userId): bool
    {
        $media = self::ownedMedia($draftId, $mediaUuid, $userId);
        if (!$media) return false;
        $stmt = self::db()->prepare(
            'DELETE m FROM post_draft_media m
             JOIN post_drafts d ON d.id = m.draft_id
             WHERE m.draft_id = ? AND m.media_uuid = ? AND d.user_id = ?'
        );
        $stmt->execute([$draftId, $mediaUuid, $userId]);
        if ($stmt->rowCount() === 1) self::removePrivateFile((string) $media['storage_path']);
        return $stmt->rowCount() === 1;
    }

    public static function deleteForUser(string $id, int $userId): bool
    {
        $stmt = self::db()->prepare('SELECT m.storage_path FROM post_draft_media m JOIN post_drafts d ON d.id = m.draft_id WHERE d.id = ? AND d.user_id = ?');
        $stmt->execute([$id, $userId]);
        $paths = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $delete = self::db()->prepare('DELETE FROM post_drafts WHERE id = ? AND user_id = ?');
        $delete->execute([$id, $userId]);
        if ($delete->rowCount() !== 1) return false;
        foreach ($paths as $path) self::removePrivateFile((string) $path);
        self::removePrivateDirectory($id);
        return true;
    }

    private static function removePrivateFile(string $relative): void
    {
        $root = realpath(App::root('storage/private'));
        if ($root === false || $relative === '' || str_contains($relative, '..')) return;
        $file = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/')));
        if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
    }

    private static function removePrivateDirectory(string $id): void
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) return;
        $root = realpath(App::root('storage/private/drafts'));
        if ($root === false) return;
        $dir = realpath($root . DIRECTORY_SEPARATOR . $id);
        if ($dir === false || !str_starts_with($dir, $root . DIRECTORY_SEPARATOR) || !is_dir($dir)) return;
        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) || is_link($path)) @unlink($path);
        }
        @rmdir($dir);
    }
}
