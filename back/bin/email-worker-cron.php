<?php

declare(strict_types=1);

use App\Services\EmailJobService;
use App\Services\EmailService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(
        "Este script solo puede ejecutarse desde CLI.\n"
    );
}

/*
|--------------------------------------------------------------------------
| Email Worker para Cron
|--------------------------------------------------------------------------
|
| Pensado para hosting compartido.
|
| - procesa hasta 20 emails por ejecución;
| - no queda corriendo indefinidamente;
| - corta antes de los 50 segundos;
| - si no hay emails pendientes, termina.
|
| Se puede ejecutar cada minuto.
|
*/

$maxJobs = 20;
$maxRuntimeSeconds = 50;

$startedAt = microtime(true);

$hostname = gethostname();

if (
    $hostname === false ||
    trim($hostname) === ''
) {
    $hostname = 'cron-host';
}

$workerId =
    $hostname .
    ':email-cron:' .
    getmypid();

$processed = 0;
$sent = 0;
$failed = 0;

echo "========================================\n";
echo " PermuOK Email Cron Worker\n";
echo "========================================\n";
echo "Worker: {$workerId}\n";
echo "Máximo emails: {$maxJobs}\n";
echo "Máximo tiempo: {$maxRuntimeSeconds}s\n";
echo "----------------------------------------\n";

try {
    while (
        $processed < $maxJobs &&
        (
            microtime(true) -
            $startedAt
        ) < $maxRuntimeSeconds
    ) {
        $job =
            EmailJobService::claimNext(
                $workerId
            );

        if ($job === null) {
            break;
        }

        $jobId =
            (int)$job['id'];

        $attempt =
            (int)$job['attempts'];

        $processed++;

        echo "\n";
        echo "[EMAIL {$jobId}] processing\n";
        echo "  Destino: {$job['email_to']}\n";
        echo "  Tipo: {$job['email_type']}\n";
        echo "  Intento: {$attempt}\n";

        try {
            EmailService::send(
                (string)$job['email_to'],
                (string)$job['subject'],
                (string)$job['html_body'],
                $job['text_body'] !== null
                    ? (string)$job['text_body']
                    : null,
                $job['recipient_name'] !== null
                    ? (string)$job['recipient_name']
                    : null
            );

            EmailJobService::complete(
                $jobId
            );

            $sent++;

            echo "[EMAIL {$jobId}] sent\n";
        } catch (Throwable $e) {
            EmailJobService::fail(
                $jobId,
                $e
            );

            $failed++;

            echo "[EMAIL {$jobId}] error\n";
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
        microtime(true) -
        $startedAt,
        2
    );

echo "\n";
echo "----------------------------------------\n";
echo "Procesados: {$processed}\n";
echo "Enviados: {$sent}\n";
echo "Errores: {$failed}\n";
echo "Tiempo: {$runtime}s\n";
echo "Worker finalizado.\n";