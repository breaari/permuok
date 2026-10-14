<?php

declare(strict_types=1);

use App\Services\NotificationService;

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
        "Uso:\n" .
        "php bin/test-new-conversation-email.php tu@email.com\n"
    );
}

try {
    $job =
        NotificationService::queueNewConversationTestEmail(
            $email
        );

    echo "\n";
    echo "Email de prueba encolado.\n";
    echo "Job ID: " .
        ($job['id'] ?? '—') .
        "\n";
    echo "Destino: {$email}\n";
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