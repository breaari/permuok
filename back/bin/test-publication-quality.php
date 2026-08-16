<?php

declare(strict_types=1);

use App\Services\AI\PublicationQualityService;

require_once __DIR__ . '/../vendor/autoload.php';

$result =
    PublicationQualityService::analyzeProperty(
        14
    );

echo json_encode(
    $result,
    JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE
);

echo PHP_EOL;