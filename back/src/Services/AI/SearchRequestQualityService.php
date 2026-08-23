<?php

namespace App\Services\AI;

use PDO;
use Exception;

class SearchRequestQualityService
{
    private const VERSION = '1.1';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function analyze(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

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

        $propertyTypes =
            self::getPropertyTypes(
                $pdo,
                $searchRequestId
            );

        $amenities =
            self::getAmenities(
                $pdo,
                $searchRequestId
            );

        $criteria =
            self::evaluateCriteria(
                $request,
                $propertyTypes,
                $amenities
            );

        $location =
            self::evaluateLocation(
                $request
            );

        $payment =
            self::evaluatePayment(
                $request
            );

        return [
            'status' => 'completed',

            'score' =>
            round(
                $criteria['score'] +
                    $location['score'] +
                    $payment['score'],
                2
            ),

            'sections' => [
                'criteria' => [
                    'score' =>
                    $criteria['score'],

                    'max_score' =>
                    30,
                ],

                'location' => [
                    'score' =>
                    $location['score'],

                    'max_score' =>
                    15,
                ],

                'payment' => [
                    'score' =>
                    $payment['score'],

                    'max_score' =>
                    15,
                ],
            ],

            'suggestions' =>
            array_merge(
                $criteria['suggestions'],
                $location['suggestions'],
                $payment['suggestions']
            ),

            'version' =>
            self::VERSION,
        ];
    }

    private static function evaluateCriteria(
        array $request,
        array $propertyTypes,
        array $amenities
    ): array {
        $score = 0.0;
        $suggestions = [];

        /*
     * 1. Tipo de propiedad.
     * Es el criterio estructural principal.
     */
        if ($propertyTypes) {
            $score += 10;
        } else {
            $suggestions[] = [
                'field' => 'property_types',
                'priority' => 'high',
                'title' => 'Definí qué propiedad buscás.',
                'message' =>
                'Seleccioná al menos un tipo de propiedad.',
            ];
        }

        /*
     * 2. Criterios cuantitativos.
     *
     * No premiamos cada campo individualmente.
     * Nos importa que exista algún criterio adicional
     * cuando realmente forma parte de la búsqueda.
     */
        $quantitativeCriteria = 0;

        if (
            self::positive(
                $request['min_total_area'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if (
            self::positive(
                $request['min_covered_area'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if (
            self::positive(
                $request['min_bedrooms'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if (
            self::positive(
                $request['min_bathrooms'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if (
            self::positive(
                $request['min_garages'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if (
            self::positive(
                $request['max_antiquity'] ?? null
            )
        ) {
            $quantitativeCriteria++;
        }

        if ($quantitativeCriteria >= 1) {
            $score += 5;
        }

        if ($quantitativeCriteria >= 2) {
            $score += 2;
        }

        if ($quantitativeCriteria >= 3) {
            $score += 1;
        }

        /*
     * 3. Estado / condición.
     *
     * Definirlo aporta precisión,
     * pero dejarlo abierto también es válido.
     */
        if (
            !empty($request['property_condition']) &&
            $request['property_condition'] !== 'any'
        ) {
            $score += 4;
        } else {
            /*
         * No lo penalizamos fuerte:
         * una búsqueda sin preferencia de estado
         * sigue siendo válida.
         */
            $score += 2;
        }

        /*
     * 4. Amenities o requisitos específicos.
     */
        if ($amenities) {
            $score += 4;
        }

        /*
     * 5. Profundidad general de criterios.
     *
     * Premia búsquedas que tengan más definición,
     * pero sin obligarlas a completar toda la ficha.
     */
        $definitionSignals = 0;

        if ($propertyTypes) {
            $definitionSignals++;
        }

        if ($quantitativeCriteria >= 1) {
            $definitionSignals++;
        }

        if (
            !empty($request['property_condition']) &&
            $request['property_condition'] !== 'any'
        ) {
            $definitionSignals++;
        }

        if ($amenities) {
            $definitionSignals++;
        }

        if ($definitionSignals >= 2) {
            $score += 2;
        }

        if ($definitionSignals >= 3) {
            $score += 1;
        }

        if ($definitionSignals >= 4) {
            $score += 1;
        }

        return [
            'score' => min(30, $score),
            'suggestions' => $suggestions,
        ];
    }

    private static function evaluateLocation(
        array $request
    ): array {
        $score = 0.0;
        $suggestions = [];

        if (
            !empty($request['country']) &&
            !empty($request['province'])
        ) {
            $score += 7;
        }

        if (!empty($request['city'])) {
            $score += 4;
        }

        if (!empty($request['zone'])) {
            $score += 3;
        } elseif (
            empty($request['open_to_other_zones'])
        ) {
            $suggestions[] = [
                'field' => 'zone',
                'priority' => 'medium',
                'title' => 'Precisá la zona.',
                'message' =>
                'Una zona definida mejora la precisión de los matches.',
            ];
        }

        if (
            !empty($request['open_to_other_zones'])
        ) {
            $score += 1;
        }

        return [
            'score' => min(15, $score),
            'suggestions' => $suggestions,
        ];
    }

    private static function evaluatePayment(
        array $request
    ): array {
        $score = 0.0;
        $suggestions = [];

        $min =
            self::number(
                $request['min_value'] ?? null
            );

        $max =
            self::number(
                $request['max_value'] ?? null
            );

        if (
            $max !== null &&
            $max > 0 &&
            !empty($request['currency'])
        ) {
            $score += 8;
        } else {
            $suggestions[] = [
                'field' => 'max_value',
                'priority' => 'high',
                'title' => 'Definí un presupuesto.',
                'message' =>
                'El valor máximo es uno de los criterios más importantes para generar matches.',
            ];
        }

        if (
            $min !== null &&
            $min >= 0
        ) {
            $score += 2;
        }

        if (
            !empty($request['payment_mode_cash']) ||
            !empty($request['payment_mode_swap'])
        ) {
            $score += 3;
        }

        if (
            !empty($request['payment_mode_swap']) &&
            self::nonNegative(
                $request['cash_difference_max']
                    ?? null
            )
        ) {
            $score += 2;
        } elseif (
            empty($request['payment_mode_swap'])
        ) {
            $score += 2;
        }

        return [
            'score' => min(15, $score),
            'suggestions' => $suggestions,
        ];
    }

    private static function getPropertyTypes(
        PDO $pdo,
        int $id
    ): array {
        $st = $pdo->prepare("
            SELECT property_type
            FROM search_request_property_types
            WHERE search_request_id = :id
        ");

        $st->execute([
            'id' => $id,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function getAmenities(
        PDO $pdo,
        int $id
    ): array {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM search_request_amenities
            WHERE search_request_id = :id
              AND deleted_at IS NULL
        ");

        $st->execute([
            'id' => $id,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function number(
        mixed $value
    ): ?float {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {
            return null;
        }

        return (float)$value;
    }

    private static function positive(
        mixed $value
    ): bool {
        $value = self::number($value);

        return
            $value !== null &&
            $value > 0;
    }

    private static function nonNegative(
        mixed $value
    ): bool {
        $value = self::number($value);

        return
            $value !== null &&
            $value >= 0;
    }
}
