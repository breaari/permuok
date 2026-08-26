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

$hostname = gethostname();

if (
    $hostname === false ||
    trim($hostname) === ''
) {
    $hostname = 'unknown-host';
}

$workerId =
    $hostname .
    ':' .
    getmypid();

$idleSleepMicroseconds = 750000;

echo "========================================\n";
echo " PermuOK Email Worker\n";
echo "========================================\n";
echo "Worker: {$workerId}\n";
echo "Modo: permanente\n";
echo "----------------------------------------\n";

try {
    while (true) {
        $job =
            EmailJobService::claimNext(
                $workerId
            );

        if ($job === null) {
            usleep(
                $idleSleepMicroseconds
            );

            continue;
        }

        $jobId =
            (int)$job['id'];

        $attempt =
            (int)$job['attempts'];

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

            echo "[EMAIL {$jobId}] sent\n";
        } catch (Throwable $e) {
            EmailJobService::fail(
                $jobId,
                $e
            );

            echo "[EMAIL {$jobId}] error\n";
            echo "  {$e->getMessage()}\n";
        }
    }
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "\n[WORKER ERROR] " .
            $e->getMessage() .
            "\n"
    );

    exit(1);
}