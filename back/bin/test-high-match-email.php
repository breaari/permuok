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

    exit("Este script solo puede ejecutarse desde CLI.\n");
}

$email =
    trim(
        (string)(
            $argv[1]
            ?? ''
        )
    );

$mode =
    strtolower(
        trim(
            (string)(
                $argv[2]
                ?? 'search'
            )
        )
    );

$allowedModes = [
    'search',
    'property',
];

if ($email === '') {
    exit("Uso:\n\n" .
        "php bin/test-high-match-email.php EMAIL MODO\n\n" .
        "Modos disponibles:\n" .
        "- search\n" .
        "- property\n");
}

if (
    !in_array(
        $mode,
        $allowedModes,
        true
    )
) {
    exit("Modo inválido.\n\n" .
        "Modos disponibles:\n" .
        "- search\n" .
        "- property\n");
}

try {
    $job =
        HighMatchNotificationService::queueTestEmail(
            $email,
            $mode
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

    echo "Modo: " .
        $mode .
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
