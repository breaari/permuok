<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class SearchRequestAIAnalysisService
{
    private const PROMPT_VERSION = '1.0';
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function prepareInput(
        int $searchRequestId
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM search_requests
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $searchRequestId,
        ]);

        $request =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception(
                'Búsqueda no encontrada.'
            );
        }

        $stTypes = $pdo->prepare("
            SELECT property_type
            FROM search_request_property_types
            WHERE search_request_id = :id
            ORDER BY property_type ASC
        ");

        $stTypes->execute([
            'id' => $searchRequestId,
        ]);

        $propertyTypes =
            $stTypes->fetchAll(PDO::FETCH_COLUMN)
            ?: [];

        $stAmenities = $pdo->prepare("
            SELECT amenity_code
            FROM search_request_amenities
            WHERE search_request_id = :id
              AND deleted_at IS NULL
            ORDER BY amenity_code ASC
        ");

        $stAmenities->execute([
            'id' => $searchRequestId,
        ]);

        $amenities =
            $stAmenities->fetchAll(PDO::FETCH_COLUMN)
            ?: [];

        return [
            'entity_type' =>
            'search_request',

            'entity_id' =>
            $searchRequestId,

            'search_request' => [
                'title' =>
                trim((string)$request['title']),

                'description' =>
                trim((string)$request['description']),

                'location' => [
                    'country' =>
                    $request['country'],

                    'province' =>
                    $request['province'],

                    'city' =>
                    $request['city'],

                    'zone' =>
                    $request['zone'],

                    'open_to_other_zones' =>
                    (bool)$request['open_to_other_zones'],
                ],

                'property_types' =>
                $propertyTypes,

                'property_condition' =>
                $request['property_condition'],

                'budget' => [
                    'currency' =>
                    $request['currency'],

                    'min' =>
                    $request['min_value'],

                    'max' =>
                    $request['max_value'],
                ],

                'criteria' => [
                    'min_total_area' =>
                    $request['min_total_area'],

                    'min_covered_area' =>
                    $request['min_covered_area'],

                    'min_bedrooms' =>
                    $request['min_bedrooms'],

                    'min_bathrooms' =>
                    $request['min_bathrooms'],

                    'min_garages' =>
                    $request['min_garages'],

                    'max_antiquity' =>
                    $request['max_antiquity'],

                    'amenities' =>
                    $amenities,
                ],

                'payment' => [
                    'cash' =>
                    (bool)$request['payment_mode_cash'],

                    'swap' =>
                    (bool)$request['payment_mode_swap'],

                    'cash_difference_max' =>
                    $request['cash_difference_max'],

                    'cash_difference_currency' =>
                    $request['cash_difference_currency'],
                ],

                'urgency' =>
                $request['urgency'],

                'notes' =>
                $request['notes'],
            ],
        ];
    }

    public static function buildInputHash(
        int $searchRequestId
    ): string {
        $payload = [
            'prompt_version' =>
            self::PROMPT_VERSION,

            'input' =>
            self::prepareInput(
                $searchRequestId
            ),
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo preparar el análisis IA.',
                0,
                $e
            );
        }

        return hash(
            'sha256',
            $json
        );
    }

    public static function requestAnalysis(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

        $pdo = self::db();

        $inputHash =
            self::buildInputHash(
                $searchRequestId
            );

        $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses
        WHERE entity_type = 'search_request'
          AND entity_id = :entity_id
          AND input_hash = :input_hash
          AND prompt_version = :prompt_version
        ORDER BY id DESC
        LIMIT 1
    ");

        $st->execute([
            'entity_id' => $searchRequestId,
            'input_hash' => $inputHash,
            'prompt_version' => self::PROMPT_VERSION,
        ]);

        $existing =
            $st->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (
            $existing &&
            $existing['status'] === 'completed'
        ) {
            return [
                'analysis_id' => (int)$existing['id'],
                'status' => 'completed',
                'reused' => true,
                'queued' => false,
            ];
        }

        if (
            $existing &&
            in_array(
                $existing['status'],
                ['pending', 'processing'],
                true
            )
        ) {
            $analysisId =
                (int)$existing['id'];
        } elseif (
            $existing &&
            $existing['status'] === 'failed'
        ) {
            $analysisId =
                (int)$existing['id'];

            $pdo->prepare("
            UPDATE publication_ai_analyses
            SET
                status = 'pending',
                error_message = NULL,
                analyzed_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute([
                'id' => $analysisId,
            ]);
        } else {
            $stInsert = $pdo->prepare("
            INSERT INTO publication_ai_analyses (
                entity_type,
                entity_id,
                status,
                prompt_version,
                input_hash
            ) VALUES (
                'search_request',
                :entity_id,
                'pending',
                :prompt_version,
                :input_hash
            )
        ");

            $stInsert->execute([
                'entity_id' => $searchRequestId,
                'prompt_version' => self::PROMPT_VERSION,
                'input_hash' => $inputHash,
            ]);

            $analysisId =
                (int)$pdo->lastInsertId();
        }

        $job =
            CompatibilityJobService::enqueueSearchRequestAIAnalysis(
                $searchRequestId,
                $analysisId
            );

        return [
            'analysis_id' => $analysisId,
            'status' => 'pending',
            'reused' => $existing !== null,
            'queued' => true,
            'job_id' => (int)($job['id'] ?? 0),
        ];
    }
    public static function processAnalysis(
    int $searchRequestId,
    int $analysisId,
    int $attempt = 1,
    int $maxAttempts = 3
): array {
    if ($searchRequestId <= 0) {
        throw new Exception(
            'El ID de la búsqueda no es válido.'
        );
    }

    if ($analysisId <= 0) {
        throw new Exception(
            'El analysis_id no es válido.'
        );
    }

    $pdo = self::db();

    $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses
        WHERE id = :id
          AND entity_type = 'search_request'
          AND entity_id = :entity_id
        LIMIT 1
    ");

    $st->execute([
        'id' => $analysisId,
        'entity_id' => $searchRequestId,
    ]);

    $analysis =
        $st->fetch(PDO::FETCH_ASSOC);

    if (!$analysis) {
        throw new Exception(
            'No se encontró el análisis IA de la búsqueda.'
        );
    }

    if ($analysis['status'] === 'completed') {
        return [
            'ok' => true,
            'skipped' => true,
            'analysis_id' => $analysisId,
            'reason' => 'El análisis ya estaba completado.',
        ];
    }

    $currentHash =
        self::buildInputHash(
            $searchRequestId
        );

    $requestedHash =
        (string)($analysis['input_hash'] ?? '');

    if (
        $requestedHash === '' ||
        !hash_equals(
            $requestedHash,
            $currentHash
        )
    ) {
        $pdo->prepare("
            UPDATE publication_ai_analyses
            SET
                status = 'failed',
                error_message = :message,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute([
            'message' =>
                'La búsqueda cambió después de solicitar el análisis.',
            'id' =>
                $analysisId,
        ]);

        return [
            'ok' => true,
            'skipped' => true,
            'analysis_id' => $analysisId,
            'reason' =>
                'La búsqueda cambió después de solicitar el análisis.',
        ];
    }

    $pdo->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'processing',
            error_message = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute([
        'id' => $analysisId,
    ]);

    return [
        'ok' => true,
        'analysis_id' => $analysisId,
        'status' => 'processing',
        'attempt' => $attempt,
        'max_attempts' => $maxAttempts,
    ];
}
}
