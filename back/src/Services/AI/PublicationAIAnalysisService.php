<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class PublicationAIAnalysisService
{
    private const PROMPT_VERSION = '2.1';
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const ALLOWED_QUESTION_FIELDS = [
        'title',
        'description',
        'property_type',
        'price',
        'currency',
        'country',
        'province',
        'city',
        'zone',
        'address',
        'total_area',
        'covered_area',
        'bedrooms',
        'bathrooms',
        'garages',
        'antiquity',
        'amenities',
        'images',
        'requirements',
    ];


    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    private static function filterAllowedQuestions(
        array $questions
    ): array {
        return array_values(
            array_filter(
                $questions,
                static function ($question): bool {
                    if (!is_array($question)) {
                        return false;
                    }

                    $field = trim(
                        (string)($question['field'] ?? '')
                    );

                    return in_array(
                        $field,
                        self::ALLOWED_QUESTION_FIELDS,
                        true
                    );
                }
            )
        );
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
     * Ya existe un análisis pendiente
     * o procesándose.
     *
     * Volvemos a pedir el job para garantizar que
     * exista uno activo. active_key evita duplicados.
     */
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

            $job =
                CompatibilityJobService::enqueuePropertyAIAnalysis(
                    $propertyId,
                    $analysisId
                );

            return [
                'analysis_id' =>
                $analysisId,

                'status' =>
                (string)$existing['status'],

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
     * Si anteriormente falló exactamente
     * este mismo análisis, reutilizamos su fila.
     *
     * Lo volvemos a pending y generamos/reutilizamos
     * el job correspondiente.
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
                        $propertyId,
                        $analysisId
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
                    $propertyId,
                    $analysisId
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


    public static function getCurrentPropertyAnalysis(
        int $propertyId
    ): ?array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        /*
     * Calculamos el hash actual.
     *
     * Así nunca devolvemos como vigente un análisis
     * correspondiente a una versión vieja de la ficha.
     */
        $inputHash =
            self::buildPropertyInputHash(
                $propertyId
            );

        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT
            id,
            entity_type,
            entity_id,
            status,

            content_score,
title_score,
description_score,
image_score,
consistency_score,
professionalism_score,
matchability_score,

            suggested_title,
            suggested_description,

            questions_json,
            suggestions_json,
            detected_features_json,
            contradictions_json,
            image_analysis_json,

            model_name,
            prompt_version,
            input_hash,
            error_message,

            analyzed_at,
            created_at,
            updated_at

        FROM publication_ai_analyses

        WHERE entity_type = 'property'
          AND entity_id = :entity_id
          AND input_hash = :input_hash
          AND prompt_version = :prompt_version

        ORDER BY id DESC

        LIMIT 1
    ");

        $st->execute([
            'entity_id' =>
            $propertyId,

            'input_hash' =>
            $inputHash,

            'prompt_version' =>
            self::PROMPT_VERSION,
        ]);

        $row =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            return null;
        }

        $decodeJson =
            static function ($value): array {
                if (
                    $value === null ||
                    trim((string)$value) === ''
                ) {
                    return [];
                }

                $decoded =
                    json_decode(
                        (string)$value,
                        true
                    );

                return is_array($decoded)
                    ? $decoded
                    : [];
            };

        return [
            'id' =>
            (int)$row['id'],

            'entity_type' =>
            (string)$row['entity_type'],

            'entity_id' =>
            (int)$row['entity_id'],

            'status' =>
            (string)$row['status'],

            'scores' => [
                'content' =>
                $row['content_score'] !== null
                    ? (float)$row['content_score']
                    : null,

                'title' =>
                $row['title_score'] !== null
                    ? (float)$row['title_score']
                    : null,

                'description' =>
                $row['description_score'] !== null
                    ? (float)$row['description_score']
                    : null,

                'images' =>
                $row['image_score'] !== null
                    ? (float)$row['image_score']
                    : null,

                'consistency' =>
                $row['consistency_score'] !== null
                    ? (float)$row['consistency_score']
                    : null,

                'professionalism' =>
                $row['professionalism_score'] !== null
                    ? (float)$row['professionalism_score']
                    : null,

                'matchability' =>
                $row['matchability_score'] !== null
                    ? (float)$row['matchability_score']
                    : null,
            ],

            'suggested_title' =>
            $row['suggested_title'],

            'suggested_description' =>
            $row['suggested_description'],

            'questions' =>
            $decodeJson(
                $row['questions_json']
            ),

            'suggestions' =>
            $decodeJson(
                $row['suggestions_json']
            ),

            'detected_features' =>
            $decodeJson(
                $row['detected_features_json']
            ),

            'contradictions' =>
            $decodeJson(
                $row['contradictions_json']
            ),

            'image_analysis' =>
            $decodeJson(
                $row['image_analysis_json']
            ),

            'model_name' =>
            $row['model_name'],

            'prompt_version' =>
            (string)$row['prompt_version'],

            'input_hash' =>
            (string)$row['input_hash'],

            'error_message' =>
            $row['error_message'],

            'analyzed_at' =>
            $row['analyzed_at'],

            'created_at' =>
            $row['created_at'],

            'updated_at' =>
            $row['updated_at'],
        ];
    }


    public static function processPropertyAnalysis(
        int $propertyId,
        int $analysisId,
        int $attempt = 1,
        int $maxAttempts = 3
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        if ($analysisId <= 0) {
            throw new Exception(
                'El job de análisis IA no contiene un analysis_id válido.'
            );
        }

        $pdo = self::db();

        /*
     * Recuperamos exactamente el análisis
     * asociado al job.
     *
     * Ya no intentamos adivinarlo mediante
     * el hash actual de la propiedad.
     */
        $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses

        WHERE id = :id
          AND entity_type = 'property'
          AND entity_id = :entity_id

        LIMIT 1
    ");

        $st->execute([
            'id' =>
            $analysisId,

            'entity_id' =>
            $propertyId,
        ]);

        $analysis =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$analysis) {
            throw new Exception(
                'No se encontró el análisis IA asociado al job.'
            );
        }

        /*
     * Si ya terminó, el job puede cerrarse
     * sin volver a llamar a OpenAI.
     */
        if ($analysis['status'] === 'completed') {
            return [
                'ok' => true,
                'skipped' => true,
                'analysis_id' => $analysisId,
                'reason' =>
                'El análisis ya había sido completado.',
            ];
        }

        /*
     * Verificamos que la propiedad todavía tenga
     * exactamente el contenido para el cual se
     * solicitó este análisis.
     */
        $currentHash =
            self::buildPropertyInputHash(
                $propertyId
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
            self::markAnalysisFailed(
                $analysisId,
                'La publicación cambió después de solicitar el análisis. Solicitá un nuevo análisis para evaluar la versión actual.'
            );

            return [
                'ok' => true,
                'skipped' => true,
                'analysis_id' => $analysisId,
                'reason' =>
                'La publicación cambió después de solicitar el análisis.',
            ];
        }

        /*
     * Marcamos exactamente esta fila
     * como processing.
     */
        $stProcessing = $pdo->prepare("
        UPDATE publication_ai_analyses

        SET
            status = 'processing',
            error_message = NULL,
            updated_at = CURRENT_TIMESTAMP

        WHERE id = :id

        LIMIT 1
    ");

        $stProcessing->execute([
            'id' =>
            $analysisId,
        ]);

        try {
            $totalStartedAt = microtime(true);

            $prepareStartedAt = microtime(true);

            $input =
                self::preparePropertyInput(
                    $propertyId
                );

            $prepareSeconds =
                microtime(true) -
                $prepareStartedAt;

            $openAIStartedAt = microtime(true);

            $result =
                self::callOpenAIForProperty(
                    $input
                );

            $openAISeconds =
                microtime(true) -
                $openAIStartedAt;

            $persistStartedAt = microtime(true);

            /*
     * La llamada a OpenAI puede ser larga.
     * Renovamos la conexión antes de guardar.
     */

            self::completeAnalysis(
                $analysisId,
                $result
            );

            $persistSeconds =
                microtime(true) -
                $persistStartedAt;

            $totalSeconds =
                microtime(true) -
                $totalStartedAt;

            echo PHP_EOL;
            echo "[AI ANALYSIS #{$analysisId}]" . PHP_EOL;

            echo sprintf(
                "  Preparar datos: %.2f s%s",
                $prepareSeconds,
                PHP_EOL
            );

            echo sprintf(
                "  OpenAI:        %.2f s%s",
                $openAISeconds,
                PHP_EOL
            );

            echo sprintf(
                "  Persistencia:  %.2f s%s",
                $persistSeconds,
                PHP_EOL
            );

            echo sprintf(
                "  TOTAL IA:      %.2f s%s",
                $totalSeconds,
                PHP_EOL
            );
            $usage =
                is_array($result['_usage'] ?? null)
                ? $result['_usage']
                : [];

            echo "  Tokens:" . PHP_EOL;

            echo sprintf(
                "    Input:       %d%s",
                (int)($usage['input_tokens'] ?? 0),
                PHP_EOL
            );

            echo sprintf(
                "    Cached:      %d%s",
                (int)($usage['cached_tokens'] ?? 0),
                PHP_EOL
            );

            echo sprintf(
                "    Output:      %d%s",
                (int)($usage['output_tokens'] ?? 0),
                PHP_EOL
            );

            echo sprintf(
                "    Reasoning:   %d%s",
                (int)($usage['reasoning_tokens'] ?? 0),
                PHP_EOL
            );

            echo sprintf(
                "    Total:       %d%s",
                (int)($usage['total_tokens'] ?? 0),
                PHP_EOL
            );
            return [
                'ok' =>
                true,

                'analysis_id' =>
                $analysisId,

                'status' =>
                'completed',

                'model' =>
                $result['_model'] ?? null,
            ];
        } catch (Throwable $e) {
            if ($attempt < $maxAttempts) {
                self::markAnalysisPendingForRetry(
                    $analysisId,
                    $e->getMessage()
                );
            } else {
                self::markAnalysisFailed(
                    $analysisId,
                    $e->getMessage()
                );
            }

            throw $e;
        }
    }
    private static function callOpenAIForProperty(
        array $input
    ): array {
        $apiKey =
            trim(
                (string)(
                    $_ENV['OPENAI_API_KEY']
                    ?? getenv('OPENAI_API_KEY')
                    ?: ''
                )
            );

        if ($apiKey === '') {
            throw new Exception(
                'OPENAI_API_KEY no está configurada.'
            );
        }

        $model =
            trim(
                (string)(
                    $_ENV['OPENAI_MODEL']
                    ?? getenv('OPENAI_MODEL')
                    ?: self::DEFAULT_MODEL
                )
            );

        if ($model === '') {
            $model =
                self::DEFAULT_MODEL;
        }

        $content = [];

        /*
     * Instrucciones + ficha completa.
     */
        $content[] = [
            'type' =>
            'input_text',

            'text' =>
            self::buildPropertyPrompt(
                $input
            ),
        ];

        /*
     * Imágenes.
     *
     * Para esta primera versión las enviamos
     * como data URLs base64.
     *
     * Así no necesitamos que OpenAI tenga acceso
     * público a las URLs privadas de PermuOK.
     */
        foreach (
            $input['images'] ?? []
            as $image
        ) {
            $dataUrl =
                self::propertyImageToDataUrl(
                    (string)(
                        $image['file_path'] ?? ''
                    )
                );

            if ($dataUrl === null) {
                continue;
            }

            $content[] = [
                'type' =>
                'input_image',

                'image_url' =>
                $dataUrl,

                /*
             * auto es suficiente inicialmente.
             */
                'detail' =>
                'low',
            ];
        }

        $body = [
            'model' =>
            $model,

            'reasoning' => [
                'effort' =>
                'low',
            ],

            'input' => [
                [
                    'role' =>
                    'user',

                    'content' =>
                    $content,
                ],
            ],

            /*
         * Structured Outputs.
         */
            'text' => [
                'verbosity' => 'low',
                'format' => [
                    'type' =>
                    'json_schema',

                    'name' =>
                    'property_publication_analysis',

                    'strict' =>
                    true,

                    'schema' =>
                    self::analysisSchema(),
                ],
            ],
        ];

        $jsonBody = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        $ch = curl_init(
            'https://api.openai.com/v1/responses'
        );

        if ($ch === false) {
            throw new Exception(
                'No se pudo inicializar la conexión con OpenAI.'
            );
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST =>
                true,

                CURLOPT_RETURNTRANSFER =>
                true,

                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' .
                        $apiKey,

                    'Content-Type: application/json',
                ],

                CURLOPT_POSTFIELDS =>
                $jsonBody,

                CURLOPT_CONNECTTIMEOUT =>
                15,

                CURLOPT_TIMEOUT =>
                120,
            ]
        );

        $response =
            curl_exec(
                $ch
            );

        if ($response === false) {
            $curlError =
                curl_error(
                    $ch
                );

            curl_close(
                $ch
            );

            throw new Exception(
                'Error conectando con OpenAI: ' .
                    $curlError
            );
        }

        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        curl_close(
            $ch
        );

        try {
            $decoded =
                json_decode(
                    $response,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'OpenAI devolvió una respuesta inválida.',
                0,
                $e
            );
        }

        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {
            $apiMessage =
                $decoded['error']['message']
                ?? 'Error desconocido de OpenAI.';

            throw new Exception(
                'OpenAI API: ' .
                    $apiMessage
            );
        }

        $outputText =
            self::extractOpenAIOutputText(
                $decoded
            );

        if ($outputText === '') {
            throw new Exception(
                'OpenAI no devolvió el análisis esperado.'
            );
        }

        try {
            $analysis =
                json_decode(
                    $outputText,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo interpretar el análisis generado por OpenAI.',
                0,
                $e
            );
        }

        $analysis['_model'] =
            (string)(
                $decoded['model']
                ?? $model
            );

        $usage =
            is_array($decoded['usage'] ?? null)
            ? $decoded['usage']
            : [];

        $inputDetails =
            is_array($usage['input_tokens_details'] ?? null)
            ? $usage['input_tokens_details']
            : [];

        $outputDetails =
            is_array($usage['output_tokens_details'] ?? null)
            ? $usage['output_tokens_details']
            : [];

        $analysis['_usage'] = [
            'input_tokens' =>
            (int)($usage['input_tokens'] ?? 0),

            'cached_tokens' =>
            (int)($inputDetails['cached_tokens'] ?? 0),

            'output_tokens' =>
            (int)($usage['output_tokens'] ?? 0),

            'reasoning_tokens' =>
            (int)($outputDetails['reasoning_tokens'] ?? 0),

            'total_tokens' =>
            (int)($usage['total_tokens'] ?? 0),
        ];

        return $analysis;
    }

    private static function markAnalysisPendingForRetry(
        int $analysisId,
        string $message
    ): void {
        if ($analysisId <= 0) {
            return;
        }

        $pdo = self::db();

        $message = mb_substr(
            $message,
            0,
            6000
        );

        $st = $pdo->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'pending',
            error_message = :error_message,
            analyzed_at = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        LIMIT 1
    ");

        $st->execute([
            'error_message' => $message,
            'id' => $analysisId,
        ]);
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

    private static function buildPropertyPrompt(
        array $input
    ): string {
        $propertyJson =
            json_encode(
                $input,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        return <<<PROMPT
Sos un asistente especializado en optimización de publicaciones inmobiliarias B2B.

Analizá esta publicación para PermuOK.

OBJETIVOS:

1. Evaluar la calidad del título.
2. Evaluar la descripción.
3. Detectar información posiblemente faltante.
4. Detectar contradicciones entre ficha, descripción e imágenes.
5. Analizar las imágenes buscando atributos útiles de la propiedad.
6. Formular preguntas inteligentes cuando una imagen sugiera un atributo
   que no esté confirmado en la ficha.

REGLAS IMPORTANTES:

- Nunca inventes características.
- Nunca afirmes como cierto algo observado únicamente en una imagen.
- Si una imagen parece mostrar un atributo no informado, generá una pregunta de confirmación.
- Ejemplo: si parece verse un balcón, preguntá "¿La propiedad cuenta con balcón?".
- Los atributos detectados visualmente deben tener requires_confirmation=true.
- No modifiques datos estructurados.
- No inventes superficies, dormitorios, baños, antigüedad, ubicación ni precio.
- Detectá lenguaje poco profesional, texto de prueba, insultos, exageraciones o contenido poco comercial.
- Si falta información, no la inventes: sugerí completarla.
- Escribí siempre en español rioplatense profesional.
- Priorizá utilidad comercial y calidad para matching inmobiliario B2B.

REGLAS DE CALIDAD DEL TÍTULO Y DESCRIPCIÓN:

- Evaluá título y descripción de manera crítica. No consideres bueno un texto
  solamente porque existe.

- Un título extremadamente genérico, de prueba o que no permita identificar
  correctamente la propiedad debe recibir un title_score bajo y generar una
  suggestion de prioridad high o medium.

- Ejemplos de títulos pobres:
  "casa blanca"
  "departamento"
  "propiedad prueba"
  "casa en venta"

  CRITERIOS PARA UN BUEN TÍTULO EN PERMUOK:

- El título debe ser comercial, claro y breve.
- Más información NO significa necesariamente un mejor título.
- Evaluá el título con los mismos criterios que usaría una inmobiliaria
  profesional para presentar rápidamente una propiedad.

- Priorizar, cuando estén confirmados:
  tipo de propiedad,
  cantidad de ambientes,
  atributo diferencial relevante,
  zona o barrio.

- Ejemplos de buenos títulos:
  "Departamento 2 ambientes en Lanús Oeste"
  "Casa 4 ambientes con jardín en Martínez"
  "Departamento 3 ambientes con cochera en Mar del Plata"
  "Local comercial en Palermo Soho"

- NO penalices un título por no incluir:
  superficie total,
  superficie cubierta,
  piso,
  número de departamento,
  dirección exacta,
  baños,
  dormitorios si ya se expresaron los ambientes,
  antigüedad,
  tipo de cochera,
  precio o moneda.

- No sugieras agregar piso, número de departamento o dirección exacta al título.

- No sugieras agregar superficies al título salvo que la superficie sea
  excepcionalmente relevante para identificar la tipología del inmueble.

- No sugieras datos técnicos solamente para aumentar la cantidad de
  información del título.

- Piso, unidad/departamento y dirección exacta son datos de identificación
  y ubicación, no atributos comerciales que normalmente deban formar parte
  del título.

- Un título breve que identifica correctamente tipología, ambientes y zona
  puede recibir un puntaje alto aunque no incluya otros datos.

- Si el título fue generado correctamente con esos criterios, no generar una
  suggestion sólo para hacerlo más largo.


- Una descripción demasiado breve, genérica, redundante, de prueba o que no
  aporte información inmobiliaria útil debe recibir un description_score bajo.

- Una descripción de menos de 60 caracteres normalmente debe considerarse
  insuficiente, salvo casos excepcionales.

- Una descripción como:
  "una descripción de la casa blanca"
  debe considerarse claramente pobre.

- Cuando título o descripción sean pobres, agregá obligatoriamente una entrada
  en suggestions.

- Para problemas de redacción de título o descripción usá suggestions, no
  questions. Las questions se reservan para datos que el usuario debe confirmar.

- Si la descripción es muy pobre:
  description_score no debe superar 35.

- Si el título es extremadamente genérico:
  title_score no debe superar 40.

- No inventes características para recomendar cómo completar el texto.
  Indicá qué tipo de información sería útil agregar.

  CRITERIOS DE PUNTUACIÓN ADICIONALES:

CONSISTENCY_SCORE:

Evalúa únicamente la coherencia interna de la publicación.

Considerar:
- contradicciones entre título, descripción y ficha;
- contradicciones entre ubicación cargada y dirección;
- incompatibilidades entre características informadas;
- contradicciones entre requisitos de operación;
- datos confirmados que se contradigan entre sí.

No penalizar simplemente porque falte información.
La falta de datos no es por sí sola una contradicción.

Las inferencias obtenidas únicamente desde imágenes no deben considerarse
contradicciones confirmadas.

Referencia orientativa:
90-100: información coherente, sin contradicciones relevantes.
70-89: pequeñas inconsistencias o ambigüedades.
50-69: varias inconsistencias que deberían revisarse.
0-49: contradicciones importantes que afectan la confiabilidad de la publicación.


PROFESSIONALISM_SCORE:

Evalúa la calidad profesional del contenido que verá o utilizará una inmobiliaria.

Considerar:
- lenguaje profesional;
- claridad;
- ausencia de insultos;
- ausencia de texto de prueba;
- ausencia de contenido absurdo o irrelevante;
- ausencia de spam o exageraciones impropias;
- redacción apropiada para una publicación inmobiliaria.

No penalizar por faltantes estructurales que corresponden a otros criterios.

Referencia orientativa:
90-100: contenido profesional y apropiado.
70-89: correcto, con pequeños aspectos mejorables.
50-69: problemas evidentes de redacción o presentación.
0-49: contenido poco profesional, de prueba, ofensivo o claramente inadecuado.


MATCHABILITY_SCORE:

Evalúa qué tan interpretables, coherentes y útiles son los requisitos de
operación para producir compatibilidades inmobiliarias.

Considerar:
- claridad de lo que la inmobiliaria acepta o busca;
- coherencia entre modalidad y requisitos;
- precisión de tipos de propiedad buscados;
- precisión de zonas;
- rangos económicos cuando correspondan;
- características mínimas cuando sean relevantes;
- utilidad real de las notas de búsqueda o permuta.

No premiar simplemente por tener muchos campos cargados.
Una búsqueda abierta puede ser válida si está configurada de forma coherente.
No inventar requisitos que no fueron informados.

Referencia orientativa:
90-100: requisitos claros y altamente utilizables para matching.
70-89: suficientemente claros, con mejoras menores posibles.
50-69: útiles pero ambiguos o poco definidos.
0-49: insuficientes, contradictorios o difíciles de utilizar para matching.

REGLAS SOBRE PREGUNTAS:

Las questions NO son una lista de datos faltantes.

Generar una question únicamente cuando exista una incertidumbre real que
requiera confirmación del usuario.

Una question está justificada solamente en estos casos:

1. Una imagen sugiere una característica que no está confirmada en la ficha.
   Ejemplo:
   una imagen parece mostrar un balcón, pero no hay información suficiente
   para afirmarlo.
   Pregunta válida:
   "¿La propiedad cuenta con balcón?"

2. Dos datos confirmados parecen contradecirse y es necesario saber cuál es
   correcto.
   Ejemplo:
   la descripción indica dos baños pero la ficha estructurada indica uno.
   Pregunta válida:
   "La descripción menciona dos baños, pero la ficha indica uno.
   ¿Podés confirmar cuántos baños tiene la propiedad?"

NO generar questions simplemente porque:

- falta un dato;
- una búsqueda o permuta podría ser más específica;
- no se cargaron amenities;
- no se definieron tipos de propiedad buscados;
- no se definieron zonas;
- no existe un rango económico;
- la descripción podría contener más información;
- un campo opcional está vacío.

Esos casos deben resolverse mediante suggestions cuando representen
una mejora útil.

Si un problema puede expresarse como una acción concreta que el usuario
puede realizar, debe ir en suggestions y NO en questions.

No generar una question sobre un tema que ya esté cubierto por una suggestion.

Un valor 0 confirmado no significa que el dato esté faltante.

No preguntes por cocheras si la ficha indica 0, salvo que exista evidencia
visual concreta que sugiera una cochera.

Máximo 3 questions.
Es válido y preferible devolver questions=[] cuando no exista ninguna
incertidumbre real que necesite confirmación.

Las preguntas están dirigidas a usuarios inmobiliarios, no a programadores.

Nunca mencionar nombres internos de campos, variables o datos técnicos.

No usar términos como:
"formatted_address",
"country_code",
"place_id",
"latitude",
"longitude",
"property_type",
"criteria_mode"
ni ningún otro nombre interno del sistema.

Cuando exista una inconsistencia, describirla con lenguaje natural.

La pregunta debe poder entenderse sin conocer cómo funciona PermuOK.

REGLAS SOBRE SUGERENCIAS:

- Cada suggestion debe representar una acción concreta que el usuario pueda realizar.
- field debe identificar el área principal a mejorar, por ejemplo:
  title,
  description,
  images,
  amenities,
  location,
  features,
  requirements,
  matchability,
  professionalism.

- action debe ser un título breve, concreto y accionable.

Buenos ejemplos de action:
"Agregar amenities"
"Mejorar la descripción"
"Completar la galería"
"Definir mejor la permuta buscada"
"Corregir una inconsistencia"
"Agregar características relevantes"

Malos ejemplos:
"Mejorar este aspecto"
"Revisar publicación"
"Optimizar"
"Información faltante"

- message debe explicar brevemente qué conviene hacer y por qué.
- Cada suggestion debe corresponder a una acción realmente disponible
  para el usuario en PermuOK o a una mejora que pueda realizar directamente
  sobre el título o la descripción.

- Nunca recomendar completar datos técnicos que PermuOK obtiene o puede
  obtener automáticamente de Google Maps.

- No recomendar agregar:
  código postal,
  coordenadas,
  latitud,
  longitud,
  place_id,
  identificadores de Google,
  ni otros datos técnicos de geolocalización.

- No recomendar "completar la ubicación" si país, provincia, ciudad, zona,
  dirección seleccionada y geolocalización son coherentes.

- Sólo generar una suggestion de ubicación cuando exista un problema real
  que el usuario pueda corregir, por ejemplo:
  ciudad y dirección seleccionada no coinciden,
  provincia y ciudad son incompatibles,
  o la zona informada contradice claramente la dirección.

- No recomendar información que el usuario no tenga forma razonable de
  cargar o modificar desde PermuOK.

- Si un dato comercial no tiene un campo estructurado específico pero puede
  incorporarse naturalmente en la descripción, la acción debe ser
  "Mejorar la descripción", no inventar un campo inexistente.
- No generar más de una suggestion para el mismo problema o field salvo que
  sean acciones claramente diferentes.

- Evitar repetir en suggestions una mejora ya expresada de forma equivalente.

- Si varios detalles pertenecen a la misma acción, agruparlos en una sola
  suggestion.

Ejemplo:
En lugar de generar por separado:
"Agregar orientación"
"Agregar calefacción"
"Agregar expensas"

generar:
action: "Completar la descripción"
message: "Podés agregar orientación, calefacción, expensas y otros diferenciales confirmados."

REGLA DE SEPARACIÓN ENTRE ACCIONES:

Cada suggestion debe tratar un único tema principal.

Si generás una suggestion específica para amenities:
- no vuelvas a pedir amenities dentro de "Mejorar la descripción".

Si generás una suggestion específica para imágenes:
- no repitas recomendaciones de imágenes en otras suggestions.

Si generás una suggestion específica para requisitos o matching:
- no repitas tipos, zonas o rangos económicos en otra suggestion.

Usá el campo estructurado correspondiente cuando exista.

Ejemplo:

Correcto:

field: "description"
action: "Mejorar la descripción"
message: "Sumá información confirmada sobre distribución, orientación,
estado, luminosidad y otros diferenciales comerciales."

field: "amenities"
action: "Agregar amenities"
message: "Cargá los amenities confirmados para mejorar filtros y
compatibilidades."

Incorrecto:

field: "description"
action: "Completar la descripción"
message: "Agregá orientación, expensas y amenities."

si además existe una suggestion específica para amenities.

REGLAS SOBRE IMÁGENES:

- detected_features contiene solamente características potenciales
  del inmueble.
- No incluir "render", "plano", "foto", "imagen borrosa",
  "vista cenital", "diagrama" ni características de la imagen dentro
  de detected_features.
- Toda característica inferida exclusivamente desde una imagen debe
  llevar requires_confirmation=true.
- Nunca considerar una inferencia visual como dato confirmado.
- El tipo y la calidad de la imagen deben informarse solamente en
  image_analysis.

REGLAS SOBRE CONTRADICCIONES:

- contradictions debe contener solamente contradicciones objetivas
  entre datos confirmados.
- Una diferencia entre la ficha y algo inferido visualmente NO debe
  considerarse una contradicción confirmada.
- En esos casos generar una question y, si corresponde, una suggestion
  indicando que existe una posible inconsistencia visual.

REGLAS DE REDACCIÓN:

- Nunca mostrar códigos internos como apartment, house, commercial.
- Usar sus equivalentes naturales en español.
- Evitar abreviaturas innecesarias y texto repetitivo.
- No usar lenguaje robótico.
- No inventar información para hacer el texto más atractivo.

CAMPOS ACTUALMENTE EDITABLES EN LA FICHA DE PROPIEDAD:

- título
- descripción
- tipo de propiedad
- precio
- moneda
- país
- provincia
- ciudad
- zona
- dirección
- superficie total
- superficie cubierta
- dormitorios
- baños
- cocheras
- antigüedad
- amenities disponibles en PermuOK
- imágenes
- requisitos de permuta/búsqueda

REGLA:
Las preguntas de questions deben referirse únicamente a datos que el
usuario pueda confirmar y luego cargar en alguno de estos campos.

Si detectás información comercial útil que no tenga un campo estructurado
específico pero pueda incorporarse razonablemente en el título o la descripción,
podés sugerir incorporarla allí.

No sugerir datos técnicos, administrativos o de geolocalización que PermuOK
no permita editar y que puedan obtenerse automáticamente por el sistema.

REGLAS DE CONCISIÓN DE LA RESPUESTA:

- La respuesta debe ser breve, concreta y accionable.
- No repetir el mismo problema en questions, suggestions y contradictions
  salvo que sea estrictamente necesario.
- Cada reason o message debe expresarse en una sola frase breve.
- Cada observación de imagen debe describir un único hallazgo.
- Priorizá solamente los hallazgos de mayor impacto comercial o para matching.
- No completar arrays con elementos de poco valor solamente para generar más contenido.

PUBLICACIÓN:

{$propertyJson}
PROMPT;
    }

    private static function analysisSchema(): array
    {
        return [
            'type' =>
            'object',

            'additionalProperties' =>
            false,

            'properties' => [
                'content_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'title_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'description_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'image_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'consistency_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'professionalism_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'matchability_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'questions' => [
                    'type' =>
                    'array',

                    'maxItems' =>
                    5,

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'field' => [
                                'type' => 'string',
                            ],

                            'question' => [
                                'type' => 'string',
                            ],

                            'reason' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'field',
                            'question',
                            'reason',
                        ],
                    ],
                ],

                'suggestions' => [
                    'type' =>
                    'array',

                    'maxItems' =>
                    5,

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'field' => [
                                'type' => 'string',
                            ],

                            'action' => [
                                'type' => 'string',
                            ],

                            'priority' => [
                                'type' => 'string',
                                'enum' => [
                                    'low',
                                    'medium',
                                    'high',
                                ],
                            ],

                            'message' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'field',
                            'action',
                            'priority',
                            'message',
                        ],
                    ],
                ],
                'detected_features' => [
                    'type' =>
                    'array',

                    'maxItems' =>
                    5,

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'feature' => [
                                'type' => 'string',
                            ],

                            'confidence' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                            ],

                            'requires_confirmation' => [
                                'type' => 'boolean',
                            ],

                            'reason' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'feature',
                            'confidence',
                            'requires_confirmation',
                            'reason',
                        ],
                    ],
                ],

                'contradictions' => [
                    'type' =>
                    'array',

                    'maxItems' =>
                    5,

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'field' => [
                                'type' => 'string',
                            ],

                            'message' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'field',
                            'message',
                        ],
                    ],
                ],

                'image_analysis' => [
                    'type' =>
                    'array',

                    'maxItems' =>
                    5,

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'image_index' => [
                                'type' => 'integer',
                            ],

                            'observations' => [
                                'type' =>
                                'array',

                                'maxItems' =>
                                3,

                                'items' => [
                                    'type' =>
                                    'string',
                                ],
                            ],

                            'quality_notes' => [
                                'type' =>
                                'array',

                                'maxItems' =>
                                2,

                                'items' => [
                                    'type' =>
                                    'string',
                                ],
                            ],
                        ],

                        'required' => [
                            'image_index',
                            'observations',
                            'quality_notes',
                        ],
                    ],
                ],
            ],

            'required' => [
                'content_score',
                'title_score',
                'description_score',
                'image_score',
                'consistency_score',
                'professionalism_score',
                'matchability_score',
                'questions',
                'suggestions',
                'detected_features',
                'contradictions',
                'image_analysis',
            ],
        ];
    }

    private static function propertyImageToDataUrl(
        string $relativePath
    ): ?string {
        $relativePath =
            trim(
                $relativePath
            );

        if ($relativePath === '') {
            return null;
        }

        $uploadsBase =
            rtrim(
                (string)(
                    $_ENV['UPLOADS_DIR']
                    ?? ''
                ),
                '/\\'
            );

        if ($uploadsBase === '') {
            $uploadsBase =
                dirname(
                    __DIR__,
                    3
                ) .
                '/uploads';
        }

        $fullPath =
            $uploadsBase .
            '/' .
            ltrim(
                $relativePath,
                '/\\'
            );

        if (
            !is_file(
                $fullPath
            )
        ) {
            return null;
        }

        $mime =
            mime_content_type(
                $fullPath
            );

        $allowed = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (
            !in_array(
                $mime,
                $allowed,
                true
            )
        ) {
            return null;
        }

        $binary =
            file_get_contents(
                $fullPath
            );

        if ($binary === false) {
            return null;
        }

        return sprintf(
            'data:%s;base64,%s',
            $mime,
            base64_encode(
                $binary
            )
        );
    }

    private static function extractOpenAIOutputText(
        array $response
    ): string {
        foreach (
            $response['output'] ?? []
            as $outputItem
        ) {
            if (
                ($outputItem['type'] ?? null)
                !== 'message'
            ) {
                continue;
            }

            foreach (
                $outputItem['content'] ?? []
                as $content
            ) {
                if (
                    ($content['type'] ?? null)
                    !== 'output_text'
                ) {
                    continue;
                }

                $text =
                    trim(
                        (string)(
                            $content['text'] ?? ''
                        )
                    );

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private static function completeAnalysis(
        int $analysisId,
        array $result
    ): void {
        $pdo = self::db();

        $st = $pdo->prepare("
        UPDATE publication_ai_analyses

        SET
            status = 'completed',

            content_score =
                :content_score,

            title_score =
                :title_score,

            description_score =
                :description_score,

            image_score =
                :image_score,
consistency_score =
    :consistency_score,

professionalism_score =
    :professionalism_score,

matchability_score =
    :matchability_score,
            questions_json =
                :questions_json,

            suggestions_json =
                :suggestions_json,

            detected_features_json =
                :detected_features_json,

            contradictions_json =
                :contradictions_json,

            image_analysis_json =
                :image_analysis_json,

            model_name =
                :model_name,

            error_message =
                NULL,

            analyzed_at =
                NOW(),

            updated_at =
                CURRENT_TIMESTAMP

        WHERE id = :id

        LIMIT 1
    ");

        $encode =
            static fn(array $value): string =>
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
            );

        $st->execute([
            'content_score' =>
            (float)$result['content_score'],

            'title_score' =>
            (float)$result['title_score'],

            'description_score' =>
            (float)$result['description_score'],

            'image_score' =>
            (float)$result['image_score'],
            'consistency_score' =>
            (float)$result['consistency_score'],

            'professionalism_score' =>
            (float)$result['professionalism_score'],

            'matchability_score' =>
            (float)$result['matchability_score'],
            'questions_json' =>
            $encode(
                self::filterAllowedQuestions(
                    $result['questions'] ?? []
                )
            ),
            'suggestions_json' =>
            $encode(
                $result['suggestions'] ?? []
            ),

            'detected_features_json' =>
            $encode(
                $result['detected_features'] ?? []
            ),

            'contradictions_json' =>
            $encode(
                $result['contradictions'] ?? []
            ),

            'image_analysis_json' =>
            $encode(
                $result['image_analysis'] ?? []
            ),

            'model_name' =>
            trim(
                (string)(
                    $result['_model']
                    ?? self::DEFAULT_MODEL
                )
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
