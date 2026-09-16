<?php

declare(strict_types=1);

use App\Services\BillingCycleService;

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
 * Evita que dos ejecuciones del cron procesen
 * simultáneamente las mismas membresías.
 */
$lockFile =
    sys_get_temp_dir() .
    '/permuok-billing-cycle-' .
    hash(
        'sha256',
        (string)realpath(__DIR__)
    ) .
    '.lock';

$lockHandle = fopen(
    $lockFile,
    'c'
);

if ($lockHandle === false) {
    fwrite(
        STDERR,
        "[ERROR] No se pudo crear el bloqueo del ciclo.\n"
    );

    exit(1);
}

if (
    !flock(
        $lockHandle,
        LOCK_EX | LOCK_NB
    )
) {
    fclose($lockHandle);

    echo
        "El ciclo de membresías ya está " .
        "siendo ejecutado.\n";

    exit(0);
}

try {
    $startedAt = microtime(true);

    $result =
        BillingCycleService::
            processDueMemberships();

    $runtime =
        round(
            microtime(true) - $startedAt,
            2
        );

    echo "Ciclo procesado correctamente.\n";

    echo json_encode(
        [
            'result' => $result,
            'runtime_seconds' => $runtime,
        ],
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    echo "\n";
} catch (Throwable $e) {
    error_log(
        '[BILLING CYCLE ERROR] ' .
        $e->getMessage()
    );

    fwrite(
        STDERR,
        "No se pudo procesar el ciclo de membresías.\n"
    );

    exit(1);
} finally {
    flock(
        $lockHandle,
        LOCK_UN
    );

    fclose($lockHandle);
}