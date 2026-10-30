<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DeviceCatalog extends Model
{
    public static function manufacturers(): array
    {
        return self::db()->query(
            'SELECT id, slug, display_name, logo_path
             FROM device_manufacturers WHERE is_active = 1
             ORDER BY sort_order, display_name'
        )->fetchAll();
    }

    public static function searchModels(int $manufacturerId, string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($manufacturerId < 1 || mb_strlen($query) < 1) return [];
        $limit = max(1, min(25, $limit));
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $stmt = self::db()->prepare(
            'SELECT id, manufacturer_id, commercial_name, model_code, aliases
             FROM device_models
             WHERE manufacturer_id = ? AND is_active = 1
               AND (commercial_name LIKE ? ESCAPE \'\\\\\'
                    OR COALESCE(model_code, \'\') LIKE ? ESCAPE \'\\\\\'
                    OR COALESCE(aliases, \'\') LIKE ? ESCAPE \'\\\\\')
             ORDER BY
               CASE WHEN model_code = ? THEN 0
                    WHEN commercial_name = ? THEN 1
                    WHEN model_code LIKE ? ESCAPE \'\\\\\' THEN 2 ELSE 3 END,
               commercial_name
             LIMIT ' . $limit
        );
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $stmt->execute([$manufacturerId, $like, $like, $like, $query, $query, $prefix]);
        return $stmt->fetchAll();
    }

    public static function submitSuggestion(
        int $manufacturerId,
        int $userId,
        string $commercialName,
        string $modelCode,
        string $aliases,
        string $note
    ): int {
        $stmt = self::db()->prepare(
            'INSERT INTO device_model_suggestions
                (manufacturer_id, submitted_by, commercial_name, model_code, aliases, note)
             SELECT ?, ?, ?, ?, ?, ? FROM device_manufacturers
             WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([
            $manufacturerId, $userId, $commercialName,
            $modelCode !== '' ? $modelCode : null,
            $aliases !== '' ? $aliases : null,
            $note !== '' ? $note : null,
            $manufacturerId,
        ]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('manufacturer_unavailable');
        return (int) self::db()->lastInsertId();
    }

    public static function pendingSuggestions(): array
    {
        return self::db()->query(
            "SELECT s.*, m.display_name AS manufacturer_name, u.display_name AS submitter_name
             FROM device_model_suggestions s
             JOIN device_manufacturers m ON m.id = s.manufacturer_id
             JOIN users u ON u.id = s.submitted_by
             WHERE s.status = 'pending'
             ORDER BY s.created_at ASC, s.id ASC LIMIT 200"
        )->fetchAll();
    }

    public static function moderateSuggestion(int $suggestionId, int $adminId, bool $approve): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM device_model_suggestions WHERE id = ? FOR UPDATE');
            $stmt->execute([$suggestionId]);
            $suggestion = $stmt->fetch();
            if (!$suggestion || $suggestion['status'] !== 'pending') {
                throw new \DomainException('suggestion_not_pending');
            }
            $modelId = null;
            if ($approve) {
                $insert = $pdo->prepare(
                    'INSERT INTO device_models
                        (manufacturer_id, commercial_name, model_code, aliases, created_by, approved_by, approved_at)
                     VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
                );
                $insert->execute([
                    (int) $suggestion['manufacturer_id'],
                    (string) $suggestion['commercial_name'],
                    $suggestion['model_code'],
                    $suggestion['aliases'],
                    (int) $suggestion['submitted_by'],
                    $adminId,
                ]);
                $modelId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare(
                'UPDATE device_model_suggestions SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(),
                    approved_model_id = ? WHERE id = ?'
            )->execute([$approve ? 'approved' : 'rejected', $adminId, $modelId, $suggestionId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
