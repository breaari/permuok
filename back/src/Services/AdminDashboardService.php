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

            'ai' => [
                'cost_today_usd' =>
                self::aiCostToday($pdo),

                'cost_month_usd' =>
                self::aiCostMonth($pdo),

                'cost_total_usd' =>
                self::aiCostTotal($pdo),

                'calls_month' =>
                self::aiCallsMonth($pdo),

                'tokens_month' =>
                self::aiTokensMonth($pdo),

                'failed_calls_month' =>
                self::aiFailedCallsMonth($pdo),
            ],
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

    private static function aiCostToday(
        PDO $pdo
    ): float {
        $value = $pdo->query("
        SELECT COALESCE(
            SUM(estimated_cost_usd),
            0
        )
        FROM ai_usage_logs
        WHERE status = 'success'
          AND created_at >= UTC_DATE()
          AND created_at < UTC_DATE() + INTERVAL 1 DAY
    ")->fetchColumn();

        return round(
            (float)$value,
            8
        );
    }

    private static function aiCostMonth(
        PDO $pdo
    ): float {
        $value = $pdo->query("
        SELECT COALESCE(
            SUM(estimated_cost_usd),
            0
        )
        FROM ai_usage_logs
        WHERE status = 'success'
          AND created_at >=
              DATE_FORMAT(
                  UTC_DATE(),
                  '%Y-%m-01'
              )
          AND created_at <
              DATE_FORMAT(
                  UTC_DATE() + INTERVAL 1 MONTH,
                  '%Y-%m-01'
              )
    ")->fetchColumn();

        return round(
            (float)$value,
            8
        );
    }

    private static function aiCostTotal(
        PDO $pdo
    ): float {
        $value = $pdo->query("
        SELECT COALESCE(
            SUM(estimated_cost_usd),
            0
        )
        FROM ai_usage_logs
        WHERE status = 'success'
    ")->fetchColumn();

        return round(
            (float)$value,
            8
        );
    }

    private static function aiCallsMonth(
        PDO $pdo
    ): int {
        return self::count($pdo, "
        SELECT COUNT(*)
        FROM ai_usage_logs
        WHERE created_at >=
            DATE_FORMAT(
                UTC_DATE(),
                '%Y-%m-01'
            )
          AND created_at <
            DATE_FORMAT(
                UTC_DATE() + INTERVAL 1 MONTH,
                '%Y-%m-01'
            )
    ");
    }

    private static function aiTokensMonth(
        PDO $pdo
    ): int {
        $value = $pdo->query("
        SELECT COALESCE(
            SUM(total_tokens),
            0
        )
        FROM ai_usage_logs
        WHERE status = 'success'
          AND created_at >=
              DATE_FORMAT(
                  UTC_DATE(),
                  '%Y-%m-01'
              )
          AND created_at <
              DATE_FORMAT(
                  UTC_DATE() + INTERVAL 1 MONTH,
                  '%Y-%m-01'
              )
    ")->fetchColumn();

        return (int)$value;
    }

    private static function aiFailedCallsMonth(
        PDO $pdo
    ): int {
        return self::count($pdo, "
        SELECT COUNT(*)
        FROM ai_usage_logs
        WHERE status = 'failed'
          AND created_at >=
              DATE_FORMAT(
                  UTC_DATE(),
                  '%Y-%m-01'
              )
          AND created_at <
              DATE_FORMAT(
                  UTC_DATE() + INTERVAL 1 MONTH,
                  '%Y-%m-01'
              )
    ");
    }
}
