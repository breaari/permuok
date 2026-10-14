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

    exit("Este script solo puede ejecutarse desde CLI.\n");
}

$email =
    trim(
        (string)(
            $argv[1]
            ?? ''
        )
    );

$type =
    trim(
        (string)(
            $argv[2]
            ?? 'new_conversation'
        )
    );

$allowedTypes = [
    'new_conversation',
    'new_message',
    'compatibility_mutual_interest',
    'contact_share_requested',
];

if ($email === '') {
    exit("Uso:\n\n" .
        "php bin/test-new-conversation-email.php EMAIL TIPO\n\n" .
        "Tipos disponibles:\n" .
        "- new_conversation\n" .
        "- new_message\n" .
        "- compatibility_mutual_interest\n" .
        "- contact_share_requested\n");
}

if (
    !in_array(
        $type,
        $allowedTypes,
        true
    )
) {
    exit("Tipo de email inválido.\n\n" .
        "Tipos disponibles:\n" .
        "- new_conversation\n" .
        "- new_message\n" .
        "- compatibility_mutual_interest\n" .
        "- contact_share_requested\n");
}

try {
    $job =
        NotificationService::queueNotificationTestEmail(
            $email,
            $type
        );

    echo "\n";
    echo "Email de prueba encolado correctamente.\n";
    echo "----------------------------------------\n";
    echo "Job ID: " .
        ($job['id'] ?? '—') .
        "\n";

    echo "Destino: " .
        $email .
        "\n";

    echo "Tipo: " .
        $type .
        "\n";

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
