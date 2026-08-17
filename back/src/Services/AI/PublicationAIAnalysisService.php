<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class PublicationAIAnalysisService
{
    private const PROMPT_VERSION = '1.2';
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

            $job =
                CompatibilityJobService::enqueuePropertyAIAnalysis(
                    $propertyId
                );

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

    public static function processPropertyAnalysis(
        int $propertyId,
        int $attempt = 1,
        int $maxAttempts = 3
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $pdo = self::db();

        /*
     * Calculamos nuevamente el hash.
     *
     * Esto es importante porque el job pudo haber
     * esperado algunos segundos/minutos en la cola.
     */
        $currentHash =
            self::buildPropertyInputHash(
                $propertyId
            );

        /*
     * Buscamos específicamente el análisis pendiente
     * correspondiente al estado actual.
     */
        $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses

        WHERE entity_type = 'property'
          AND entity_id = :entity_id
          AND input_hash = :input_hash
          AND prompt_version = :prompt_version
          AND status IN ('pending', 'processing')

        ORDER BY id DESC

        LIMIT 1
    ");

        $st->execute([
            'entity_id' =>
            $propertyId,

            'input_hash' =>
            $currentHash,

            'prompt_version' =>
            self::PROMPT_VERSION,
        ]);

        $analysis =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        /*
     * Puede pasar que la propiedad haya cambiado
     * después de solicitar el análisis.
     *
     * En ese caso NO analizamos información vieja.
     */
        if (!$analysis) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' =>
                'No existe un análisis pendiente para el estado actual de la propiedad.',
            ];
        }

        $analysisId =
            (int)$analysis['id'];

        /*
     * Marcamos processing.
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
            /*
         * Volvemos a preparar el input exactamente
         * en el momento de enviar a OpenAI.
         */
            $input =
                self::preparePropertyInput(
                    $propertyId
                );

            $result =
                self::callOpenAIForProperty(
                    $input
                );

            /*
 * La llamada multimodal puede tardar lo suficiente
 * como para que MySQL cierre la conexión inactiva.
 *
 * Forzamos una conexión nueva antes de persistir
 * el resultado.
 */
            self::db(true);

            self::completeAnalysis(
                $analysisId,
                $result
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
                'auto',
            ];
        }

        $body = [
            'model' =>
            $model,

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

        return $analysis;
    }

    private static function markAnalysisPendingForRetry(
        int $analysisId,
        string $message
    ): void {
        if ($analysisId <= 0) {
            return;
        }

        $pdo = self::db(true);

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

        $pdo = self::db(true);

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
2. Proponer un título profesional y atractivo.
3. Evaluar la descripción.
4. Proponer una descripción mejorada.
5. Detectar información posiblemente faltante.
6. Detectar contradicciones entre ficha, descripción e imágenes.
7. Analizar las imágenes buscando atributos útiles de la propiedad.
8. Formular preguntas inteligentes cuando una imagen sugiera un atributo que no esté confirmado en la ficha.

REGLAS IMPORTANTES:

- Nunca inventes características.
- Nunca afirmes como cierto algo observado únicamente en una imagen.
- Si una imagen parece mostrar un atributo no informado, generá una pregunta de confirmación.
- Ejemplo: si parece verse un balcón, preguntá "¿La propiedad cuenta con balcón?".
- Los atributos detectados visualmente deben tener requires_confirmation=true.
- No modifiques datos estructurados.
- No inventes superficies, dormitorios, baños, antigüedad, ubicación ni precio.
- Detectá lenguaje poco profesional, texto de prueba, insultos, exageraciones o contenido poco comercial.
- El título sugerido debe ser descriptivo, profesional y conciso.
- La descripción sugerida debe utilizar únicamente datos confirmados.
- Si falta información, no la inventes: sugerí completarla.
- Escribí siempre en español rioplatense profesional.
- Priorizá utilidad comercial y calidad para matching inmobiliario B2B.

REGLAS SOBRE PREGUNTAS:

- Priorizá preguntas sobre datos que puedan ser confirmados y cargados
  actualmente en la ficha.
- Un valor 0 confirmado no significa que el dato esté faltante.
- No preguntes por cocheras si la ficha indica 0, salvo que exista
  evidencia visual concreta que sugiera una cochera.
- No generes preguntas genéricas solamente porque un campo comercial
  podría ser útil.
- Máximo 5 preguntas, priorizadas por impacto.
- Evitá preguntas redundantes.

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

Si detectás otro dato comercialmente útil que PermuOK todavía no permite
guardar, podés mencionarlo en suggestions, pero no generes una question
accionable sobre ese dato.

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

                'suggested_title' => [
                    'type' => 'string',
                ],

                'suggested_description' => [
                    'type' => 'string',
                ],

                'questions' => [
                    'type' =>
                    'array',

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

                    'items' => [
                        'type' =>
                        'object',

                        'additionalProperties' =>
                        false,

                        'properties' => [
                            'type' => [
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
                            'type',
                            'priority',
                            'message',
                        ],
                    ],
                ],

                'detected_features' => [
                    'type' =>
                    'array',

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

                                'items' => [
                                    'type' =>
                                    'string',
                                ],
                            ],

                            'quality_notes' => [
                                'type' =>
                                'array',

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
                'suggested_title',
                'suggested_description',
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
        $pdo = self::db(true);

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

            suggested_title =
                :suggested_title,

            suggested_description =
                :suggested_description,

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

            'suggested_title' =>
            trim(
                (string)$result['suggested_title']
            ),

            'suggested_description' =>
            trim(
                (string)$result['suggested_description']
            ),

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
