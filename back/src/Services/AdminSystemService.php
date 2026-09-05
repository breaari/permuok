<?php

namespace App\Services;

use PDO;

class AdminSystemService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function compatibilityJobs(
        string $status = 'failed',
        int $page = 1,
        int $limit = 20
    ): array {
        $allowedStatuses = [
            'pending',
            'processing',
            'completed',
            'failed',
        ];

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'failed';
        }

        $page =
            max(
                1,
                $page
            );

        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        $offset =
            ($page - 1) *
            $limit;

        $pdo =
            self::db();

        $stCount = $pdo->prepare("
            SELECT COUNT(*)
            FROM compatibility_jobs
            WHERE status = :status
        ");

        $stCount->execute([
            'status' =>
            $status,
        ]);

        $total =
            (int)$stCount->fetchColumn();

        $st = $pdo->prepare("
            SELECT
                id,
                job_type,
                entity_id,
                reference_id,
                status,
                priority,
                attempts,
                max_attempts,
                available_at,
                started_at,
                completed_at,
                locked_at,
                locked_by,
                error_message,
                rerun_requested,
                created_at,
                updated_at

            FROM compatibility_jobs

            WHERE status = :status

            ORDER BY
                updated_at DESC,
                id DESC

            LIMIT :limit
            OFFSET :offset
        ");

        $st->bindValue(
            ':status',
            $status
        );

        $st->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $st->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $st->execute();

        $rows =
            $st->fetchAll(
                PDO::FETCH_ASSOC
            );

        $items =
            array_map(
                static function (
                    array $row
                ): array {
                    return [
                        'id' =>
                        (int)$row['id'],

                        'job_type' =>
                        (string)$row['job_type'],

                        'entity_id' =>
                        (int)$row['entity_id'],

                        'reference_id' =>
                        $row['reference_id'] !== null
                            ? (int)$row['reference_id']
                            : null,

                        'status' =>
                        (string)$row['status'],

                        'priority' =>
                        (int)$row['priority'],

                        'attempts' =>
                        (int)$row['attempts'],

                        'max_attempts' =>
                        (int)$row['max_attempts'],

                        'available_at' =>
                        $row['available_at'],

                        'started_at' =>
                        $row['started_at'],

                        'completed_at' =>
                        $row['completed_at'],

                        'locked_at' =>
                        $row['locked_at'],

                        'locked_by' =>
                        $row['locked_by'],

                        'error_message' =>
                        $row['error_message'],

                        'rerun_requested' =>
                        (int)(
                            $row['rerun_requested']
                            ?? 0
                        ),

                        'created_at' =>
                        $row['created_at'],

                        'updated_at' =>
                        $row['updated_at'],
                    ];
                },
                $rows
            );

        return [
            'items' =>
            $items,

            'pagination' => [
                'page' =>
                $page,

                'limit' =>
                $limit,

                'total' =>
                $total,

                'total_pages' =>
                $total > 0
                    ? (int)ceil(
                        $total /
                            $limit
                    )
                    : 1,
            ],

            'filters' => [
                'status' =>
                $status,
            ],
        ];
    }

    public static function emailJobs(
        string $status = 'failed',
        int $page = 1,
        int $limit = 20
    ): array {
        $allowedStatuses = [
            'pending',
            'processing',
            'sent',
            'failed',
        ];

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'failed';
        }

        $page =
            max(
                1,
                $page
            );

        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        $offset =
            ($page - 1) *
            $limit;

        $pdo =
            self::db();

        $stCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM email_jobs
        WHERE status = :status
    ");

        $stCount->execute([
            'status' =>
            $status,
        ]);

        $total =
            (int)$stCount->fetchColumn();

        $st = $pdo->prepare("
        SELECT
            id,
            user_id,
            email_to,
            recipient_name,
            email_type,
            subject,
            related_type,
            related_id,
            status,
            priority,
            attempts,
            max_attempts,
            available_at,
            locked_at,
            last_error,
            sent_at,
            created_at,
            updated_at

        FROM email_jobs

        WHERE status = :status

        ORDER BY
            updated_at DESC,
            id DESC

        LIMIT :limit
        OFFSET :offset
    ");

        $st->bindValue(
            ':status',
            $status
        );

        $st->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $st->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $st->execute();

        $rows =
            $st->fetchAll(
                PDO::FETCH_ASSOC
            );

        $items =
            array_map(
                static function (
                    array $row
                ): array {
                    return [
                        'id' =>
                        (int)$row['id'],

                        'user_id' =>
                        $row['user_id'] !== null
                            ? (int)$row['user_id']
                            : null,

                        'email_to' =>
                        (string)$row['email_to'],

                        'recipient_name' =>
                        $row['recipient_name'],

                        'email_type' =>
                        (string)$row['email_type'],

                        'subject' =>
                        (string)$row['subject'],

                        'related_type' =>
                        $row['related_type'],

                        'related_id' =>
                        $row['related_id'] !== null
                            ? (int)$row['related_id']
                            : null,

                        'status' =>
                        (string)$row['status'],

                        'priority' =>
                        (int)$row['priority'],

                        'attempts' =>
                        (int)$row['attempts'],

                        'max_attempts' =>
                        (int)$row['max_attempts'],

                        'available_at' =>
                        $row['available_at'],

                        'locked_at' =>
                        $row['locked_at'],

                        'last_error' =>
                        $row['last_error'],

                        'sent_at' =>
                        $row['sent_at'],

                        'created_at' =>
                        $row['created_at'],

                        'updated_at' =>
                        $row['updated_at'],
                    ];
                },
                $rows
            );

        return [
            'items' =>
            $items,

            'pagination' => [
                'page' =>
                $page,

                'limit' =>
                $limit,

                'total' =>
                $total,

                'total_pages' =>
                $total > 0
                    ? (int)ceil(
                        $total /
                            $limit
                    )
                    : 1,
            ],

            'filters' => [
                'status' =>
                $status,
            ],
        ];
    }
}
