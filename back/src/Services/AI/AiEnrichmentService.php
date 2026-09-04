<?php

namespace App\Services\AI;

use PDO;
use Exception;
use Throwable;

require_once __DIR__ . '/AiProviderInterface.php';
require_once __DIR__ . '/AiPromptService.php';

class AiEnrichmentService
{
    private const EXTRACTION_VERSION = '1.5';

    private static ?AiProviderInterface $provider = null;

    /**
     * Permite conectar OpenAI, Gemini u otro proveedor sin modificar
     * la lógica de enriquecimiento.
     */
    public static function setProvider(AiProviderInterface $provider): void
    {
        self::$provider = $provider;
    }

    /**
     * Analiza y enriquece una propiedad.
     */
    public static function enrichProperty(
        int $propertyId,
        bool $force = false
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido'
            );
        }

        $context =
            self::buildPropertyContext(
                $propertyId
            );

        $promptVersion =
            AiPromptService::PROPERTY_PROMPT_VERSION;

        $sourceHash =
            self::calculateSourceHash(
                $context,
                $promptVersion
            );

        if (!$force) {
            $current =
                self::getCurrentAnalysis(
                    'property',
                    $propertyId
                );

            if (
                $current &&
                hash_equals(
                    (string)$current['source_hash'],
                    $sourceHash
                ) &&
                (string)(
                    $current['prompt_version'] ?? ''
                ) === $promptVersion &&
                (string)(
                    $current['extraction_version'] ?? ''
                ) === self::EXTRACTION_VERSION
            ) {
                return self::formatStoredAnalysis(
                    $current,
                    true
                );
            }
        }

        if (!self::$provider) {
            throw new Exception(
                'No se configuró un proveedor de inteligencia artificial'
            );
        }

        /*
     * El contexto ya contiene estos datos porque
     * buildPropertyContext() los obtiene de properties.
     */
        $realEstateId =
            isset(
                $context['property']['real_estate_id']
            )
            ? (int)$context['property']['real_estate_id']
            : null;

        $userId =
            isset(
                $context['property']['created_by_user_id']
            )
            ? (int)$context['property']['created_by_user_id']
            : null;

        /*
     * Hoy el provider operativo es OpenAI.
     * Si en el futuro conectamos otro proveedor,
     * este valor podrá resolverse desde el contrato.
     */
        $providerName =
            self::$provider instanceof OpenAIProvider
            ? 'openai'
            : 'unknown';

        $startedAt =
            microtime(true);

        /*
     * Nos permite distinguir:
     *
     * - falló la llamada al proveedor;
     * - OpenAI respondió bien pero falló algo
     *   posterior, como persistencia.
     *
     * Si OpenAI respondió, el consumo ya ocurrió
     * y no debemos registrar además un falso
     * segundo consumo fallido.
     */
        $providerCallSucceeded = false;

        try {
            $providerResult =
                self::$provider->analyzeEntity(
                    'property',
                    $context,
                    $promptVersion
                );

            $providerCallSucceeded = true;

            $processingTimeMs =
                (int)round(
                    (
                        microtime(true) -
                        $startedAt
                    ) * 1000
                );

            $modelName =
                trim(
                    (string)(
                        $providerResult['model']
                        ?? ''
                    )
                );

            $usage =
                is_array(
                    $providerResult['usage']
                        ?? null
                )
                ? $providerResult['usage']
                : [];

            $tokensUsed =
                max(
                    0,
                    (int)(
                        $providerResult['tokens_used'] ?? 0
                    )
                );

            /*
         * Registramos el consumo inmediatamente
         * después de una respuesta exitosa del
         * proveedor.
         *
         * Así no perdemos el costo si después
         * falla la persistencia del análisis.
         */
            AiUsageService::log([
                'provider' =>
                $providerName,

                'model_name' =>
                $modelName !== ''
                    ? $modelName
                    : null,

                'operation' =>
                'entity_enrichment',

                'entity_type' =>
                'property',

                'entity_id' =>
                $propertyId,

                'real_estate_id' =>
                $realEstateId,

                'user_id' =>
                $userId,

                'input_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['input_tokens'] ?? 0
                    )
                ),

                'cached_input_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['cached_input_tokens'] ?? 0
                    )
                ),

                'output_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['output_tokens'] ?? 0
                    )
                ),

                'total_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['total_tokens'] ?? $tokensUsed
                    )
                ),

                'duration_ms' =>
                $processingTimeMs,

                'status' =>
                'success',
            ]);

            $resultData =
                self::normalizeProviderResult(
                    $providerResult['data'] ?? []
                );

            $analysisId =
                self::saveAnalysis(
                    entityType: 'property',
                    entityId: $propertyId,
                    sourceHash: $sourceHash,
                    result: $resultData,
                    processingTimeMs: $processingTimeMs,
                    tokensUsed: $tokensUsed,
                    modelName: $modelName !== ''
                        ? $modelName
                        : null,
                    promptVersion: $promptVersion
                );

            $saved =
                self::getAnalysisById(
                    $analysisId
                );

            if (!$saved) {
                throw new Exception(
                    'El análisis se guardó, pero no pudo recuperarse'
                );
            }

            return self::formatStoredAnalysis(
                $saved,
                false
            );
        } catch (Throwable $e) {
            /*
         * Sólo registramos "failed" cuando la
         * llamada al proveedor no llegó a responder
         * exitosamente.
         *
         * Si respondió y luego falló MySQL u otra
         * parte interna, el consumo ya quedó
         * registrado arriba como una llamada real.
         */
            if (!$providerCallSucceeded) {
                $durationMs =
                    (int)round(
                        (
                            microtime(true) -
                            $startedAt
                        ) * 1000
                    );

                AiUsageService::log([
                    'provider' =>
                    $providerName,

                    'operation' =>
                    'entity_enrichment',

                    'entity_type' =>
                    'property',

                    'entity_id' =>
                    $propertyId,

                    'real_estate_id' =>
                    $realEstateId,

                    'user_id' =>
                    $userId,

                    'status' =>
                    'failed',

                    'duration_ms' =>
                    $durationMs,

                    'error_message' =>
                    $e->getMessage(),
                ]);
            }

            throw new Exception(
                'No se pudo enriquecer la propiedad: ' .
                    $e->getMessage(),
                0,
                $e
            );
        }
    }

    public static function enrichSearchRequest(
        int $searchRequestId,
        bool $force = false
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido'
            );
        }

        $context =
            self::buildSearchRequestContext(
                $searchRequestId
            );

        $promptVersion =
            AiPromptService::SEARCH_REQUEST_PROMPT_VERSION;

        $sourceHash =
            self::calculateSourceHash(
                $context,
                $promptVersion
            );

        if (!$force) {
            $current =
                self::getCurrentAnalysis(
                    'search_request',
                    $searchRequestId
                );

            if (
                $current &&
                hash_equals(
                    (string)$current['source_hash'],
                    $sourceHash
                ) &&
                (string)(
                    $current['prompt_version'] ?? ''
                ) === $promptVersion &&
                (string)(
                    $current['extraction_version'] ?? ''
                ) === self::EXTRACTION_VERSION
            ) {
                return self::formatStoredAnalysis(
                    $current,
                    true
                );
            }
        }

        if (!self::$provider) {
            throw new Exception(
                'No se configuró un proveedor de inteligencia artificial'
            );
        }

        /*
     * buildSearchRequestContext() ya trae
     * real_estate_id y created_by_user_id.
     */
        $realEstateId =
            isset(
                $context['search_request']['real_estate_id']
            )
            ? (int)$context['search_request']['real_estate_id']
            : null;

        $userId =
            isset(
                $context['search_request']['created_by_user_id']
            )
            ? (int)$context['search_request']['created_by_user_id']
            : null;

        $providerName =
            self::$provider instanceof OpenAIProvider
            ? 'openai'
            : 'unknown';

        $startedAt =
            microtime(true);

        $providerCallSucceeded =
            false;

        try {
            $providerResult =
                self::$provider->analyzeEntity(
                    'search_request',
                    $context,
                    $promptVersion
                );

            $providerCallSucceeded =
                true;

            $processingTimeMs =
                (int)round(
                    (
                        microtime(true) -
                        $startedAt
                    ) * 1000
                );

            $modelName =
                trim(
                    (string)(
                        $providerResult['model']
                        ?? ''
                    )
                );

            $usage =
                is_array(
                    $providerResult['usage']
                        ?? null
                )
                ? $providerResult['usage']
                : [];

            $tokensUsed =
                max(
                    0,
                    (int)(
                        $providerResult['tokens_used'] ?? 0
                    )
                );

            /*
         * Registramos el consumo apenas el
         * proveedor respondió correctamente.
         */
            AiUsageService::log([
                'provider' =>
                $providerName,

                'model_name' =>
                $modelName !== ''
                    ? $modelName
                    : null,

                'operation' =>
                'entity_enrichment',

                'entity_type' =>
                'search_request',

                'entity_id' =>
                $searchRequestId,

                'real_estate_id' =>
                $realEstateId,

                'user_id' =>
                $userId,

                'input_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['input_tokens'] ?? 0
                    )
                ),

                'cached_input_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['cached_input_tokens'] ?? 0
                    )
                ),

                'output_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['output_tokens'] ?? 0
                    )
                ),

                'total_tokens' =>
                max(
                    0,
                    (int)(
                        $usage['total_tokens'] ?? $tokensUsed
                    )
                ),

                'duration_ms' =>
                $processingTimeMs,

                'status' =>
                'success',
            ]);

            $resultData =
                self::normalizeProviderResult(
                    $providerResult['data']
                        ?? []
                );

            $resultData =
                self::normalizeSearchRequestResult(
                    $resultData,
                    $context
                );

            $analysisId =
                self::saveAnalysis(
                    entityType: 'search_request',
                    entityId: $searchRequestId,
                    sourceHash: $sourceHash,
                    result: $resultData,
                    processingTimeMs: $processingTimeMs,
                    tokensUsed: $tokensUsed,
                    modelName: $modelName !== ''
                        ? $modelName
                        : null,
                    promptVersion: $promptVersion
                );

            $saved =
                self::getAnalysisById(
                    $analysisId
                );

            if (!$saved) {
                throw new Exception(
                    'El análisis se guardó, pero no pudo recuperarse'
                );
            }

            return self::formatStoredAnalysis(
                $saved,
                false
            );
        } catch (Throwable $e) {
            if (!$providerCallSucceeded) {
                $durationMs =
                    (int)round(
                        (
                            microtime(true) -
                            $startedAt
                        ) * 1000
                    );

                AiUsageService::log([
                    'provider' =>
                    $providerName,

                    'operation' =>
                    'entity_enrichment',

                    'entity_type' =>
                    'search_request',

                    'entity_id' =>
                    $searchRequestId,

                    'real_estate_id' =>
                    $realEstateId,

                    'user_id' =>
                    $userId,

                    'status' =>
                    'failed',

                    'duration_ms' =>
                    $durationMs,

                    'error_message' =>
                    $e->getMessage(),
                ]);
            }

            throw new Exception(
                'No se pudo enriquecer la búsqueda: ' .
                    $e->getMessage(),
                0,
                $e
            );
        }
    }

    public static function getPropertyEnrichment(
        int $propertyId
    ): ?array {
        $analysis = self::getCurrentAnalysis(
            'property',
            $propertyId
        );

        return $analysis
            ? self::formatStoredAnalysis($analysis, true)
            : null;
    }
    public static function getSearchRequestEnrichment(
        int $searchRequestId
    ): ?array {
        $analysis = self::getCurrentAnalysis(
            'search_request',
            $searchRequestId
        );

        return $analysis
            ? self::formatStoredAnalysis($analysis, true)
            : null;
    }
    /**
     * Construye todo el contexto relevante para analizar una propiedad.
     */
    private static function buildPropertyContext(
        int $propertyId
    ): array {
        $pdo = self::db();
        $stProperty = $pdo->prepare("
            SELECT
                id,
                real_estate_id,
                created_by_user_id,
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
                antiquity,
                status,
                is_visible,
                created_at,
                updated_at
            FROM properties
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stProperty->execute([
            'id' => $propertyId,
        ]);

        $property = $stProperty->fetch(PDO::FETCH_ASSOC);

        if (!$property) {
            throw new Exception('Propiedad no encontrada');
        }

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

        $amenities = $stAmenities->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];

        $stRequirements = $pdo->prepare("
            SELECT *
            FROM property_requirements
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stRequirements->execute([
            'property_id' => $propertyId,
        ]);

        $requirements = $stRequirements->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

        $requirementPropertyTypes = [];
        $requirementLocations = [];

        if ($requirements && !empty($requirements['id'])) {
            $requirementId = (int)$requirements['id'];

            $stTypes = $pdo->prepare("
                SELECT property_type
                FROM property_requirement_property_types
                WHERE property_requirement_id = :requirement_id
                ORDER BY property_type ASC
            ");

            $stTypes->execute([
                'requirement_id' => $requirementId,
            ]);

            $requirementPropertyTypes = $stTypes->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

            $stLocations = $pdo->prepare("
                SELECT
                    country_code,
                    country,
                    province,
                    city,
                    zone
                FROM property_requirement_locations
                WHERE property_requirement_id = :requirement_id
                ORDER BY
                    country ASC,
                    province ASC,
                    city ASC,
                    zone ASC
            ");

            $stLocations->execute([
                'requirement_id' => $requirementId,
            ]);

            $requirementLocations = $stLocations->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
        }

        $stImages = $pdo->prepare("
            SELECT
                id,
                sort_order,
                is_cover
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");

        $stImages->execute([
            'property_id' => $propertyId,
        ]);

        $images = $stImages->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        return self::normalizeForHash([
            'entity_type' => 'property',

            'property' => $property,

            'amenities' => array_values(
                array_unique($amenities)
            ),

            'requirements' => $requirements,

            'requirement_property_types' =>
            array_values(
                array_unique($requirementPropertyTypes)
            ),

            'requirement_locations' =>
            $requirementLocations,

            'media' => [
                'images_count' => count($images),
                'has_cover' => self::hasCoverImage($images),
            ],
        ]);
    }
    private static function buildSearchRequestContext(
        int $searchRequestId
    ): array {
        $pdo = self::db();

        $stRequest = $pdo->prepare("
        SELECT
            id,
            real_estate_id,
            created_by_user_id,
            title,
            description,
            country_code,
            country,
            province,
            city,
            zone,
            property_condition,
            currency,
            min_value,
            max_value,
            min_total_area,
            min_covered_area,
            min_bedrooms,
            min_bathrooms,
            min_garages,
            max_antiquity,
            urgency,
            payment_mode_cash,
            payment_mode_swap,
            cash_difference_max,
            cash_difference_currency,
            open_to_other_zones,
            notes,
            status,
            is_visible,
            published_at,
            expires_at,
            created_at,
            updated_at
        FROM search_requests
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stRequest->execute([
            'id' => $searchRequestId,
        ]);

        $request = $stRequest->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Búsqueda no encontrada');
        }

        /*
     * Tipos de propiedad buscados.
     * Esta tabla no tiene deleted_at.
     */
        $stPropertyTypes = $pdo->prepare("
        SELECT property_type
        FROM search_request_property_types
        WHERE search_request_id = :search_request_id
        ORDER BY property_type ASC
    ");

        $stPropertyTypes->execute([
            'search_request_id' => $searchRequestId,
        ]);

        $propertyTypes = $stPropertyTypes->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];

        /*
     * Amenities deseadas.
     */
        $stAmenities = $pdo->prepare("
        SELECT amenity_code
        FROM search_request_amenities
        WHERE search_request_id = :search_request_id
          AND deleted_at IS NULL
        ORDER BY amenity_code ASC
    ");

        $stAmenities->execute([
            'search_request_id' => $searchRequestId,
        ]);

        $amenities = $stAmenities->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];

        /*
     * Inmuebles ofrecidos en permuta.
     */
        $stExchangeOffers = $pdo->prepare("
        SELECT
            id,
            search_request_id,
            title,
            description,
            property_type,
            estimated_price,
            currency,
            country_code,
            country,
            province,
            city,
            zone,
            total_area,
            covered_area,
            bedrooms,
            bathrooms,
            garages,
            antiquity,
            created_at,
            updated_at
        FROM search_request_exchange_offers
        WHERE search_request_id = :search_request_id
          AND deleted_at IS NULL
        ORDER BY id ASC
    ");

        $stExchangeOffers->execute([
            'search_request_id' => $searchRequestId,
        ]);

        $exchangeOffers = $stExchangeOffers->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        /*
     * Sumamos información de imágenes sin enviar file_path.
     * Para la IA alcanza con saber cantidad y si existe portada.
     */
        foreach ($exchangeOffers as &$offer) {
            $exchangeOfferId = (int)$offer['id'];

            $stImages = $pdo->prepare("
            SELECT
                id,
                sort_order,
                is_cover
            FROM search_request_exchange_offer_images
            WHERE exchange_offer_id = :exchange_offer_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");

            $stImages->execute([
                'exchange_offer_id' => $exchangeOfferId,
            ]);

            $offerImages = $stImages->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

            $offer['media'] = [
                'images_count' => count($offerImages),
                'has_cover' => self::hasCoverImage($offerImages),
            ];
        }

        unset($offer);

        return self::normalizeForHash([
            'entity_type' => 'search_request',

            'search_request' => $request,

            'desired_property_types' => array_values(
                array_unique($propertyTypes)
            ),

            'desired_amenities' => array_values(
                array_unique($amenities)
            ),

            'desired_location' => [
                'country_code' =>
                $request['country_code'] ?? null,

                'country' =>
                $request['country'] ?? null,

                'province' =>
                $request['province'] ?? null,

                'city' =>
                $request['city'] ?? null,

                'zone' =>
                $request['zone'] ?? null,

                'open_to_other_zones' =>
                (int)($request['open_to_other_zones'] ?? 0) === 1,
            ],

            'budget' => [
                'currency' =>
                $request['currency'] ?? null,

                'min_value' =>
                $request['min_value'] ?? null,

                'max_value' =>
                $request['max_value'] ?? null,

                'cash_difference_max' =>
                $request['cash_difference_max'] ?? null,

                'cash_difference_currency' =>
                $request['cash_difference_currency'] ?? null,
            ],

            'payment_modes' => [
                'cash' =>
                (int)($request['payment_mode_cash'] ?? 0) === 1,

                'swap' =>
                (int)($request['payment_mode_swap'] ?? 0) === 1,
            ],

            'exchange_offers' => $exchangeOffers,
        ]);
    }
    /**
     * Genera un hash determinístico del contenido.
     */
    private static function calculateSourceHash(
        array $context,
        string $promptVersion
    ): string {
        $hashPayload = [
            'context' => $context,
            'prompt_version' => $promptVersion,
            'extraction_version' => self::EXTRACTION_VERSION,
        ];

        $json = json_encode(
            self::normalizeForHash($hashPayload),
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION |
                JSON_THROW_ON_ERROR
        );

        return hash('sha256', $json);
    }

    /**
     * Devuelve el análisis vigente más reciente.
     */
    private static function getCurrentAnalysis(
        string $entityType,
        int $entityId
    ): ?array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM ai_entity_enrichments
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
              AND is_current = 1
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $st->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Guarda un nuevo análisis y marca los anteriores como históricos.
     */
    private static function saveAnalysis(
        string $entityType,
        int $entityId,
        string $sourceHash,
        array $result,
        int $processingTimeMs,
        int $tokensUsed,
        ?string $modelName,
        string $promptVersion
    ): int {
        // La llamada a OpenAI pudo demorar y cerrar la conexión anterior.
        $pdo = self::db(true);

        $pdo->beginTransaction();

        try {
            $encodedResult = json_encode(
                $result,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
            );

            $semanticSummary =
                ($result['summary'] ?? '') !== ''
                ? $result['summary']
                : null;

            $confidence = self::confidenceToDatabase(
                (float)($result['confidence'] ?? 0)
            );

            /*
         * Primero desactivamos cualquier análisis vigente
         * de esta misma entidad.
         */
            $stDeactivate = $pdo->prepare("
            UPDATE ai_entity_enrichments
            SET
                is_current = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
              AND is_current = 1
              AND deleted_at IS NULL
        ");

            $stDeactivate->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            /*
         * Revisamos si este mismo contenido ya fue analizado.
         */
            $stExisting = $pdo->prepare("
            SELECT id
            FROM ai_entity_enrichments
            WHERE entity_type = :entity_type
              AND entity_id = :entity_id
              AND source_hash = :source_hash
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

            $stExisting->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'source_hash' => $sourceHash,
            ]);

            $existingId = (int)($stExisting->fetchColumn() ?: 0);

            if ($existingId > 0) {
                /*
             * Si ya existe, actualizamos el análisis en vez
             * de intentar insertar una fila duplicada.
             */
                $stUpdate = $pdo->prepare("
                UPDATE ai_entity_enrichments
                SET
                    extracted_data_json = :extracted_data_json,
                    semantic_summary = :semantic_summary,
                    confidence = :confidence,
                    model_name = :model_name,
                    extraction_version = :extraction_version,
                    prompt_version = :prompt_version,
                    status = 'processed',
                    is_current = 1,
                    processed_at = NOW(),
                    processing_time_ms = :processing_time_ms,
                    tokens_used = :tokens_used,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                LIMIT 1
            ");

                $stUpdate->execute([
                    'extracted_data_json' => $encodedResult,
                    'semantic_summary' => $semanticSummary,
                    'confidence' => $confidence,
                    'model_name' => $modelName,
                    'extraction_version' => self::EXTRACTION_VERSION,
                    'prompt_version' => $promptVersion,
                    'processing_time_ms' => $processingTimeMs,
                    'tokens_used' => $tokensUsed,
                    'id' => $existingId,
                ]);

                $analysisId = $existingId;
            } else {
                /*
             * Si el contenido cambió, insertamos un nuevo análisis.
             */
                $stInsert = $pdo->prepare("
                INSERT INTO ai_entity_enrichments (
                    entity_type,
                    entity_id,
                    source_hash,
                    extracted_data_json,
                    semantic_summary,
                    confidence,
                    model_name,
                    extraction_version,
                    prompt_version,
                    status,
                    is_current,
                    processed_at,
                    processing_time_ms,
                    tokens_used
                ) VALUES (
                    :entity_type,
                    :entity_id,
                    :source_hash,
                    :extracted_data_json,
                    :semantic_summary,
                    :confidence,
                    :model_name,
                    :extraction_version,
                    :prompt_version,
                    'processed',
                    1,
                    NOW(),
                    :processing_time_ms,
                    :tokens_used
                )
            ");

                $stInsert->execute([
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'source_hash' => $sourceHash,
                    'extracted_data_json' => $encodedResult,
                    'semantic_summary' => $semanticSummary,
                    'confidence' => $confidence,
                    'model_name' => $modelName,
                    'extraction_version' => self::EXTRACTION_VERSION,
                    'prompt_version' => $promptVersion,
                    'processing_time_ms' => $processingTimeMs,
                    'tokens_used' => $tokensUsed,
                ]);

                $analysisId = (int)$pdo->lastInsertId();
            }

            $pdo->commit();

            return $analysisId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function getAnalysisById(
        int $analysisId
    ): ?array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM ai_entity_enrichments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $analysisId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Asegura que el proveedor entregue siempre el mismo contrato.
     */
    private static function normalizeProviderResult(
        array $result
    ): array {
        $summary = trim(
            (string)($result['summary'] ?? '')
        );

        $tags = self::normalizeStringList(
            $result['tags'] ?? []
        );

        $entities = is_array(
            $result['entities'] ?? null
        )
            ? $result['entities']
            : [];

        $intent = is_array(
            $result['intent'] ?? null
        )
            ? $result['intent']
            : [];

        $contradictions = self::normalizeArrayList(
            $result['contradictions'] ?? []
        );

        $missingInformation = self::normalizeStringList(
            $result['missing_information'] ?? []
        );

        $warnings = self::normalizeArrayList(
            $result['warnings'] ?? []
        );

        /*
     * El schema devuelve objetos con:
     * question, field, reason y priority.
     */
        $smartQuestions = self::normalizeArrayList(
            $result['smart_questions'] ?? []
        );
        $workflowSuggestions = self::normalizeArrayList(
            $result['workflow_suggestions'] ?? []
        );
        $publicationAnalysis = is_array(
            $result['publication_analysis'] ?? null
        )
            ? $result['publication_analysis']
            : [
                'information_score' => 0,
                'commercial_score' => 0,
                'matchability_score' => 0,
                'overall_score' => 0,
                'strengths' => [],
                'recommendations' => [],
            ];

        $confidence = self::normalizeConfidence(
            $result['confidence'] ?? 0
        );

        return [
            'summary' => $summary,
            'tags' => $tags,
            'entities' => $entities,
            'intent' => $intent,
            'contradictions' => $contradictions,
            'missing_information' => $missingInformation,
            'warnings' => $warnings,
            'smart_questions' => $smartQuestions,
            'workflow_suggestions' => $workflowSuggestions,
            'publication_analysis' => $publicationAnalysis,
            'confidence' => $confidence,
        ];
    }

    private static function normalizeSearchRequestResult(
        array $result,
        array $context
    ): array {
        $exchangeOffers = is_array(
            $context['exchange_offers'] ?? null
        )
            ? $context['exchange_offers']
            : [];

        $hasExchangeOffer = count($exchangeOffers) > 0;
        if (
            isset($result['entities']) &&
            is_array($result['entities'])
        ) {
            $result['entities']['budget_flexibility'] = [
                'value' => null,
                'confidence' => 0,
                'mode' => 'unknown',
                'source' => null,
                'evidence' => null,
            ];
        }
        /*
     * Si ya hay una exchange_offer, no corresponde sugerir
     * crearla nuevamente.
     *
     * Tampoco corresponde vincular una property existente,
     * porque no tenemos evidencia de que exista una fila
     * relacionada en la tabla properties.
     */
        if ($hasExchangeOffer) {
            $workflowSuggestions = is_array(
                $result['workflow_suggestions'] ?? null
            )
                ? $result['workflow_suggestions']
                : [];

            $workflowSuggestions = array_values(
                array_filter(
                    $workflowSuggestions,
                    static function ($suggestion): bool {
                        if (!is_array($suggestion)) {
                            return false;
                        }

                        $type = (string)(
                            $suggestion['type'] ?? ''
                        );

                        return !in_array(
                            $type,
                            [
                                'create_exchange_offer',
                                'link_existing_property',
                            ],
                            true
                        );
                    }
                )
            );

            /*
         * Si la oferta existe pero está incompleta, aseguramos
         * una acción específica para completarla.
         */
            $firstOffer = $exchangeOffers[0] ?? [];

            $media = is_array($firstOffer['media'] ?? null)
                ? $firstOffer['media']
                : [];

            $imagesCount = (int)(
                $media['images_count'] ?? 0
            );

            $hasCover = (bool)(
                $media['has_cover'] ?? false
            );

            $offerNeedsCompletion =
                empty($firstOffer['property_type']) ||
                empty($firstOffer['estimated_price']) ||
                empty($firstOffer['city']) ||
                $imagesCount === 0 ||
                !$hasCover;

            if ($offerNeedsCompletion) {
                $alreadyHasCompleteSuggestion = false;

                foreach ($workflowSuggestions as $suggestion) {
                    if (
                        is_array($suggestion) &&
                        ($suggestion['type'] ?? null) ===
                        'complete_exchange_offer'
                    ) {
                        $alreadyHasCompleteSuggestion = true;
                        break;
                    }
                }

                if (!$alreadyHasCompleteSuggestion) {
                    $workflowSuggestions[] = [
                        'type' => 'complete_exchange_offer',
                        'priority' => 'high',
                        'reason' =>
                        'El inmueble ofrecido en permuta ya está cargado, pero su ficha todavía está incompleta.',
                        'related_field' => 'exchange_offers[0]',
                        'action_label' =>
                        'Completar inmueble ofrecido',
                    ];
                }
            }

            foreach ($workflowSuggestions as &$suggestion) {
                if (!is_array($suggestion)) {
                    continue;
                }

                if (
                    isset($suggestion['reason']) &&
                    is_string($suggestion['reason'])
                ) {
                    $suggestion['reason'] = preg_replace(
                        [
                            '/,?\s*y falta confirmación de publicación en PermuOK/iu',
                            '/,?\s*falta confirmación de publicación en PermuOK/iu',
                            '/,?\s*confirmación de publicación en PermuOK/iu',
                            '/,?\s*confirmación de publicación/iu',
                            '/,?\s*confirmar si ya está publicada en PermuOK/iu',
                        ],
                        '',
                        $suggestion['reason']
                    );

                    $suggestion['reason'] = trim(
                        preg_replace(
                            '/\s+/',
                            ' ',
                            $suggestion['reason']
                        )
                    );

                    $suggestion['reason'] = trim(
                        $suggestion['reason'],
                        " \t\n\r\0\x0B,;"
                    );
                }
            }

            unset($suggestion);

            $result['workflow_suggestions'] =
                $workflowSuggestions;
        }

        /*
     * En este flujo la persona entrega una propiedad para
     * adquirir la buscada. No corresponde preguntarle de manera
     * automática si acepta recibir un inmueble de menor valor.
     */
        $smartQuestions = is_array(
            $result['smart_questions'] ?? null
        )
            ? $result['smart_questions']
            : [];

        $result['smart_questions'] = array_values(
            array_filter(
                $smartQuestions,
                static function ($question): bool {
                    if (!is_array($question)) {
                        return false;
                    }

                    $field = strtolower(
                        trim((string)($question['field'] ?? ''))
                    );

                    $text = strtolower(
                        trim((string)($question['question'] ?? ''))
                    );

                    if (
                        $field === 'accepts_lower_value' ||
                        strpos($text, 'recibir un inmueble de menor valor') !== false ||
                        strpos($text, 'recibir una propiedad de menor valor') !== false ||
                        strpos($text, 'ya está publicada en permuok') !== false ||
                        strpos($text, 'ya está publicado en permuok') !== false ||
                        strpos($text, 'está publicado en permuok') !== false ||
                        strpos($text, 'comparta el enlace') !== false ||
                        strpos($text, 'comparta el id') !== false ||
                        strpos($text, 'linkear') !== false ||
                        strpos($text, 'puede linkearse') !== false ||
                        strpos($text, 'vincularla') !== false ||
                        strpos($text, 'vincularlo') !== false ||
                        strpos($field, 'legal_status') !== false ||
                        strpos($text, 'estado registral') !== false ||
                        strpos($text, 'libre de deudas') !== false ||
                        strpos($text, 'pose de escritura') !== false ||
                        strpos($text, 'desea vincularlo') !== false

                    ) {
                        return false;
                    }

                    return true;
                }
            )
        );

        /*
     * También lo retiramos de información faltante, porque no
     * necesariamente es un dato aplicable a esta dirección
     * concreta de la permuta.
     */
        $missingInformation = is_array(
            $result['missing_information'] ?? null
        )
            ? $result['missing_information']
            : [];

        $result['missing_information'] = array_values(
            array_filter(
                $missingInformation,
                static function ($item): bool {
                    $text = strtolower(
                        trim((string)$item)
                    );

                    return
                        strpos($text, 'accepts_lower_value') === false &&
                        strpos($text, 'properties_of_lower_value') === false &&
                        strpos($text, 'propiedades de menor valor') === false &&
                        strpos($text, 'recibir inmueble de menor valor') === false &&
                        strpos($text, 'publicado en permuok') === false &&
                        strpos($text, 'publicada en permuok') === false &&
                        strpos($text, 'recibir una propiedad de menor valor') === false;
                }
            )
        );
        /*
 * En búsquedas, los mínimos iguales a cero representan
 * ausencia de requisito y no una contradicción.
 */
        $contradictions = is_array(
            $result['contradictions'] ?? null
        )
            ? $result['contradictions']
            : [];

        $result['contradictions'] = array_values(
            array_filter(
                $contradictions,
                static function ($contradiction): bool {
                    if (!is_array($contradiction)) {
                        return false;
                    }

                    $field = strtolower(
                        trim((string)($contradiction['field'] ?? ''))
                    );

                    $structuredValue = trim(
                        (string)($contradiction['structured_value'] ?? '')
                    );

                    $minimumFields = [
                        'min_total_area',
                        'min_covered_area',
                        'min_bedrooms',
                        'min_bathrooms',
                        'min_garages',
                    ];

                    if (
                        in_array($field, $minimumFields, true) &&
                        in_array(
                            $structuredValue,
                            ['0', '0.0', '0.00'],
                            true
                        )
                    ) {
                        return false;
                    }
                    if (
                        $field === 'property_condition' &&
                        strpos(
                            strtolower(
                                (string)($contradiction['evidence'] ?? '')
                            ),
                            'exchange_offers'
                        ) !== false
                    ) {
                        return false;
                    }
                    return true;
                }
            )
        );
        /*
     * Evitamos advertencias genéricas por el solo hecho de que
     * el presupuesto esté expresado en USD.
     */
        $warnings = is_array(
            $result['warnings'] ?? null
        )
            ? $result['warnings']
            : [];

        $result['warnings'] = array_values(
            array_filter(
                $warnings,
                static function ($warning): bool {
                    if (!is_array($warning)) {
                        return false;
                    }

                    $code = strtolower(
                        trim((string)($warning['code'] ?? ''))
                    );

                    return !in_array(
                        $code,
                        [
                            'price_currency_check',
                            'price_range_note',
                            'price_currency_mismatch_check',
                            'price_range_unclarified_currency_usage',
                            'price_range_raw',
                            'budget_range_broad',
                            'price_range_as_string',
                        ],
                        true
                    );
                }
            )
        );

        /*
 * Limpiamos recomendaciones que no aplican automáticamente
 * a esta dirección de la permuta.
 */
        $publicationAnalysis = is_array(
            $result['publication_analysis'] ?? null
        )
            ? $result['publication_analysis']
            : [];

        $recommendations = is_array(
            $publicationAnalysis['recommendations'] ?? null
        )
            ? $publicationAnalysis['recommendations']
            : [];

        $publicationAnalysis['recommendations'] = array_values(
            array_filter(
                $recommendations,
                static function ($recommendation): bool {
                    $text = strtolower(
                        trim((string)$recommendation)
                    );

                    return
                        strpos($text, 'acepta recibir inmueble de menor valor') === false &&
                        strpos($text, 'acepta recibir una propiedad de menor valor') === false &&
                        strpos($text, 'recibir inmueble de menor valor') === false &&
                        strpos($text, 'recibir una propiedad de menor valor') === false &&
                        strpos($text, 'propiedades de menor valor') === false;
                }
            )
        );

        $result['publication_analysis'] = $publicationAnalysis;
        /*
 * Eliminamos del resumen referencias a recibir una propiedad
 * de menor valor, porque no corresponde automáticamente
 * para esta dirección de la permuta.
 */
        if (
            isset($result['summary']) &&
            is_string($result['summary'])
        ) {
            $result['summary'] = preg_replace(
                [
                    '/Falta confirmar si acepta recibir inmueble de menor valor[^.]*\.?/iu',
                    '/Falta confirmar si acepta recibir una propiedad de menor valor[^.]*\.?/iu',
                    '/Falta confirmar si acepta una propiedad de menor valor[^.]*\.?/iu',
                    '/,?\s*y no indica legalidad/iu',
                    '/,?\s*no indica legalidad/iu',
                    '/,?\s*y falta estado legal/iu',
                    '/,?\s*falta estado legal/iu',
                ],
                '',
                $result['summary']
            );

            $result['summary'] = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $result['summary']
                )
            );
        }
        return $result;
    }
    /**
     * Convierte un registro almacenado al formato usado por la aplicación.
     */
    private static function formatStoredAnalysis(
        array $row,
        bool $fromCache
    ): array {
        $data = json_decode(
            (string)($row['extracted_data_json'] ?? '{}'),
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        return [
            'id' => (int)$row['id'],
            'entity_type' => (string)$row['entity_type'],
            'entity_id' => (int)$row['entity_id'],
            'source_hash' => (string)$row['source_hash'],
            'status' => (string)$row['status'],
            'is_current' =>
            (int)($row['is_current'] ?? 0) === 1,

            'data' => $data,

            'semantic_summary' =>
            $row['semantic_summary'] ?? null,

            'confidence' =>
            self::confidenceFromDatabase(
                $row['confidence'] ?? null
            ),

            'model_name' =>
            $row['model_name'] ?? null,

            'extraction_version' =>
            $row['extraction_version'] ?? null,

            'prompt_version' =>
            $row['prompt_version'] ?? null,

            'processing_time_ms' =>
            isset($row['processing_time_ms'])
                ? (int)$row['processing_time_ms']
                : null,

            'tokens_used' =>
            isset($row['tokens_used'])
                ? (int)$row['tokens_used']
                : null,

            'processed_at' =>
            $row['processed_at'] ?? null,

            'from_cache' => $fromCache,
        ];
    }

    private static function normalizeStringList(
        mixed $value
    ): array {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $normalized = trim((string)$item);

            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        return array_values(array_unique($items));
    }

    private static function normalizeArrayList(
        mixed $value
    ): array {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                $value,
                static fn($item) =>
                is_array($item) ||
                    (
                        is_scalar($item) &&
                        trim((string)$item) !== ''
                    )
            )
        );
    }

    private static function normalizeConfidence(
        mixed $value
    ): float {
        $confidence = (float)$value;

        if ($confidence > 1 && $confidence <= 100) {
            $confidence /= 100;
        }

        return max(
            0,
            min(1, round($confidence, 4))
        );
    }

    /**
     * La columna DECIMAL(5,2) puede almacenar 0–100.
     */
    private static function confidenceToDatabase(
        float $confidence
    ): float {
        return round($confidence * 100, 2);
    }

    private static function confidenceFromDatabase(
        mixed $confidence
    ): ?float {
        if ($confidence === null || $confidence === '') {
            return null;
        }

        return round(
            ((float)$confidence) / 100,
            4
        );
    }

    private static function hasCoverImage(
        array $images
    ): bool {
        foreach ($images as $image) {
            if ((int)($image['is_cover'] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function isListArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
    /**
     * Ordena recursivamente las claves para que el hash no cambie
     * por diferencias irrelevantes en el orden.
     */
    private static function normalizeForHash(
        mixed $value
    ): mixed {
        if (!is_array($value)) {
            return $value;
        }

        if (self::isListArray($value)) {
            return array_map(
                [self::class, 'normalizeForHash'],
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::normalizeForHash($item);
        }

        return $value;
    }

    private static function db(bool $forceReconnect = false): PDO
    {
        require_once __DIR__ . '/../../../db.php';
        return pdo($forceReconnect);
    }
}
