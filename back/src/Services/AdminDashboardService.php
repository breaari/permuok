<?php

namespace App\Services;

use PDO;

class AdminDashboardService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }
    public static function stats(): array
    {
        $pdo = self::db();

        return [
            'active_real_estates' => self::count($pdo, "
               SELECT COUNT(*)
FROM real_estates
WHERE deleted_at IS NULL
            "),

            'active_memberships' => self::count($pdo, "
                SELECT COUNT(*)
                FROM memberships
                WHERE status = 1
                  AND deleted_at IS NULL
            "),

            'active_publications' =>
            self::countPublications($pdo, 'published'),

            'paused_publications' =>
            self::countPublications($pdo, 'paused'),

            'active_conversations' => self::count($pdo, "
                SELECT COUNT(*)
                FROM conversations
                WHERE deleted_at IS NULL
            "),

            'pending_requests' => self::count($pdo, "
                SELECT COUNT(*)
                FROM real_estates
                WHERE status = 'pending'
                  AND deleted_at IS NULL
            "),
        ];
    }

    private static function count(PDO $pdo, string $sql): int
    {
        return (int)$pdo->query($sql)->fetchColumn();
    }

    private static function countPublications(PDO $pdo, string $status): int
    {
        $tables = [
            'properties',
            'search_requests',
            'developments',
        ];

        $total = 0;

        foreach ($tables as $table) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM {$table}
                WHERE status = :status
                  AND deleted_at IS NULL
            ");

            $stmt->execute([
                ':status' => $status,
            ]);

            $total += (int)$stmt->fetchColumn();
        }

        return $total;
    }
}
