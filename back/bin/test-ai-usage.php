<?php

declare(strict_types=1);

use App\Services\AI\AiUsageService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

$id = AiUsageService::log([
    'provider' => 'openai',
    'model_name' => 'test-php',
    'operation' => 'php_test',
    'entity_type' => 'property',
    'entity_id' => 15,
    'input_tokens' => 100,
    'cached_input_tokens' => 20,
    'output_tokens' => 50,
    'total_tokens' => 150,
    'duration_ms' => 1000,
    'status' => 'success',
]);

var_dump($id);