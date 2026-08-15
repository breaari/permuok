<?php

declare(strict_types=1);

use App\Services\CompatibilityJobProcessor;
use App\Services\CompatibilityJobService;

require_once __DIR__ . '/../vendor/autoload.php';

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
 * Cantidad máxima de jobs que procesará esta ejecución.
 *
 * Por ahora usamos 100 para evitar que una ejecución manual
 * quede corriendo indefinidamente.
 *
 * Más adelante, cuando lo instalemos como proceso permanente,
 * podemos convertirlo en un worker continuo.
 */
$maxJobs = 100;

$processed = 0;
$completed = 0;
$failed = 0;

echo "========================================\n";
echo " PermuOK Compatibility Worker\n";
echo "========================================\n";
echo "Worker: {$workerId}\n";
echo "Máximo de jobs: {$maxJobs}\n";
echo "----------------------------------------\n";

try {
    while ($processed < $maxJobs) {
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
            break;
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
            /*
             * Ejecuta el trabajo real:
             *
             * property_recalculate
             * search_request_recalculate
             * property_archive
             * search_request_archive
             */
            CompatibilityJobProcessor::process(
                $job
            );

            /*
             * Si CompatibilityEngine terminó correctamente,
             * marcamos el job como completed.
             */
            CompatibilityJobService::complete(
                $jobId
            );

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

echo "\n";
echo "----------------------------------------\n";
echo "Worker finalizado\n";
echo "Procesados: {$processed}\n";
echo "Completados: {$completed}\n";
echo "Con error: {$failed}\n";
echo "========================================\n";

exit(0);