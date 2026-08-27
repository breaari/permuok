<?php

namespace App\Services;

use PDO;
use Exception;
use Throwable;

class EmailJobService
{
    private const STALE_LOCK_MINUTES = 10;

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../db.php';

        return pdo($forceReconnect);
    }

    public static function enqueue(
        string $emailTo,
        string $emailType,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?int $userId = null,
        ?string $recipientName = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        int $priority = 5,
        ?PDO $pdo = null
    ): array {
        $emailTo = trim($emailTo);
        $emailType = trim($emailType);
        $subject = trim($subject);

        if (
            $emailTo === '' ||
            !filter_var(
                $emailTo,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new Exception(
                'El email destinatario no es válido.'
            );
        }

        if ($emailType === '') {
            throw new Exception(
                'El tipo de email es obligatorio.'
            );
        }

        if ($subject === '') {
            throw new Exception(
                'El asunto del email es obligatorio.'
            );
        }

        if (trim($htmlBody) === '') {
            throw new Exception(
                'El contenido del email es obligatorio.'
            );
        }

        $priority = max(
            1,
            min(10, $priority)
        );

        $pdo ??= self::db();

        $st = $pdo->prepare("
            INSERT INTO email_jobs (
                user_id,
                email_to,
                recipient_name,
                email_type,
                subject,
                html_body,
                text_body,
                related_type,
                related_id,
                status,
                priority,
                attempts,
                max_attempts,
                available_at
            ) VALUES (
                :user_id,
                :email_to,
                :recipient_name,
                :email_type,
                :subject,
                :html_body,
                :text_body,
                :related_type,
                :related_id,
                'pending',
                :priority,
                0,
                5,
                NOW()
            )
        ");

        $st->execute([
            'user_id' =>
            $userId && $userId > 0
                ? $userId
                : null,

            'email_to' =>
            $emailTo,

            'recipient_name' =>
            $recipientName,

            'email_type' =>
            $emailType,

            'subject' =>
            $subject,

            'html_body' =>
            $htmlBody,

            'text_body' =>
            $textBody,

            'related_type' =>
            $relatedType,

            'related_id' =>
            $relatedId && $relatedId > 0
                ? $relatedId
                : null,

            'priority' =>
            $priority,
        ]);

        $id = (int)$pdo->lastInsertId();

        $stFind = $pdo->prepare("
            SELECT *
            FROM email_jobs
            WHERE id = :id
            LIMIT 1
        ");

        $stFind->execute([
            'id' => $id,
        ]);

        $job = $stFind->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$job) {
            throw new Exception(
                'No se pudo recuperar el email encolado.'
            );
        }

        return $job;
    }

    public static function claimNext(
        string $workerId
    ): ?array {
        $workerId = trim($workerId);

        if ($workerId === '') {
            throw new Exception(
                'El worker debe tener un identificador.'
            );
        }

        self::recoverStaleJobs();

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                SELECT *
                FROM email_jobs

                WHERE status = 'pending'

                  AND available_at <= NOW()

                  AND attempts < max_attempts

                ORDER BY
                    priority DESC,
                    available_at ASC,
                    id ASC

                LIMIT 1

                FOR UPDATE
            ");

            $st->execute();

            $job = $st->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$job) {
                $pdo->commit();

                return null;
            }

            $jobId =
                (int)$job['id'];

            $stUpdate = $pdo->prepare("
                UPDATE email_jobs
                SET
                    status = 'processing',
                    attempts = attempts + 1,
                    locked_at = NOW(),
                    last_error = NULL
                WHERE id = :id
                LIMIT 1
            ");

            $stUpdate->execute([
                'id' => $jobId,
            ]);

            $pdo->commit();

            $job['status'] =
                'processing';

            $job['attempts'] =
                (int)$job['attempts'] + 1;

            return $job;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function complete(
        int $jobId
    ): void {
        if ($jobId <= 0) {
            return;
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            UPDATE email_jobs
            SET
                status = 'sent',
                sent_at = NOW(),
                locked_at = NULL,
                last_error = NULL
            WHERE id = :id
              AND status = 'processing'
            LIMIT 1
        ");

        $st->execute([
            'id' => $jobId,
        ]);
    }

    public static function fail(
        int $jobId,
        Throwable $error
    ): void {
        if ($jobId <= 0) {
            return;
        }

        $pdo = self::db();

        $stCurrent = $pdo->prepare("
            SELECT
                attempts,
                max_attempts
            FROM email_jobs
            WHERE id = :id
            LIMIT 1
        ");

        $stCurrent->execute([
            'id' => $jobId,
        ]);

        $current = $stCurrent->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$current) {
            return;
        }

        $attempts =
            (int)$current['attempts'];

        $maxAttempts =
            (int)$current['max_attempts'];

        $message = mb_substr(
            $error->getMessage(),
            0,
            6000
        );

        if ($attempts < $maxAttempts) {
            $delayMinutes = max(
                1,
                $attempts * $attempts
            );

            $st = $pdo->prepare("
                UPDATE email_jobs
                SET
                    status = 'pending',

                    available_at =
                        DATE_ADD(
                            NOW(),
                            INTERVAL :delay MINUTE
                        ),

                    locked_at = NULL,

                    last_error =
                        :last_error

                WHERE id = :id

                LIMIT 1
            ");

            $st->bindValue(
                ':delay',
                $delayMinutes,
                PDO::PARAM_INT
            );

            $st->bindValue(
                ':last_error',
                $message
            );

            $st->bindValue(
                ':id',
                $jobId,
                PDO::PARAM_INT
            );

            $st->execute();

            return;
        }

        $st = $pdo->prepare("
            UPDATE email_jobs
            SET
                status = 'failed',
                locked_at = NULL,
                last_error = :last_error
            WHERE id = :id
            LIMIT 1
        ");

        $st->execute([
            'last_error' =>
            $message,

            'id' =>
            $jobId,
        ]);
    }

    private static function recoverStaleJobs(): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            UPDATE email_jobs
            SET
                status = CASE
                    WHEN attempts >= max_attempts
                    THEN 'failed'
                    ELSE 'pending'
                END,

                available_at = CASE
                    WHEN attempts >= max_attempts
                    THEN available_at
                    ELSE NOW()
                END,

                locked_at = NULL,

                last_error = CASE
                    WHEN last_error IS NULL
                    THEN 'Trabajo recuperado después de un lock vencido.'
                    ELSE last_error
                END

            WHERE status = 'processing'

              AND locked_at IS NOT NULL

              AND locked_at <
                  DATE_SUB(
                      NOW(),
                      INTERVAL
                      " . self::STALE_LOCK_MINUTES . "
                      MINUTE
                  )
        ");

        $st->execute();
    }
}
