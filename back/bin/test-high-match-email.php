<?php

declare(strict_types=1);

use App\Services\HighMatchNotificationService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv =
    Dotenv\Dotenv::createImmutable(
        __DIR__ . '/../'
    );

$dotenv->load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(
        "Este script solo puede ejecutarse desde CLI.\n"
    );
}

$email =
    trim(
        (string)(
            $argv[1]
            ?? ''
        )
    );

if ($email === '') {
    exit(
        "Uso:\n\n" .
        "php bin/test-high-match-email.php EMAIL\n"
    );
}

try {
    $job =
        HighMatchNotificationService::queueTestEmail(
            $email
        );

    echo "\n";
    echo "Email high_match de prueba encolado correctamente.\n";
    echo "----------------------------------------\n";

    echo "Job ID: " .
        ($job['id'] ?? '—') .
        "\n";

    echo "Destino: " .
        $email .
        "\n";

    echo "Tipo: high_match_test\n";
    echo "\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "Error: " .
            $e->getMessage() .
            "\n"
    );

    exit(1);
}