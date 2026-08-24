<?php

declare(strict_types=1);

use App\Services\CompatibilityJobProcessor;
use App\Services\CompatibilityJobService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

/*
|--------------------------------------------------------------------------
| Worker para Cron
|--------------------------------------------------------------------------
|
| Pensado para hosting compartido.
|
| - procesa hasta 25 jobs por ejecución;
| - nunca permanece corriendo indefinidamente;
| - corta antes de los 50 segundos;
| - si la cola queda vacía, termina inmediatamente.
|
| El cron puede ejecutarlo cada minuto.
|
*/

$maxJobs = 25;
$maxRuntimeSeconds = 50;

$startedAt = microtime(true);

$hostname = gethostname();

if ($hostname === false || trim($hostname) === '') {
    $hostname = 'cron-host';
}

$workerId =
    $hostname .
    ':cron:' .
    getmypid();

$processed = 0;
$completed = 0;
$failed = 0;

echo "========================================\n";
echo " PermuOK Compatibility Cron Worker\n";
echo "========================================\n";
echo "Worker: {$workerId}\n";
echo "Máximo jobs: {$maxJobs}\n";
echo "Máximo tiempo: {$maxRuntimeSeconds}s\n";
echo "----------------------------------------\n";

try {
    while (
        $processed < $maxJobs &&
        (microtime(true) - $startedAt) <
            $maxRuntimeSeconds
    ) {
        $job =
            CompatibilityJobService::claimNext(
                $workerId
            );

        /*
         * Cola vacía:
         * terminamos esta ejecución.
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
            $result =
                CompatibilityJobProcessor::process(
                    $job
                );

            CompatibilityJobService::complete(
                $jobId
            );

            if (!empty($result['skipped'])) {
                echo "[JOB {$jobId}] skipped\n";

                if (!empty($result['reason'])) {
                    echo "  {$result['reason']}\n";
                }

                continue;
            }

            $completed++;

            echo "[JOB {$jobId}] completed\n";
        } catch (Throwable $e) {
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
    fwrite(
        STDERR,
        "[WORKER ERROR] " .
            $e->getMessage() .
            "\n"
    );

    exit(1);
}

$runtime =
    round(
        microtime(true) - $startedAt,
        2
    );

echo "\n";
echo "----------------------------------------\n";
echo "Procesados: {$processed}\n";
echo "Completados: {$completed}\n";
echo "Errores: {$failed}\n";
echo "Tiempo: {$runtime}s\n";
echo "Worker finalizado.\n";