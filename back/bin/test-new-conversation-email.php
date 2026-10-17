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

$opportunityEntityType =
    trim(
        (string)(
            $argv[3]
            ?? 'property'
        )
    );

$allowedTypes = [
    'new_conversation',
    'new_message',
    'compatibility_mutual_interest',
    'contact_share_requested',
    'contact_share_accepted',
    'contact_share_rejected',
];

$allowedOpportunityTypes = [
    'property',
    'search_request',
];

if ($email === '') {
    exit("Uso:\n\n" .
        "php bin/test-new-conversation-email.php EMAIL TIPO OPORTUNIDAD\n\n" .
        "Tipos de email:\n" .
        "- new_conversation\n" .
        "- new_message\n" .
        "- compatibility_mutual_interest\n" .
        "- contact_share_requested\n" .
        "- contact_share_accepted\n" .
        "- contact_share_rejected\n\n" .
        "Tipos de oportunidad:\n" .
        "- property\n" .
        "- search_request\n");
}

if (
    !in_array(
        $type,
        $allowedTypes,
        true
    )
) {
    exit("Tipo de email inválido.\n");
}

if (
    !in_array(
        $opportunityEntityType,
        $allowedOpportunityTypes,
        true
    )
) {
    exit("Tipo de oportunidad inválido.\n\n" .
        "Usá:\n" .
        "- property\n" .
        "- search_request\n");
}

try {
    $job =
        NotificationService::queueNotificationTestEmail(
            $email,
            $type,
            $opportunityEntityType
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

    echo "Oportunidad: " .
        $opportunityEntityType .
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
