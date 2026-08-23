<?php

namespace App\Services;

use PDO;
use Exception;
use Throwable;

class CompatibilityJobService
{
    private const TYPE_PROPERTY_RECALCULATE =
    'property_recalculate';

    private const TYPE_SEARCH_RECALCULATE =
    'search_request_recalculate';

    private const TYPE_PROPERTY_ARCHIVE =
    'property_archive';

    private const TYPE_SEARCH_ARCHIVE =
    'search_request_archive';

    private const TYPE_PROPERTY_QUALITY_RECALCULATE =
    'property_quality_recalculate';

    private const TYPE_PROPERTY_AI_ANALYZE =
    'property_ai_analyze';

    private const TYPE_SEARCH_REQUEST_AI_ANALYZE =
    'search_request_ai_analyze';

    private const TYPE_DEVELOPMENT_AI_ANALYZE =
    'development_ai_analyze';

    private const STALE_LOCK_MINUTES = 10;

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../db.php';

        return pdo($forceReconnect);
    }

    public static function enqueueSearchRequestAIAnalysis(
        int $searchRequestId,
        int $analysisId,
        int $priority = 7
    ): array {
        return self::enqueue(
            self::TYPE_SEARCH_REQUEST_AI_ANALYZE,
            $searchRequestId,
            $priority,
            $analysisId
        );
    }

    public static function enqueueDevelopmentAIAnalysis(
        int $developmentId,
        int $analysisId,
        int $priority = 7
    ): array {
        return self::enqueue(
            self::TYPE_DEVELOPMENT_AI_ANALYZE,
            $developmentId,
            $priority,
            $analysisId
        );
    }

    public static function enqueuePropertyQualityRecalculation(
        int $propertyId,
        int $priority = 4
    ): array {
        return self::enqueue(
            self::TYPE_PROPERTY_QUALITY_RECALCULATE,
            $propertyId,
            $priority
        );
    }

    public static function enqueuePropertyAIAnalysis(
        int $propertyId,
        int $analysisId,
        int $priority = 7
    ): array {
        return self::enqueue(
            self::TYPE_PROPERTY_AI_ANALYZE,
            $propertyId,
            $priority,
            $analysisId
        );
    }

    public static function enqueuePropertyRecalculation(
        int $propertyId,
        int $priority = 5
    ): array {
        return self::enqueue(
            self::TYPE_PROPERTY_RECALCULATE,
            $propertyId,
            $priority
        );
    }

    public static function enqueueSearchRequestRecalculation(
        int $searchRequestId,
        int $priority = 5
    ): array {
        return self::enqueue(
            self::TYPE_SEARCH_RECALCULATE,
            $searchRequestId,
            $priority
        );
    }

    public static function enqueuePropertyArchive(
        int $propertyId,
        int $priority = 8
    ): array {
        return self::enqueue(
            self::TYPE_PROPERTY_ARCHIVE,
            $propertyId,
            $priority
        );
    }

    public static function enqueueSearchRequestArchive(
        int $searchRequestId,
        int $priority = 8
    ): array {
        return self::enqueue(
            self::TYPE_SEARCH_ARCHIVE,
            $searchRequestId,
            $priority
        );
    }

    private static function enqueue(
        string $jobType,
        int $entityId,
        int $priority = 5,
        ?int $referenceId = null
    ): array {
        if ($entityId <= 0) {
            throw new Exception(
                'La entidad del trabajo no es válida.'
            );
        }

        $validTypes = [
            self::TYPE_PROPERTY_RECALCULATE,
            self::TYPE_SEARCH_RECALCULATE,
            self::TYPE_PROPERTY_ARCHIVE,
            self::TYPE_SEARCH_ARCHIVE,
            self::TYPE_PROPERTY_QUALITY_RECALCULATE,
            self::TYPE_PROPERTY_AI_ANALYZE,
            self::TYPE_SEARCH_REQUEST_AI_ANALYZE,
            self::TYPE_DEVELOPMENT_AI_ANALYZE,
        ];

        if (!in_array($jobType, $validTypes, true)) {
            throw new Exception(
                'Tipo de trabajo de compatibilidad inválido.'
            );
        }

        $priority = max(
            1,
            min(10, $priority)
        );

        $pdo = self::db();

        if (
            in_array(
                $jobType,
                [
                    self::TYPE_PROPERTY_AI_ANALYZE,
                    self::TYPE_SEARCH_REQUEST_AI_ANALYZE,
                    self::TYPE_DEVELOPMENT_AI_ANALYZE,
                ],
                true
            ) &&
            $referenceId !== null
        ) {
            $activeKey =
                $jobType .
                ':' .
                $entityId .
                ':' .
                $referenceId;
        } else {
            $activeKey =
                $jobType .
                ':' .
                $entityId;
        }

        /*
         * Gracias al UNIQUE(active_key):
         *
         * - si no existe, insertamos;
         * - si ya está pendiente/procesando,
         *   no creamos otro.
         *
         * También adelantamos available_at para
         * que una nueva modificación no deje el
         * trabajo esperando un retry viejo.
         */
        $st = $pdo->prepare("
            INSERT INTO compatibility_jobs (
                job_type,
                entity_id,
                reference_id,
                status,
                priority,
                attempts,
                max_attempts,
                available_at,
                active_key
            ) VALUES (
                :job_type,
                :entity_id,
                :reference_id,
                'pending',
                :priority,
                0,
                3,
                NOW(),
                :active_key
            )

            ON DUPLICATE KEY UPDATE
                priority = GREATEST(
                    priority,
                    VALUES(priority)
                ),

                available_at = CASE
                    WHEN status = 'pending'
                    THEN LEAST(
                        available_at,
                        NOW()
                    )
                    ELSE available_at
                END,

                updated_at = CURRENT_TIMESTAMP
        ");

        $st->execute([
            'job_type' =>
            $jobType,

            'entity_id' =>
            $entityId,

            'reference_id' =>
            $referenceId,

            'priority' =>
            $priority,

            'active_key' =>
            $activeKey,
        ]);

        $stFind = $pdo->prepare("
            SELECT *
            FROM compatibility_jobs
            WHERE active_key = :active_key
            LIMIT 1
        ");

        $stFind->execute([
            'active_key' => $activeKey,
        ]);

        $job = $stFind->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$job) {
            throw new Exception(
                'No se pudo recuperar el trabajo encolado.'
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

        /*
         * Primero recuperamos trabajos que pudieron
         * quedar processing porque un worker murió.
         */
        self::recoverStaleJobs();

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            /*
             * FOR UPDATE garantiza que dos workers
             * no puedan quedarse con el mismo job.
             *
             * La transacción dura apenas unas consultas:
             * el procesamiento pesado ocurre después
             * del commit.
             */
            $st = $pdo->prepare("
                SELECT *
                FROM compatibility_jobs

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
                UPDATE compatibility_jobs
                SET
                    status = 'processing',
                    attempts = attempts + 1,
                    started_at = NOW(),
                    locked_at = NOW(),
                    locked_by = :locked_by,
                    error_message = NULL
                WHERE id = :id
                LIMIT 1
            ");

            $stUpdate->execute([
                'locked_by' =>
                $workerId,

                'id' =>
                $jobId,
            ]);

            $pdo->commit();

            $job['status'] =
                'processing';

            $job['attempts'] =
                (int)$job['attempts'] + 1;

            $job['locked_by'] =
                $workerId;

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
            UPDATE compatibility_jobs
            SET
                status = 'completed',
                completed_at = NOW(),

                locked_at = NULL,
                locked_by = NULL,

                /*
                 * Liberamos la clave para que en el
                 * futuro pueda encolarse nuevamente.
                 */
                active_key = NULL,

                error_message = NULL

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

            FROM compatibility_jobs

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

        /*
         * Si todavía quedan intentos:
         * vuelve a pending.
         *
         * Backoff:
         * intento 1 → 1 minuto
         * intento 2 → 4 minutos
         * intento 3 → ya queda failed.
         */
        if ($attempts < $maxAttempts) {
            $delayMinutes =
                max(
                    1,
                    $attempts * $attempts
                );

            $st = $pdo->prepare("
                UPDATE compatibility_jobs
                SET
                    status = 'pending',

                    available_at =
                        DATE_ADD(
                            NOW(),
                            INTERVAL :delay MINUTE
                        ),

                    locked_at = NULL,
                    locked_by = NULL,

                    error_message = :error_message

                WHERE id = :id

                LIMIT 1
            ");

            $st->bindValue(
                ':delay',
                $delayMinutes,
                PDO::PARAM_INT
            );

            $st->bindValue(
                ':error_message',
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

        /*
         * Sin más retries:
         * queda failed y liberamos active_key.
         *
         * Así una edición futura puede generar
         * un nuevo job.
         */
        $st = $pdo->prepare("
            UPDATE compatibility_jobs
            SET
                status = 'failed',

                completed_at = NOW(),

                locked_at = NULL,
                locked_by = NULL,

                active_key = NULL,

                error_message = :error_message

            WHERE id = :id

            LIMIT 1
        ");

        $st->execute([
            'error_message' =>
            $message,

            'id' =>
            $jobId,
        ]);
    }

    public static function recoverStaleJobs(): int
    {
        $pdo = self::db();

        $minutes =
            self::STALE_LOCK_MINUTES;

        $st = $pdo->prepare("
            UPDATE compatibility_jobs
            SET
                status = 'pending',

                available_at = NOW(),

                locked_at = NULL,
                locked_by = NULL,

                error_message =
                    CONCAT(
                        COALESCE(
                            error_message,
                            ''
                        ),
                        CASE
                            WHEN error_message IS NULL
                              OR error_message = ''
                            THEN ''
                            ELSE '\\n'
                        END,
                        'Worker timeout: job recuperado automáticamente'
                    )

            WHERE status = 'processing'

              AND locked_at IS NOT NULL

              AND locked_at <
                  DATE_SUB(
                      NOW(),
                      INTERVAL {$minutes} MINUTE
                  )
        ");

        $st->execute();

        return $st->rowCount();
    }

    public static function getStats(): array
    {
        $pdo = self::db();

        $st = $pdo->query("
            SELECT
                status,
                COUNT(*) AS total
            FROM compatibility_jobs
            GROUP BY status
        ");

        $rows =
            $st->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $result = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $status =
                (string)$row['status'];

            if (
                array_key_exists(
                    $status,
                    $result
                )
            ) {
                $result[$status] =
                    (int)$row['total'];
            }
        }

        return $result;
    }
}
