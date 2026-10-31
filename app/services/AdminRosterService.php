<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AdminRosterService
{
    public static function assertActorActive(PDO $pdo, int $adminId): void
    {
        $stmt = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$adminId]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$actor || $actor['role'] !== 'admin' || $actor['account_status'] !== 'active') {
            throw new \DomainException('admin_not_active');
        }
    }

    public static function assertCanChangeStatus(
        PDO $pdo,
        string $targetRole,
        string $targetStatus,
        string $newStatus
    ): void {
        if ($targetRole !== 'admin' || $targetStatus !== 'active' || $newStatus === 'active') {
            return;
        }

        self::assertAnotherActiveAdminExists($pdo);
    }

    public static function assertCanChangeRole(
        PDO $pdo,
        string $targetRole,
        string $targetStatus,
        string $newRole
    ): void {
        if ($targetRole !== 'admin' || $targetStatus !== 'active' || $newRole === 'admin') {
            return;
        }

        self::assertAnotherActiveAdminExists($pdo);
    }

    private static function assertAnotherActiveAdminExists(PDO $pdo): void
    {
        $activeAdmins = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'"
        )->fetchColumn();

        if ($activeAdmins <= 1) {
            throw new \DomainException('last_admin');
        }
    }
}
