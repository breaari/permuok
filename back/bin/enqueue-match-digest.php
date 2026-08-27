<?php

use Dotenv\Dotenv;
use App\Services\CompatibilityJobService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

try {
    $job =
        CompatibilityJobService::
        enqueueMatchDailyDigest(2);

    echo
        'Match daily digest encolado. Job ID: ' .
        ($job['id'] ?? 'N/A') .
        PHP_EOL;
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'Error: ' .
        $e->getMessage() .
        PHP_EOL
    );

    exit(1);
}