<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\AdminRosterService;

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
        if ($manufacturerId < 1 || $query === '' || mb_strlen($query) > 80) return [];
        $limit = max(1, min(25, $limit));
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
        $like = '%' . $escaped . '%';
        $prefix = $escaped . '%';
        $sql = "SELECT dm.id, dm.manufacturer_id, dm.commercial_name, dm.model_code, dm.aliases
                FROM device_models dm
                INNER JOIN device_manufacturers m ON m.id = dm.manufacturer_id AND m.is_active = 1
                WHERE dm.manufacturer_id = ? AND dm.is_active = 1
                  AND (dm.commercial_name LIKE ? ESCAPE '!'
                       OR COALESCE(dm.model_code, '') LIKE ? ESCAPE '!'
                       OR COALESCE(dm.aliases, '') LIKE ? ESCAPE '!')
                ORDER BY
                  CASE WHEN UPPER(dm.model_code) = UPPER(?) THEN 0
                       WHEN UPPER(dm.commercial_name) = UPPER(?) THEN 1
                       WHEN dm.model_code LIKE ? ESCAPE '!' THEN 2 ELSE 3 END,
                  dm.commercial_name
                LIMIT " . $limit;
        $stmt = self::db()->prepare($sql);
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
        $commercialName = trim($commercialName);
        $modelCode = trim($modelCode);
        $aliases = trim($aliases);
        $note = trim($note);
        if (mb_strlen($commercialName) < 2 || mb_strlen($commercialName) > 140) {
            throw new \DomainException('invalid_model_name');
        }

        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $manufacturer = $pdo->prepare('SELECT id FROM device_manufacturers WHERE id = ? AND is_active = 1 FOR UPDATE');
            $manufacturer->execute([$manufacturerId]);
            if (!$manufacturer->fetchColumn()) throw new \DomainException('manufacturer_unavailable');

            $duplicateSql = "SELECT id FROM device_models
                             WHERE manufacturer_id = ? AND is_active = 1
                               AND ((? <> '' AND UPPER(model_code) = UPPER(?))
                                    OR LOWER(commercial_name) = LOWER(?))
                             LIMIT 1";
            $duplicate = $pdo->prepare($duplicateSql);
            $duplicate->execute([$manufacturerId, $modelCode, $modelCode, $commercialName]);
            if ($duplicate->fetchColumn()) throw new \DomainException('model_already_exists');

            $pending = $pdo->prepare(
                "SELECT id FROM device_model_suggestions
                 WHERE manufacturer_id = ? AND status = 'pending'
                   AND ((? <> '' AND UPPER(model_code) = UPPER(?))
                        OR LOWER(commercial_name) = LOWER(?))
                 LIMIT 1"
            );
            $pending->execute([$manufacturerId, $modelCode, $modelCode, $commercialName]);
            if ($pending->fetchColumn()) throw new \DomainException('suggestion_already_pending');

            $stmt = $pdo->prepare(
                'INSERT INTO device_model_suggestions
                    (manufacturer_id, submitted_by, commercial_name, model_code, aliases, note)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $manufacturerId, $userId, $commercialName,
                $modelCode !== '' ? $modelCode : null,
                $aliases !== '' ? $aliases : null,
                $note !== '' ? $note : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
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
            // Keep authorization at the model boundary as well as the controller.
            AdminRosterService::assertActorActive($pdo, $adminId);

            $stmt = $pdo->prepare('SELECT * FROM device_model_suggestions WHERE id = ? FOR UPDATE');
            $stmt->execute([$suggestionId]);
            $suggestion = $stmt->fetch();
            if (!$suggestion || $suggestion['status'] !== 'pending') {
                throw new \DomainException('suggestion_not_pending');
            }
            $manufacturer = $pdo->prepare(
                'SELECT id, is_active FROM device_manufacturers WHERE id = ? FOR UPDATE'
            );
            $manufacturer->execute([(int) $suggestion['manufacturer_id']]);
            $manufacturerRow = $manufacturer->fetch(\PDO::FETCH_ASSOC);
            if (!$manufacturerRow || ($approve && (int) $manufacturerRow['is_active'] !== 1)) {
                throw new \DomainException('manufacturer_unavailable');
            }

            $modelId = null;
            if ($approve) {
                $duplicate = $pdo->prepare(
                    "SELECT id FROM device_models
                     WHERE manufacturer_id = ? AND is_active = 1
                       AND ((? IS NOT NULL AND UPPER(model_code) = UPPER(?))
                            OR LOWER(commercial_name) = LOWER(?))
                     LIMIT 1"
                );
                $duplicate->execute([
                    (int) $suggestion['manufacturer_id'],
                    $suggestion['model_code'],
                    $suggestion['model_code'],
                    (string) $suggestion['commercial_name'],
                ]);
                if ($duplicate->fetchColumn()) throw new \DomainException('model_already_exists');

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
            $newStatus = $approve ? 'approved' : 'rejected';
            $pdo->prepare(
                'UPDATE device_model_suggestions SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(),
                    approved_model_id = ? WHERE id = ?'
            )->execute([$newStatus, $adminId, $modelId, $suggestionId]);

            $pdo->prepare(
                'INSERT INTO security_audit_logs (actor_user_id, action, details_json)
                 VALUES (?, ?, ?)'
            )->execute([
                $adminId,
                'device_model_suggestion.' . $newStatus,
                json_encode([
                    'suggestion_id' => $suggestionId,
                    'model_id' => $modelId,
                    'manufacturer_id' => (int) $suggestion['manufacturer_id'],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
