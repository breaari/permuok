<?php

namespace App\Services;

use Exception;
use PDO;

class MembershipGuard
{
    private const ROLE_REAL_ESTATE = 2;
    private const MEMBERSHIP_STATUS_ACTIVE = 1;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function requireActiveMembership(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                u.id AS user_id,
                u.role,
                u.real_estate_id,
                m.id AS membership_id,
                m.plan_id,
                m.end_date,
                m.max_users,
                m.max_agents,
                m.max_investors,
                m.can_publish_projects,
                m.can_view_projects
            FROM users u
            INNER JOIN memberships m
                ON m.real_estate_id = u.real_estate_id
               AND m.status = :active_status
               AND m.deleted_at IS NULL
               AND m.end_date >= CURDATE()
            WHERE u.id = :user_id
              AND u.role = :real_estate_role
              AND u.real_estate_id IS NOT NULL
              AND u.deleted_at IS NULL
            ORDER BY m.end_date DESC, m.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':active_status' => self::MEMBERSHIP_STATUS_ACTIVE,
            ':real_estate_role' => self::ROLE_REAL_ESTATE,
        ]);

        $membership = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$membership) {
            throw new Exception(
                'Tu membresía no está activa. Regularizá tu plan para continuar usando esta función.',
                402
            );
        }

        return $membership;
    }

    public static function hasActiveMembership(int $userId): bool
    {
        try {
            self::requireActiveMembership($userId);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}