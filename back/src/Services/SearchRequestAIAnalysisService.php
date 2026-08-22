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
}