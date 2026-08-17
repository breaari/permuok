<?php

declare(strict_types=1);

use App\Services\AI\PublicationQualityService;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

require_once __DIR__ . '/../db.php';

$pdo = pdo();

echo "========================================\n";
echo " PermuOK - Backfill Quality Scores\n";
echo "========================================\n";

$st = $pdo->query("
    SELECT p.id
    FROM properties p

    LEFT JOIN publication_quality_scores q
        ON q.entity_type = 'property'
       AND q.entity_id = p.id

    WHERE p.deleted_at IS NULL
      AND q.entity_id IS NULL

    ORDER BY p.id ASC
");

$propertyIds = $st->fetchAll(
    PDO::FETCH_COLUMN
) ?: [];

$total = count($propertyIds);

echo "Propiedades pendientes: {$total}\n";
echo "----------------------------------------\n";

if ($total === 0) {
    echo "No hay propiedades pendientes.\n";
    exit(0);
}

$completed = 0;
$failed = 0;

foreach ($propertyIds as $propertyId) {
    $propertyId = (int)$propertyId;

    try {
        $result =
            PublicationQualityService::analyzeProperty(
                $propertyId
            );

        $completed++;

        echo sprintf(
            "[%d/%d] Propiedad #%d -> %.2f/100 (%s)\n",
            $completed + $failed,
            $total,
            $propertyId,
            (float)$result['score'],
            (string)$result['quality_level']
        );
    } catch (Throwable $e) {
        $failed++;

        echo sprintf(
            "[%d/%d] Propiedad #%d -> ERROR: %s\n",
            $completed + $failed,
            $total,
            $propertyId,
            $e->getMessage()
        );
    }
}

echo "----------------------------------------\n";
echo "Finalizado\n";
echo "Correctas: {$completed}\n";
echo "Con error: {$failed}\n";
echo "========================================\n";