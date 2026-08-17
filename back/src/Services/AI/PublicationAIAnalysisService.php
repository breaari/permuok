<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class PublicationAIAnalysisService
{
    private const PROMPT_VERSION = '1.0';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    /**
     * Prepara todos los datos que posteriormente
     * enviaremos al modelo de IA.
     *
     * No realiza ninguna llamada externa.
     */
    public static function preparePropertyInput(
        int $propertyId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $pdo = self::db();

        /*
         * Propiedad.
         */
        $stProperty = $pdo->prepare("
            SELECT
                id,
                title,
                description,
                property_type,
                price,
                currency,

                country_code,
                country,
                province,
                city,
                zone,

                address,
                formatted_address,
                postal_code,

                latitude,
                longitude,

                total_area,
                covered_area,
                bedrooms,
                bathrooms,
                garages,
                antiquity

            FROM properties

            WHERE id = :id
              AND deleted_at IS NULL

            LIMIT 1
        ");

        $stProperty->execute([
            'id' => $propertyId,
        ]);

        $property = $stProperty->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$property) {
            throw new Exception(
                'Propiedad no encontrada.'
            );
        }

        /*
         * Imágenes.
         *
         * Conservamos el orden porque también
         * importa para el análisis de presentación.
         */
        $stImages = $pdo->prepare("
            SELECT
                file_path,
                sort_order,
                is_cover

            FROM property_images

            WHERE property_id = :property_id
              AND deleted_at IS NULL

            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $stImages->execute([
            'property_id' => $propertyId,
        ]);

        $images =
            $stImages->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        /*
         * Amenities.
         *
         * Los ordenamos por contenido para que
         * el hash no cambie sólo por orden de inserción.
         */
        $stAmenities = $pdo->prepare("
            SELECT amenity_code

            FROM property_amenities

            WHERE property_id = :property_id
              AND deleted_at IS NULL

            ORDER BY amenity_code ASC
        ");

        $stAmenities->execute([
            'property_id' => $propertyId,
        ]);

        $amenities =
            $stAmenities->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        /*
         * Requisitos de permuta / búsqueda.
         */
        $stRequirements = $pdo->prepare("
            SELECT
                id,

                criteria_mode,

                accepts_total_swap,
                accepts_swap_plus_cash,
                accepts_multiple_swap,
                accepts_open_proposals,
                accepts_cash_only,

                cash_difference_direction,
                cash_difference_min,
                cash_difference_max,
                cash_difference_currency,

                price_min,
                price_max,
                price_currency,

                min_total_area,
                max_total_area,

                min_covered_area,
                max_covered_area,

                min_bedrooms,
                min_bathrooms,
                min_garages,
                max_antiquity,

                open_to_other_zones,

                notes,
                property_condition

            FROM property_requirements

            WHERE property_id = :property_id
              AND deleted_at IS NULL

            LIMIT 1
        ");

        $stRequirements->execute([
            'property_id' => $propertyId,
        ]);

        $requirements =
            $stRequirements->fetch(
                PDO::FETCH_ASSOC
            ) ?: null;

        $requirementPropertyTypes = [];
        $requirementLocations = [];

        if (
            $requirements !== null &&
            !empty($requirements['id'])
        ) {
            $requirementId =
                (int)$requirements['id'];

            /*
             * Tipos de propiedad aceptados.
             */
            $stTypes = $pdo->prepare("
                SELECT property_type

                FROM property_requirement_property_types

                WHERE property_requirement_id =
                    :property_requirement_id

                ORDER BY property_type ASC
            ");

            $stTypes->execute([
                'property_requirement_id' =>
                $requirementId,
            ]);

            $requirementPropertyTypes =
                $stTypes->fetchAll(
                    PDO::FETCH_COLUMN
                ) ?: [];

            /*
             * Ubicaciones aceptadas.
             *
             * No incluimos IDs porque buscamos que
             * dos requisitos semánticamente iguales
             * produzcan el mismo hash.
             */
            $stLocations = $pdo->prepare("
                SELECT
                    country_code,
                    country,
                    province,
                    city,
                    zone

                FROM property_requirement_locations

                WHERE property_requirement_id =
                    :property_requirement_id

                ORDER BY
                    country_code ASC,
                    country ASC,
                    province ASC,
                    city ASC,
                    zone ASC,
                    id ASC
            ");

            $stLocations->execute([
                'property_requirement_id' =>
                $requirementId,
            ]);

            $requirementLocations =
                $stLocations->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];
        }

        /*
         * Normalización.
         *
         * El objetivo es que:
         *
         * 65489.00
         * 65489
         *
         * generen el mismo input lógico.
         */
        $normalizedProperty = [
            'title' =>
            self::stringValue(
                $property['title'] ?? null
            ),

            'description' =>
            self::stringValue(
                $property['description'] ?? null
            ),

            'property_type' =>
            self::stringValue(
                $property['property_type'] ?? null
            ),

            'price' =>
            self::numberValue(
                $property['price'] ?? null
            ),

            'currency' =>
            self::stringValue(
                $property['currency'] ?? null
            ),

            'location' => [
                'country_code' =>
                self::stringValue(
                    $property['country_code'] ?? null
                ),

                'country' =>
                self::stringValue(
                    $property['country'] ?? null
                ),

                'province' =>
                self::stringValue(
                    $property['province'] ?? null
                ),

                'city' =>
                self::stringValue(
                    $property['city'] ?? null
                ),

                'zone' =>
                self::stringValue(
                    $property['zone'] ?? null
                ),

                'address' =>
                self::stringValue(
                    $property['address'] ?? null
                ),

                'formatted_address' =>
                self::stringValue(
                    $property['formatted_address'] ?? null
                ),

                'postal_code' =>
                self::stringValue(
                    $property['postal_code'] ?? null
                ),

                'latitude' =>
                self::numberValue(
                    $property['latitude'] ?? null
                ),

                'longitude' =>
                self::numberValue(
                    $property['longitude'] ?? null
                ),
            ],

            'features' => [
                'total_area' =>
                self::numberValue(
                    $property['total_area'] ?? null
                ),

                'covered_area' =>
                self::numberValue(
                    $property['covered_area'] ?? null
                ),

                'bedrooms' =>
                self::numberValue(
                    $property['bedrooms'] ?? null
                ),

                'bathrooms' =>
                self::numberValue(
                    $property['bathrooms'] ?? null
                ),

                'garages' =>
                self::numberValue(
                    $property['garages'] ?? null
                ),

                'antiquity' =>
                self::numberValue(
                    $property['antiquity'] ?? null
                ),

                'amenities' =>
                array_values(
                    array_map(
                        fn($item) =>
                        trim((string)$item),
                        $amenities
                    )
                ),
            ],
        ];

        $normalizedImages =
            array_values(
                array_map(
                    fn(array $image) => [
                        'file_path' =>
                        trim(
                            (string)(
                                $image['file_path'] ?? ''
                            )
                        ),

                        'sort_order' =>
                        (int)(
                            $image['sort_order'] ?? 0
                        ),

                        'is_cover' =>
                        (int)(
                            $image['is_cover'] ?? 0
                        ) === 1,
                    ],
                    $images
                )
            );

        $normalizedRequirements = null;

        if ($requirements !== null) {
            $normalizedRequirements = [
                'criteria_mode' =>
                self::stringValue(
                    $requirements['criteria_mode'] ?? null
                ),

                'accepts_total_swap' =>
                self::boolValue(
                    $requirements['accepts_total_swap'] ?? null
                ),

                'accepts_swap_plus_cash' =>
                self::boolValue(
                    $requirements['accepts_swap_plus_cash'] ?? null
                ),

                'accepts_multiple_swap' =>
                self::boolValue(
                    $requirements['accepts_multiple_swap'] ?? null
                ),

                'accepts_open_proposals' =>
                self::boolValue(
                    $requirements['accepts_open_proposals'] ?? null
                ),

                'accepts_cash_only' =>
                self::boolValue(
                    $requirements['accepts_cash_only'] ?? null
                ),

                'cash_difference' => [
                    'direction' =>
                    self::stringValue(
                        $requirements['cash_difference_direction'] ?? null
                    ),

                    'min' =>
                    self::numberValue(
                        $requirements['cash_difference_min'] ?? null
                    ),

                    'max' =>
                    self::numberValue(
                        $requirements['cash_difference_max'] ?? null
                    ),

                    'currency' =>
                    self::stringValue(
                        $requirements['cash_difference_currency'] ?? null
                    ),
                ],

                'price' => [
                    'min' =>
                    self::numberValue(
                        $requirements['price_min'] ?? null
                    ),

                    'max' =>
                    self::numberValue(
                        $requirements['price_max'] ?? null
                    ),

                    'currency' =>
                    self::stringValue(
                        $requirements['price_currency'] ?? null
                    ),
                ],

                'features' => [
                    'min_total_area' =>
                    self::numberValue(
                        $requirements['min_total_area'] ?? null
                    ),

                    'max_total_area' =>
                    self::numberValue(
                        $requirements['max_total_area'] ?? null
                    ),

                    'min_covered_area' =>
                    self::numberValue(
                        $requirements['min_covered_area'] ?? null
                    ),

                    'max_covered_area' =>
                    self::numberValue(
                        $requirements['max_covered_area'] ?? null
                    ),

                    'min_bedrooms' =>
                    self::numberValue(
                        $requirements['min_bedrooms'] ?? null
                    ),

                    'min_bathrooms' =>
                    self::numberValue(
                        $requirements['min_bathrooms'] ?? null
                    ),

                    'min_garages' =>
                    self::numberValue(
                        $requirements['min_garages'] ?? null
                    ),

                    'max_antiquity' =>
                    self::numberValue(
                        $requirements['max_antiquity'] ?? null
                    ),
                ],

                'property_types' =>
                array_values(
                    array_map(
                        fn($item) =>
                        trim((string)$item),
                        $requirementPropertyTypes
                    )
                ),

                'locations' =>
                array_values(
                    array_map(
                        fn(array $location) => [
                            'country_code' =>
                            self::stringValue(
                                $location['country_code'] ?? null
                            ),

                            'country' =>
                            self::stringValue(
                                $location['country'] ?? null
                            ),

                            'province' =>
                            self::stringValue(
                                $location['province'] ?? null
                            ),

                            'city' =>
                            self::stringValue(
                                $location['city'] ?? null
                            ),

                            'zone' =>
                            self::stringValue(
                                $location['zone'] ?? null
                            ),
                        ],
                        $requirementLocations
                    )
                ),

                'open_to_other_zones' =>
                self::boolValue(
                    $requirements['open_to_other_zones'] ?? null
                ),

                'property_condition' =>
                self::stringValue(
                    $requirements['property_condition'] ?? null
                ),

                'notes' =>
                self::stringValue(
                    $requirements['notes'] ?? null
                ),
            ];
        }

        return [
            'entity_type' =>
            'property',

            'entity_id' =>
            $propertyId,

            'property' =>
            $normalizedProperty,

            'images' =>
            $normalizedImages,

            'requirements' =>
            $normalizedRequirements,
        ];
    }

    /**
     * Hash del contenido actual.
     *
     * Si ningún dato relevante cambió,
     * el hash será exactamente el mismo.
     */
    public static function buildPropertyInputHash(
        int $propertyId
    ): string {
        $input =
            self::preparePropertyInput(
                $propertyId
            );

        $hashPayload = [
            'prompt_version' =>
            self::PROMPT_VERSION,

            'input' =>
            $input,
        ];

        try {
            $json = json_encode(
                $hashPayload,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo generar el input del análisis IA.',
                0,
                $e
            );
        }

        return hash(
            'sha256',
            $json
        );
    }

    public static function getPromptVersion(): string
    {
        return self::PROMPT_VERSION;
    }

    /**
     * Solicita un análisis IA para el estado actual
     * de una propiedad.
     *
     * Todavía no ejecuta la IA:
     * crea/reutiliza el registro y encola el trabajo.
     */
    public static function requestPropertyAnalysis(
        int $propertyId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $pdo = self::db();

        /*
     * Generamos el hash del estado actual.
     */
        $inputHash =
            self::buildPropertyInputHash(
                $propertyId
            );

        /*
     * Buscamos si este mismo contenido ya fue
     * solicitado anteriormente.
     */
        $stExisting = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses

        WHERE entity_type = 'property'
          AND entity_id = :entity_id
          AND input_hash = :input_hash
          AND prompt_version = :prompt_version

        ORDER BY id DESC

        LIMIT 1
    ");

        $stExisting->execute([
            'entity_id' =>
            $propertyId,

            'input_hash' =>
            $inputHash,

            'prompt_version' =>
            self::PROMPT_VERSION,
        ]);

        $existing =
            $stExisting->fetch(
                PDO::FETCH_ASSOC
            ) ?: null;

        /*
     * Ya fue analizado exactamente este contenido.
     *
     * No gastamos otra llamada IA.
     */
        if (
            $existing &&
            $existing['status'] === 'completed'
        ) {
            return [
                'analysis_id' =>
                (int)$existing['id'],

                'status' =>
                'completed',

                'input_hash' =>
                $inputHash,

                'reused' =>
                true,

                'queued' =>
                false,
            ];
        }

        /*
     * Ya existe un trabajo pendiente o procesándose.
     *
     * Tampoco generamos otro.
     */
        if (
            $existing &&
            in_array(
                $existing['status'],
                ['pending', 'processing'],
                true
            )
        ) {
            return [
                'analysis_id' =>
                (int)$existing['id'],

                'status' =>
                (string)$existing['status'],

                'input_hash' =>
                $inputHash,

                'reused' =>
                true,

                'queued' =>
                false,
            ];
        }

        /*
     * Si anteriormente falló el mismo análisis,
     * reutilizamos su fila y permitimos reintentar.
     */
        if (
            $existing &&
            $existing['status'] === 'failed'
        ) {
            $analysisId =
                (int)$existing['id'];

            $stRetry = $pdo->prepare("
            UPDATE publication_ai_analyses

            SET
                status = 'pending',
                error_message = NULL,
                analyzed_at = NULL,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :id

            LIMIT 1
        ");

            $stRetry->execute([
                'id' =>
                $analysisId,
            ]);

            try {
                $job =
                    CompatibilityJobService::enqueuePropertyAIAnalysis(
                        $propertyId
                    );
            } catch (Throwable $e) {
                self::markAnalysisFailed(
                    $analysisId,
                    $e->getMessage()
                );

                throw $e;
            }

            return [
                'analysis_id' =>
                $analysisId,

                'status' =>
                'pending',

                'input_hash' =>
                $inputHash,

                'reused' =>
                true,

                'queued' =>
                true,

                'job_id' =>
                (int)($job['id'] ?? 0),
            ];
        }

        /*
     * No existe análisis para este contenido.
     *
     * Creamos uno nuevo.
     */
        $stInsert = $pdo->prepare("
        INSERT INTO publication_ai_analyses (
            entity_type,
            entity_id,
            status,
            prompt_version,
            input_hash
        ) VALUES (
            'property',
            :entity_id,
            'pending',
            :prompt_version,
            :input_hash
        )
    ");

        $stInsert->execute([
            'entity_id' =>
            $propertyId,

            'prompt_version' =>
            self::PROMPT_VERSION,

            'input_hash' =>
            $inputHash,
        ]);

        $analysisId =
            (int)$pdo->lastInsertId();

        try {
            $job =
                CompatibilityJobService::enqueuePropertyAIAnalysis(
                    $propertyId
                );
        } catch (Throwable $e) {
            self::markAnalysisFailed(
                $analysisId,
                $e->getMessage()
            );

            throw $e;
        }

        return [
            'analysis_id' =>
            $analysisId,

            'status' =>
            'pending',

            'input_hash' =>
            $inputHash,

            'reused' =>
            false,

            'queued' =>
            true,

            'job_id' =>
            (int)($job['id'] ?? 0),
        ];
    }

    private static function markAnalysisFailed(
        int $analysisId,
        string $message
    ): void {
        if ($analysisId <= 0) {
            return;
        }

        $pdo = self::db();

        $st = $pdo->prepare("
        UPDATE publication_ai_analyses

        SET
            status = 'failed',
            error_message = :error_message,
            updated_at = CURRENT_TIMESTAMP

        WHERE id = :id

        LIMIT 1
    ");

        $st->execute([
            'error_message' =>
            mb_substr(
                $message,
                0,
                6000
            ),

            'id' =>
            $analysisId,
        ]);
    }

    private static function stringValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim((string)$value);

        return $value === ''
            ? null
            : $value;
    }

    private static function numberValue(
        mixed $value
    ): int|float|null {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number =
            (float)$value;

        if (
            floor($number) ===
            $number
        ) {
            return (int)$number;
        }

        return $number;
    }

    private static function boolValue(
        mixed $value
    ): bool {
        return (int)$value === 1;
    }
}
