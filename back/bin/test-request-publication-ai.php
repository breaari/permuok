<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\PublicationAIAnalysisService;

$propertyId = 14;

try {
    $result =
        PublicationAIAnalysisService::
            requestPropertyAnalysis(
                $propertyId
            );

    echo PHP_EOL;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    echo PHP_EOL . PHP_EOL;

} catch (Throwable $e) {
    echo PHP_EOL;
    echo "ERROR" . PHP_EOL;
    echo "=====" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo PHP_EOL;

    exit(1);
}