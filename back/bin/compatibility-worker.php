<?php

declare(strict_types=1);

use App\Services\CompatibilityJobProcessor;
use App\Services\CompatibilityJobService;
use App\Services\HighMatchNotificationService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

/*
|--------------------------------------------------------------------------
| Compatibility Worker
|--------------------------------------------------------------------------
|
| Consume trabajos pendientes de compatibility_jobs.
|
| Flujo:
|
| pending
|   ↓
| claimNext()
|   ↓
| processing
|   ↓
| CompatibilityJobProcessor::process()
|   ↓
| completed
|
| Si ocurre un error:
|
| processing
|   ↓
| fail()
|   ↓
| pending para retry
|   o
| failed si agotó max_attempts
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

/*
 * Identificador único del worker.
 *
 * Ejemplo:
 * ARIANA-PC:15432
 */
$hostname = gethostname();

if ($hostname === false || trim($hostname) === '') {
    $hostname = 'unknown-host';
}

$workerId =
    $hostname .
    ':' .
    getmypid();

/*
 * Worker permanente.
 *
 * Si la cola está vacía espera brevemente
 * y vuelve a consultar.
 */
$idleSleepMicroseconds = 750000;

$processed = 0;
$completed = 0;
$failed = 0;

echo "========================================\n";
echo " PermuOK Compatibility Worker\n";
echo "========================================\n";
echo "Worker: {$workerId}\n";
echo "Modo: permanente\n";
echo "----------------------------------------\n";

try {
    while (true) {
        /*
         * Busca y bloquea el próximo trabajo disponible.
         *
         * claimNext() también recupera automáticamente
         * trabajos processing cuyo worker haya muerto.
         */
        $job =
            CompatibilityJobService::claimNext(
                $workerId
            );

        /*
         * No quedan trabajos disponibles.
         */
        if ($job === null) {
            usleep($idleSleepMicroseconds);
            continue;
        }

        $jobId =
            (int)$job['id'];

        $jobType =
            (string)$job['job_type'];

        $entityId =
            (int)$job['entity_id'];

        $attempt =
            (int)$job['attempts'];

        $processed++;

        echo "\n";
        echo "[JOB {$jobId}] processing\n";
        echo "  Tipo: {$jobType}\n";
        echo "  Entidad: {$entityId}\n";
        echo "  Intento: {$attempt}\n";

        try {
            $result =
                CompatibilityJobProcessor::process(
                    $job
                );

            CompatibilityJobService::complete(
                $jobId
            );

            $highMatchResult =
                HighMatchNotificationService::process();

            if (
                ($highMatchResult['notifications_created'] ?? 0) > 0
            ) {
                echo
                "  Alertas match alto: " .
                    $highMatchResult['notifications_created'] .
                    "\n";

                echo
                "  Emails encolados: " .
                    $highMatchResult['emails_queued'] .
                    "\n";
            }

            if (
                !empty($result['skipped'])
            ) {
                echo "[JOB {$jobId}] skipped\n";

                if (!empty($result['reason'])) {
                    echo "  {$result['reason']}\n";
                }

                continue;
            }
            $completed++;
            echo "[JOB {$jobId}] completed\n";
        } catch (Throwable $e) {
            /*
             * CompatibilityJobService decide si:
             *
             * - vuelve a pending para retry
             * - queda definitivamente failed
             */
            CompatibilityJobService::fail(
                $jobId,
                $e
            );

            $failed++;

            echo "[JOB {$jobId}] error\n";
            echo "  {$e->getMessage()}\n";
        }
    }
} catch (Throwable $e) {
    /*
     * Error general del worker:
     * conexión DB, configuración, claimNext(), etc.
     */
    fwrite(
        STDERR,
        "\n[WORKER ERROR] " .
            $e->getMessage() .
            "\n"
    );

    exit(1);
}
