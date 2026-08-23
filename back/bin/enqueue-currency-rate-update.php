<?php

declare(strict_types=1);

use App\Services\CompatibilityJobService;

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

try {
    $job =
        CompatibilityJobService::
            enqueueCurrencyRateUpdate();

    echo "Cotización encolada correctamente.\n";
    echo "Job ID: {$job['id']}\n";
    echo "Estado: {$job['status']}\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "[ERROR] " .
            $e->getMessage() .
            "\n"
    );

    exit(1);
}