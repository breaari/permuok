<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

use App\Services\CompatibilityJobService;

try {
    $job =
        CompatibilityJobService::
            enqueuePropertyAIAnalysis(
                14
            );

    echo PHP_EOL;

    echo json_encode(
        $job,
        JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    echo PHP_EOL . PHP_EOL;

} catch (Throwable $e) {
    echo PHP_EOL;
    echo "ERROR" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo PHP_EOL;

    exit(1);
}