<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/../'
);

$dotenv->load();

require_once __DIR__ . '/../db.php';

$pdo = pdo();

$st = $pdo->prepare("
    SELECT
        id,
        questions_json,
        suggestions_json,
        detected_features_json,
        contradictions_json,
        image_analysis_json
    FROM publication_ai_analyses
    WHERE id = :id
    LIMIT 1
");

$st->execute([
    'id' => 1,
]);

$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit("Análisis no encontrado.\n");
}

$fields = [
    'questions_json',
    'suggestions_json',
    'detected_features_json',
    'contradictions_json',
    'image_analysis_json',
];

foreach ($fields as $field) {
    echo PHP_EOL;
    echo strtoupper($field) . PHP_EOL;
    echo str_repeat('=', strlen($field)) . PHP_EOL;

    $value = json_decode(
        $row[$field] ?? '[]',
        true
    );

    echo json_encode(
        $value,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    echo PHP_EOL;
}