<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\PublicationAIAnalysisService;

$propertyId = 14;

try {
    $input =
        PublicationAIAnalysisService::preparePropertyInput(
            $propertyId
        );

    $hash =
        PublicationAIAnalysisService::buildPropertyInputHash(
            $propertyId
        );

    echo PHP_EOL;
    echo "PROPERTY AI INPUT" . PHP_EOL;
    echo "=================" . PHP_EOL;

    echo json_encode(
        $input,
        JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    echo PHP_EOL . PHP_EOL;

    echo "INPUT HASH" . PHP_EOL;
    echo "==========" . PHP_EOL;
    echo $hash . PHP_EOL;

    echo PHP_EOL;

} catch (Throwable $e) {
    echo PHP_EOL;
    echo "ERROR" . PHP_EOL;
    echo "=====" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo PHP_EOL;

    exit(1);
}