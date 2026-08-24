<?php

namespace App\Services\AI;

use PDO;
use Exception;
use Throwable;
use App\Services\CurrencyConversionService;

class CompatibilityEngine
{
    private const MIN_SCORE_TO_SAVE = 25.0;
    private const BATCH_SIZE = 500;
    private const MIN_SCORE_TO_NOTIFY = 75.0;
    /**
     * Recalcula las compatibilidades de una búsqueda
     * contra propiedades publicadas de otras inmobiliarias.
     */
    public static function calculateForSearchRequest(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido'
            );
        }

        $pdo = self::db();

        $searchRequest = self::getSearchRequest(
            $pdo,
            $searchRequestId
        );

        $propertyTypes =
            self::getSearchRequestPropertyTypes(
                $pdo,
                $searchRequestId
            );

        $desiredAmenities =
            self::getSearchRequestAmenities(
                $pdo,
                $searchRequestId
            );

        $exchangeOffers =
            self::getSearchRequestExchangeOffers(
                $pdo,
                $searchRequestId
            );

        $results = [];
        $activePropertyIds = [];
        $candidatesEvaluated = 0;
        $lastPropertyId = 0;

        while (true) {
            $properties =
                self::getCandidateProperties(
                    $pdo,
                    (int)$searchRequest['real_estate_id'],
                    $propertyTypes,
                    $lastPropertyId,
                    self::BATCH_SIZE
                );

            if ($properties === []) {
                break;
            }

            $propertyIds = array_map(
                static fn(array $property): int =>
                (int)$property['id'],
                $properties
            );

            $amenitiesByProperty =
                self::getPropertyAmenitiesBatch(
                    $pdo,
                    $propertyIds
                );

            $requirementsByProperty =
                self::getPropertyRequirementsBatch(
                    $pdo,
                    $propertyIds
                );

            $pdo->beginTransaction();

            try {
                foreach ($properties as $property) {
                    $propertyId =
                        (int)$property['id'];

                    $lastPropertyId = max(
                        $lastPropertyId,
                        $propertyId
                    );

                    $candidatesEvaluated++;

                    $evaluation =
                        self::evaluatePropertyAgainstSearch(
                            $searchRequest,
                            $propertyTypes,
                            $desiredAmenities,
                            $exchangeOffers,
                            $property,
                            $amenitiesByProperty[$propertyId] ?? [],
                            $requirementsByProperty[$propertyId] ?? null
                        );

                    if (
                        $evaluation['discarded'] ||
                        $evaluation['score'] <
                        self::MIN_SCORE_TO_SAVE
                    ) {
                        continue;
                    }

                    $compatibilityId =
                        self::saveCompatibility(
                            $pdo,
                            $searchRequest,
                            $property,
                            $evaluation
                        );

                    $activePropertyIds[] =
                        $propertyId;

                    $results[] = [
                        'compatibility_id' =>
                        $compatibilityId,

                        'property_id' =>
                        $propertyId,

                        'search_request_id' =>
                        $searchRequestId,

                        'score' =>
                        $evaluation['score'],

                        'match_level' =>
                        $evaluation['match_level'],

                        'reasons' =>
                        $evaluation['reasons'],

                        'penalties' =>
                        $evaluation['penalties'],
                    ];
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }

            if (
                count($properties) <
                self::BATCH_SIZE
            ) {
                break;
            }
        }

        $pdo->beginTransaction();

        try {
            $archivedCount =
                self::archiveStaleCompatibilities(
                    $pdo,
                    $searchRequestId,
                    $activePropertyIds
                );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        usort(
            $results,
            static fn(array $a, array $b): int =>
            $b['score'] <=> $a['score']
        );

        return [
            'search_request_id' =>
            $searchRequestId,

            'candidates_evaluated' =>
            $candidatesEvaluated,

            'compatibilities_saved' =>
            count($results),

            'compatibilities_archived' =>
            $archivedCount,

            'results' =>
            $results,
        ];
    }

    private static function getPropertyAmenitiesBatch(
        PDO $pdo,
        array $propertyIds
    ): array {
        $map = [];

        foreach (
            self::chunkIds($propertyIds)
            as $chunk
        ) {
            $bindings =
                self::buildIdPlaceholders(
                    $chunk,
                    'property_id'
                );

            $st = $pdo->prepare("
            SELECT
                property_id,
                amenity_code
            FROM property_amenities
            WHERE property_id IN (
                " .
                implode(
                    ', ',
                    $bindings['placeholders']
                ) .
                "
            )
              AND deleted_at IS NULL
            ORDER BY
                property_id ASC,
                amenity_code ASC
        ");

            $st->execute(
                $bindings['params']
            );

            while (
                $row =
                $st->fetch(PDO::FETCH_ASSOC)
            ) {
                $propertyId =
                    (int)$row['property_id'];

                $map[$propertyId][] =
                    (string)$row['amenity_code'];
            }
        }

        return $map;
    }

    private static function getPropertyRequirementsBatch(
        PDO $pdo,
        array $propertyIds
    ): array {
        $map = [];

        foreach (
            self::chunkIds($propertyIds)
            as $chunk
        ) {
            $bindings =
                self::buildIdPlaceholders(
                    $chunk,
                    'property_id'
                );

            $st = $pdo->prepare("
            SELECT *
            FROM property_requirements
            WHERE property_id IN (
                " .
                implode(
                    ', ',
                    $bindings['placeholders']
                ) .
                "
            )
              AND deleted_at IS NULL
            ORDER BY
                property_id ASC,
                id ASC
        ");

            $st->execute(
                $bindings['params']
            );

            while (
                $row =
                $st->fetch(PDO::FETCH_ASSOC)
            ) {
                $propertyId =
                    (int)$row['property_id'];

                if (
                    !isset(
                        $map[$propertyId]
                    )
                ) {
                    $map[$propertyId] =
                        $row;
                }
            }
        }

        return $map;
    }

    public static function calculateForProperty(
        int $propertyId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido'
            );
        }

        $pdo = self::db();

        $stProperty = $pdo->prepare("
        SELECT *
        FROM properties
        WHERE id = :id
        LIMIT 1
    ");

        $stProperty->execute([
            'id' => $propertyId,
        ]);

        $property = $stProperty->fetch(
            PDO::FETCH_ASSOC
        );

        if (
            !$property ||
            !empty($property['deleted_at']) ||
            $property['status'] !== 'published' ||
            (int)$property['is_visible'] !== 1
        ) {
            return self::archiveForProperty(
                $propertyId
            );
        }

        $propertyAmenities =
            self::getPropertyAmenities(
                $pdo,
                $propertyId
            );

        $propertyRequirements =
            self::getPropertyRequirements(
                $pdo,
                $propertyId
            );

        $evaluated = 0;
        $saved = 0;
        $archived = 0;
        $errors = [];
        $results = [];

        $lastSearchRequestId = 0;

        while (true) {
            $searchRequests =
                self::getCandidateSearchRequestsForProperty(
                    $pdo,
                    $property,
                    $lastSearchRequestId,
                    self::BATCH_SIZE
                );

            if ($searchRequests === []) {
                break;
            }

            $searchRequestIds = array_map(
                static fn(array $searchRequest): int =>
                (int)$searchRequest['id'],
                $searchRequests
            );

            $propertyTypesBySearch =
                self::getSearchRequestPropertyTypesBatch(
                    $pdo,
                    $searchRequestIds
                );

            $amenitiesBySearch =
                self::getSearchRequestAmenitiesBatch(
                    $pdo,
                    $searchRequestIds
                );

            $exchangeOffersBySearch =
                self::getSearchRequestExchangeOffersBatch(
                    $pdo,
                    $searchRequestIds
                );

            foreach ($searchRequests as $searchRequest) {
                $searchRequestId =
                    (int)$searchRequest['id'];

                $lastSearchRequestId = max(
                    $lastSearchRequestId,
                    $searchRequestId
                );

                try {
                    $result =
                        self::calculatePropertySearchPair(
                            $pdo,
                            $property,
                            $propertyAmenities,
                            $propertyRequirements,
                            $searchRequest,
                            $propertyTypesBySearch[$searchRequestId] ?? [],
                            $amenitiesBySearch[$searchRequestId] ?? [],
                            $exchangeOffersBySearch[$searchRequestId] ?? []
                        );

                    $evaluated++;

                    if (
                        ($result['saved'] ?? false)
                        === true
                    ) {
                        $saved++;
                    }

                    if (
                        ($result['archived'] ?? false)
                        === true
                    ) {
                        $archived++;
                    }

                    $results[] = $result;
                } catch (Throwable $e) {
                    $errors[] = [
                        'search_request_id' =>
                        $searchRequestId,

                        'message' =>
                        $e->getMessage(),
                    ];
                }
            }

            if (
                count($searchRequests) <
                self::BATCH_SIZE
            ) {
                break;
            }
        }

        return [
            'property_id' =>
            $propertyId,

            'search_requests_evaluated' =>
            $evaluated,

            'compatibilities_saved' =>
            $saved,

            'compatibilities_archived' =>
            $archived,

            'errors_count' =>
            count($errors),

            'results' =>
            $results,

            'errors' =>
            $errors,
        ];
    }
    private static function convertAmount(
        float $amount,
        string $fromCurrency,
        string $toCurrency
    ): ?float {
        $fromCurrency =
            strtoupper(trim($fromCurrency));

        $toCurrency =
            strtoupper(trim($toCurrency));

        if ($amount < 0) {
            return null;
        }

        if (
            $fromCurrency === '' ||
            $toCurrency === ''
        ) {
            return null;
        }

        if ($fromCurrency === $toCurrency) {
            return round($amount, 2);
        }

        try {
            return CurrencyConversionService::convert(
                $amount,
                $fromCurrency,
                $toCurrency
            );
        } catch (Throwable $e) {
            /*
         * Si no hay cotización disponible,
         * no inventamos ninguna conversión.
         */
            return null;
        }
    }
    private static function getCandidateSearchRequestsForProperty(
        PDO $pdo,
        array $property,
        int $afterId = 0,
        int $limit = self::BATCH_SIZE
    ): array {
        $propertyId =
            (int)$property['id'];

        $realEstateId =
            (int)$property['real_estate_id'];

        $propertyType =
            trim(
                (string)(
                    $property['property_type']
                    ?? ''
                )
            );

        $limit = max(
            1,
            min(
                self::BATCH_SIZE,
                $limit
            )
        );

        $sql = "
        SELECT sr.*

        FROM search_requests sr

        WHERE
            sr.real_estate_id <> :real_estate_id

            AND sr.status = 'published'

            AND sr.is_visible = 1

            AND sr.deleted_at IS NULL

            AND sr.id > :after_id

            AND (
                NOT EXISTS (
                    SELECT 1
                    FROM search_request_property_types srpt_any
                    WHERE
                        srpt_any.search_request_id = sr.id
                )

                OR EXISTS (
                    SELECT 1
                    FROM search_request_property_types srpt_match
                    WHERE
                        srpt_match.search_request_id = sr.id
                        AND srpt_match.property_type = :property_type
                )

                OR EXISTS (
                    SELECT 1
                    FROM compatibilities c
                    WHERE
                        c.compatibility_type =
                            'property_search_request'

                        AND c.property_id =
                            :property_id

                        AND c.search_request_id =
                            sr.id

                        AND c.deleted_at IS NULL
                )
            )

        ORDER BY sr.id ASC

        LIMIT {$limit}
    ";

        $st = $pdo->prepare($sql);

        $st->execute([
            'real_estate_id' =>
            $realEstateId,

            'property_type' =>
            $propertyType,

            'property_id' =>
            $propertyId,

            'after_id' =>
            $afterId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }
    private static function calculatePropertySearchPair(
        PDO $pdo,
        array $property,
        array $propertyAmenities,
        ?array $propertyRequirements,
        array $searchRequest,
        array $propertyTypes,
        array $desiredAmenities,
        array $exchangeOffers
    ): array {
        $propertyId =
            (int)$property['id'];

        $searchRequestId =
            (int)$searchRequest['id'];

        $evaluation =
            self::evaluatePropertyAgainstSearch(
                $searchRequest,
                $propertyTypes,
                $desiredAmenities,
                $exchangeOffers,
                $property,
                $propertyAmenities,
                $propertyRequirements
            );

        if (
            $evaluation['discarded'] ||
            $evaluation['score'] <
            self::MIN_SCORE_TO_SAVE
        ) {
            $archived =
                self::archivePropertySearchPair(
                    $pdo,
                    $propertyId,
                    $searchRequestId
                );

            return [
                'property_id' =>
                $propertyId,

                'search_request_id' =>
                $searchRequestId,

                'score' =>
                $evaluation['score'],

                'match_level' =>
                $evaluation['match_level'],

                'saved' =>
                false,

                'archived' =>
                $archived > 0,

                'reasons' =>
                $evaluation['reasons'],

                'penalties' =>
                $evaluation['penalties'],
            ];
        }

        $compatibilityId =
            self::saveCompatibility(
                $pdo,
                $searchRequest,
                $property,
                $evaluation
            );

        return [
            'compatibility_id' =>
            $compatibilityId,

            'property_id' =>
            $propertyId,

            'search_request_id' =>
            $searchRequestId,

            'score' =>
            $evaluation['score'],

            'match_level' =>
            $evaluation['match_level'],

            'saved' =>
            true,

            'archived' =>
            false,

            'reasons' =>
            $evaluation['reasons'],

            'penalties' =>
            $evaluation['penalties'],
        ];
    }


    private static function archivePropertySearchPair(
        PDO $pdo,
        int $propertyId,
        int $searchRequestId
    ): int {
        /*
     * Solamente archivamos oportunidades
     * que todavía no avanzaron comercialmente.
     */
        $stLost = $pdo->prepare("
        SELECT id
        FROM compatibilities
        WHERE compatibility_type =
            'property_search_request'
          AND property_id =
            :property_id
          AND search_request_id =
            :search_request_id
          AND deleted_at IS NULL
          AND status IN (
              'detected',
              'dismissed'
          )
          AND score >= :min_score
        LIMIT 1
    ");

        $stLost->execute([
            'property_id' =>
            $propertyId,

            'search_request_id' =>
            $searchRequestId,

            'min_score' =>
            self::MIN_SCORE_TO_NOTIFY,
        ]);

        $lostId =
            (int)(
                $stLost->fetchColumn()
                ?: 0
            );

        $st = $pdo->prepare("
        UPDATE compatibilities

        SET
            status = 'archived',
            archived_at = NOW(),
            updated_at = CURRENT_TIMESTAMP

        WHERE compatibility_type =
            'property_search_request'

          AND property_id =
            :property_id

          AND search_request_id =
            :search_request_id

          AND deleted_at IS NULL

          AND status IN (
              'detected',
              'dismissed'
          )
    ");

        $st->execute([
            'property_id' =>
            $propertyId,

            'search_request_id' =>
            $searchRequestId,
        ]);

        $archivedCount =
            $st->rowCount();

        if (
            $archivedCount > 0 &&
            $lostId > 0
        ) {
            self::createDirectLostEvents(
                $pdo,
                [$lostId]
            );
        }

        return $archivedCount;
    }

    public static function archiveForProperty(
        int $propertyId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido'
            );
        }

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            /*
         * Guardamos qué matches notificables
         * estaban activos antes de archivarlos.
         */
            $stIds = $pdo->prepare("
            SELECT id
            FROM compatibilities
            WHERE compatibility_type =
                'property_search_request'
              AND property_id = :property_id
              AND deleted_at IS NULL
              AND status IN (
                  'detected',
                  'dismissed'
              )
              AND score >= :min_score
        ");

            $stIds->execute([
                'property_id' =>
                $propertyId,

                'min_score' =>
                self::MIN_SCORE_TO_NOTIFY,
            ]);

            $lostIds =
                $stIds->fetchAll(
                    PDO::FETCH_COLUMN
                ) ?: [];

            $st = $pdo->prepare("
            UPDATE compatibilities
            SET
                status = 'archived',
                archived_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
            WHERE compatibility_type =
                'property_search_request'
              AND property_id = :property_id
              AND deleted_at IS NULL
              AND status IN (
                  'detected',
                  'dismissed'
              )
        ");

            $st->execute([
                'property_id' =>
                $propertyId,
            ]);

            self::createDirectLostEvents(
                $pdo,
                $lostIds
            );

            $archivedCount =
                $st->rowCount();

            $pdo->commit();

            return [
                'property_id' =>
                $propertyId,

                'compatibilities_archived' =>
                $archivedCount,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function archiveForSearchRequest(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido'
            );
        }

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            $stIds = $pdo->prepare("
            SELECT id
            FROM compatibilities
            WHERE compatibility_type =
                'property_search_request'
              AND search_request_id =
                :search_request_id
              AND deleted_at IS NULL
              AND status IN (
                  'detected',
                  'dismissed'
              )
              AND score >= :min_score
        ");

            $stIds->execute([
                'search_request_id' =>
                $searchRequestId,

                'min_score' =>
                self::MIN_SCORE_TO_NOTIFY,
            ]);

            $lostIds =
                $stIds->fetchAll(
                    PDO::FETCH_COLUMN
                ) ?: [];

            $st = $pdo->prepare("
            UPDATE compatibilities
            SET
                status = 'archived',
                archived_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
            WHERE compatibility_type =
                'property_search_request'
              AND search_request_id =
                :search_request_id
              AND deleted_at IS NULL
              AND status IN (
                  'detected',
                  'dismissed'
              )
        ");

            $st->execute([
                'search_request_id' =>
                $searchRequestId,
            ]);

            self::createDirectLostEvents(
                $pdo,
                $lostIds
            );

            $archivedCount =
                $st->rowCount();

            $pdo->commit();

            return [
                'search_request_id' =>
                $searchRequestId,

                'compatibilities_archived' =>
                $archivedCount,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function getSearchRequest(
        PDO $pdo,
        int $searchRequestId
    ): array {
        $st = $pdo->prepare("
            SELECT *
            FROM search_requests
            WHERE id = :id
              AND status = 'published'
              AND is_visible = 1
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $searchRequestId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception(
                'Búsqueda publicada no encontrada'
            );
        }

        return $row;
    }


    private static function getSearchRequestPropertyTypesBatch(
        PDO $pdo,
        array $searchRequestIds
    ): array {
        $map = [];

        foreach (
            self::chunkIds($searchRequestIds)
            as $chunk
        ) {
            $bindings =
                self::buildIdPlaceholders(
                    $chunk,
                    'search_id'
                );

            $st = $pdo->prepare("
            SELECT
                search_request_id,
                property_type
            FROM search_request_property_types
            WHERE search_request_id IN (
                " .
                implode(
                    ', ',
                    $bindings['placeholders']
                ) .
                "
            )
            ORDER BY
                search_request_id ASC,
                property_type ASC
        ");

            $st->execute(
                $bindings['params']
            );

            while (
                $row =
                $st->fetch(PDO::FETCH_ASSOC)
            ) {
                $searchRequestId =
                    (int)$row['search_request_id'];

                $map[$searchRequestId][] =
                    (string)$row['property_type'];
            }
        }

        return $map;
    }

    private static function getSearchRequestAmenitiesBatch(
        PDO $pdo,
        array $searchRequestIds
    ): array {
        $map = [];

        foreach (
            self::chunkIds($searchRequestIds)
            as $chunk
        ) {
            $bindings =
                self::buildIdPlaceholders(
                    $chunk,
                    'search_id'
                );

            $st = $pdo->prepare("
            SELECT
                search_request_id,
                amenity_code
            FROM search_request_amenities
            WHERE search_request_id IN (
                " .
                implode(
                    ', ',
                    $bindings['placeholders']
                ) .
                "
            )
              AND deleted_at IS NULL
            ORDER BY
                search_request_id ASC,
                amenity_code ASC
        ");

            $st->execute(
                $bindings['params']
            );

            while (
                $row =
                $st->fetch(PDO::FETCH_ASSOC)
            ) {
                $searchRequestId =
                    (int)$row['search_request_id'];

                $map[$searchRequestId][] =
                    (string)$row['amenity_code'];
            }
        }

        return $map;
    }

    private static function getSearchRequestExchangeOffersBatch(
        PDO $pdo,
        array $searchRequestIds
    ): array {
        $map = [];

        foreach (
            self::chunkIds($searchRequestIds)
            as $chunk
        ) {
            $bindings =
                self::buildIdPlaceholders(
                    $chunk,
                    'search_id'
                );

            $st = $pdo->prepare("
            SELECT
                seo.id,
                seo.search_request_id,
                seo.property_id,
                seo.title,
                seo.property_type,

                CASE
                    WHEN seo.property_id IS NOT NULL
                        THEN p.price
                    ELSE seo.estimated_price
                END AS estimated_price,

                CASE
                    WHEN seo.property_id IS NOT NULL
                        THEN p.currency
                    ELSE seo.currency
                END AS currency,

                seo.country_code,
                seo.country,
                seo.province,
                seo.city,
                seo.zone,
                seo.total_area,
                seo.covered_area,
                seo.bedrooms,
                seo.bathrooms,
                seo.garages,
                seo.antiquity

            FROM search_request_exchange_offers seo

            LEFT JOIN properties p
                ON p.id = seo.property_id
                AND p.deleted_at IS NULL
                AND p.status = 'published'
                AND p.is_visible = 1

            WHERE seo.search_request_id IN (
                " .
                implode(
                    ', ',
                    $bindings['placeholders']
                ) .
                "
            )

              AND seo.deleted_at IS NULL

              AND (
                  seo.property_id IS NULL
                  OR p.id IS NOT NULL
              )

            ORDER BY
                seo.search_request_id ASC,
                estimated_price DESC,
                seo.id ASC
        ");

            $st->execute(
                $bindings['params']
            );

            while (
                $row =
                $st->fetch(PDO::FETCH_ASSOC)
            ) {
                $searchRequestId =
                    (int)$row['search_request_id'];

                $map[$searchRequestId][] =
                    $row;
            }
        }

        return $map;
    }

    private static function getSearchRequestPropertyTypes(
        PDO $pdo,
        int $searchRequestId
    ): array {
        $st = $pdo->prepare("
            SELECT property_type
            FROM search_request_property_types
            WHERE search_request_id = :search_request_id
            ORDER BY property_type ASC
        ");

        $st->execute([
            'search_request_id' => $searchRequestId,
        ]);

        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function getSearchRequestAmenities(
        PDO $pdo,
        int $searchRequestId
    ): array {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM search_request_amenities
            WHERE search_request_id = :search_request_id
              AND deleted_at IS NULL
            ORDER BY amenity_code ASC
        ");

        $st->execute([
            'search_request_id' => $searchRequestId,
        ]);

        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function getCandidateProperties(
        PDO $pdo,
        int $sourceRealEstateId,
        array $propertyTypes,
        int $afterId = 0,
        int $limit = self::BATCH_SIZE
    ): array {
        $sql = "
        SELECT p.*
        FROM properties p
        WHERE p.real_estate_id <> :source_real_estate_id
          AND p.status = 'published'
          AND p.is_visible = 1
          AND p.deleted_at IS NULL
          
    ";
        $sql .= "
    AND p.id > :after_id
";
        $params = [
            'source_real_estate_id' =>
            $sourceRealEstateId,
            'after_id' => $afterId,
        ];

        /*
     * Si la búsqueda especifica tipos de propiedad,
     * descartamos directamente desde SQL los tipos
     * que el motor igualmente descartaría después.
     */
        $propertyTypes = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn($type) =>
                        trim((string)$type),
                        $propertyTypes
                    ),
                    static fn($type) =>
                    $type !== ''
                )
            )
        );

        if ($propertyTypes !== []) {
            $placeholders = [];

            foreach ($propertyTypes as $index => $propertyType) {
                $key =
                    'property_type_' . $index;

                $placeholders[] =
                    ':' . $key;

                $params[$key] =
                    $propertyType;
            }

            $sql .= "
          AND p.property_type IN (
              " .
                implode(
                    ', ',
                    $placeholders
                ) .
                "
          )
        ";
        }

        $limit = max(
            1,
            min(self::BATCH_SIZE, $limit)
        );

        $sql .= "
    ORDER BY p.id ASC
    LIMIT {$limit}
";

        $st = $pdo->prepare($sql);

        $st->execute($params);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function getSearchRequestExchangeOffers(
        PDO $pdo,
        int $searchRequestId
    ): array {
        $st = $pdo->prepare("
        SELECT
            seo.id,
            seo.search_request_id,
            seo.property_id,
            seo.title,
            seo.property_type,

            CASE
                WHEN seo.property_id IS NOT NULL
                    THEN p.price
                ELSE seo.estimated_price
            END AS estimated_price,

            CASE
                WHEN seo.property_id IS NOT NULL
                    THEN p.currency
                ELSE seo.currency
            END AS currency,

            seo.country_code,
            seo.country,
            seo.province,
            seo.city,
            seo.zone,
            seo.total_area,
            seo.covered_area,
            seo.bedrooms,
            seo.bathrooms,
            seo.garages,
            seo.antiquity

        FROM search_request_exchange_offers seo

        LEFT JOIN properties p
            ON p.id = seo.property_id
            AND p.deleted_at IS NULL
            AND p.status = 'published'
            AND p.is_visible = 1

        WHERE seo.search_request_id =
            :search_request_id

          AND seo.deleted_at IS NULL

          AND (
              seo.property_id IS NULL
              OR p.id IS NOT NULL
          )

        ORDER BY
            estimated_price DESC,
            seo.id ASC
    ");

        $st->execute([
            'search_request_id' =>
            $searchRequestId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function getPropertyAmenities(
        PDO $pdo,
        int $propertyId
    ): array {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM property_amenities
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY amenity_code ASC
        ");

        $st->execute([
            'property_id' => $propertyId,
        ]);

        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    private static function getPropertyRequirements(
        PDO $pdo,
        int $propertyId
    ): ?array {
        $st = $pdo->prepare("
        SELECT *
        FROM property_requirements
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'property_id' => $propertyId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function hasLocationCriteria(
        array $search
    ): bool {
        foreach (
            [
                'country',
                'province',
                'city',
                'zone',
            ] as $field
        ) {
            if (
                trim(
                    (string)(
                        $search[$field] ?? ''
                    )
                ) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    private static function hasPriceCriteria(
        array $search
    ): bool {
        $minValue =
            self::toNullableFloat(
                $search['min_value'] ?? null
            );

        $maxValue =
            self::toNullableFloat(
                $search['max_value'] ?? null
            );

        return ($minValue !== null && $minValue > 0) ||
            ($maxValue !== null && $maxValue > 0);
    }
    private static function evaluatePropertyAgainstSearch(
        array $search,
        array $desiredPropertyTypes,
        array $desiredAmenities,
        array $exchangeOffers,
        array $property,
        array $propertyAmenities,
        ?array $propertyRequirements
    ): array {
        $score = 0.0;
        $possibleScore = 0.0;
        $reasons = [];
        $penalties = [];
        $discarded = false;

        /*
         * 1. Tipo de propiedad: 25 puntos.
         * Si hay tipos requeridos y no coincide, se descarta.
         */
        if ($desiredPropertyTypes !== []) {
            $possibleScore += 25;

            if (
                in_array(
                    (string)$property['property_type'],
                    $desiredPropertyTypes,
                    true
                )
            ) {
                $score += 25;
                $reasons[] = [
                    'code' => 'property_type_match',
                    'label' => 'Tipo de propiedad compatible',
                    'weight' => 25,
                    'matched' => true,
                    'search_value' => $desiredPropertyTypes,
                    'property_value' =>
                    $property['property_type'],
                ];
            } else {
                return self::discardResult(
                    'property_type_mismatch',
                    'El tipo de propiedad no coincide con la búsqueda'
                );
            }
        }

        /*
 * 2. Ubicación: 25 puntos.
 *
 * Solo participa del score cuando la búsqueda
 * tiene alguna ubicación efectivamente definida.
 */
        if (self::hasLocationCriteria($search)) {
            $possibleScore += 25;

            $locationScore =
                self::calculateLocationScore(
                    $search,
                    $property
                );

            $score +=
                $locationScore['score'];

            $reasons[] =
                $locationScore['reason'];

            if (
                $locationScore['score'] === 0.0
            ) {
                $penalties[] = [
                    'code' =>
                    'location_mismatch',

                    'label' =>
                    'La propiedad está fuera de la ubicación buscada',

                    'penalty' => 0,
                ];
            }
        }

        /*
 * 3. Precio: 20 puntos.
 *
 * Solo participa cuando la búsqueda
 * especificó un mínimo y/o un máximo.
 */

        $priceResult = [
            'score' => 0.0,
            'reason' => null,
            'has_significant_mismatch' => false,
        ];
        if (self::hasPriceCriteria($search)) {
            $possibleScore += 20;

            $priceResult =
                self::calculatePriceScore(
                    $search,
                    $property
                );

            $score +=
                $priceResult['score'];

            $reasons[] =
                $priceResult['reason'];
        }
        /*
         * 4. Características mínimas: 20 puntos.
         */
        $featuresResult = self::calculateFeaturesScore(
            $search,
            $property
        );

        $score += $featuresResult['score'];
        $possibleScore += $featuresResult['possible_score'];

        $reasons = array_merge(
            $reasons,
            $featuresResult['reasons']
        );

        $penalties = array_merge(
            $penalties,
            $featuresResult['penalties']
        );

        /*
         * 5. Amenities: 10 puntos.
         */
        if ($desiredAmenities !== []) {
            $possibleScore += 10;

            $matchedAmenities = array_values(
                array_intersect(
                    $desiredAmenities,
                    $propertyAmenities
                )
            );

            $amenityRatio =
                count($matchedAmenities) /
                count($desiredAmenities);

            $amenityScore = round(
                10 * $amenityRatio,
                2
            );

            $score += $amenityScore;

            $reasons[] = [
                'code' => 'amenities_match',
                'label' => 'Amenities compatibles',
                'weight' => 10,
                'score' => $amenityScore,
                'matched' => $matchedAmenities,
                'required' => $desiredAmenities,
            ];
        }
        /*
 /*
 * 6. Viabilidad de permuta: 20 puntos.
 *
 * Solo se evalúa cuando la búsqueda indica que
 * contempla intercambio.
 */
        if ((int)($search['payment_mode_swap'] ?? 0) === 1) {
            $possibleScore += 20;

            $swapResult = self::calculateSwapScore(
                $search,
                $exchangeOffers,
                $property,
                $propertyRequirements
            );

            $score += $swapResult['score'];

            $reasons = array_merge(
                $reasons,
                $swapResult['reasons']
            );

            $penalties = array_merge(
                $penalties,
                $swapResult['penalties']
            );
        }

        /*
 * Normalización a escala 0–100.
 */
        $normalizedScore = $possibleScore > 0
            ? round(
                ($score / $possibleScore) * 100,
                2
            )
            : 0.0;

        /*
 * Primero obtenemos el nivel normal
 * según el porcentaje alcanzado.
 */
        $matchLevel =
            self::resolveMatchLevel(
                $normalizedScore
            );

        /*
 * Si la propiedad incumple alguna característica
 * mínima expresamente solicitada, evitamos mostrarla
 * como compatibilidad alta o total.
 *
 * Puede seguir siendo una alternativa válida,
 * pero existe una diferencia importante respecto
 * de lo solicitado.
 */
        $hasImportantMismatch =
            ($featuresResult['has_minimum_mismatch'] ?? false)
            ||
            ($priceResult['has_significant_mismatch'] ?? false);

        if (
            $hasImportantMismatch &&
            in_array(
                $matchLevel,
                ['high', 'total'],
                true
            )
        ) {
            $matchLevel = 'medium';
        }

        return [
            'discarded' =>
            $discarded,

            'score' =>
            $normalizedScore,

            'match_level' =>
            $matchLevel,

            'reasons' =>
            $reasons,

            'penalties' =>
            $penalties,
        ];
    }

    private static function calculateLocationScore(
        array $search,
        array $property
    ): array {
        $searchCountry = self::normalizeText(
            $search['country'] ?? ''
        );

        $searchProvince = self::normalizeText(
            $search['province'] ?? ''
        );

        $searchCity = self::normalizeText(
            $search['city'] ?? ''
        );

        $searchZone = self::normalizeText(
            $search['zone'] ?? ''
        );

        $propertyCountry = self::normalizeText(
            $property['country'] ?? ''
        );

        $propertyProvince = self::normalizeText(
            $property['province'] ?? ''
        );

        $propertyCity = self::normalizeText(
            $property['city'] ?? ''
        );

        $propertyZone = self::normalizeText(
            $property['zone'] ?? ''
        );

        $openToOtherZones =
            (int)($search['open_to_other_zones'] ?? 0) === 1;

        if (
            $searchZone !== '' &&
            $searchZone === $propertyZone &&
            $searchCity === $propertyCity
        ) {
            return [
                'score' => 25.0,
                'reason' => [
                    'code' => 'exact_zone_match',
                    'label' => 'Coincidencia exacta de zona',
                    'weight' => 25,
                    'matched' => true,
                ],
            ];
        }

        if (
            $searchCity !== '' &&
            $searchCity === $propertyCity
        ) {
            return [
                'score' => $openToOtherZones ? 22.0 : 18.0,
                'reason' => [
                    'code' => 'city_match',
                    'label' =>
                    'Coincidencia de ciudad',
                    'weight' => 25,
                    'matched' => true,
                ],
            ];
        }

        if (
            $searchProvince !== '' &&
            $searchProvince === $propertyProvince
        ) {
            return [
                'score' => $openToOtherZones ? 10.0 : 3.0,
                'reason' => [
                    'code' => 'province_match',
                    'label' =>
                    'Coincidencia solamente de provincia',
                    'weight' => 25,
                    'matched' => true,
                ],
            ];
        }

        if (
            $searchCountry !== '' &&
            $searchCountry === $propertyCountry
        ) {
            return [
                'score' => 2.0,
                'reason' => [
                    'code' => 'country_match',
                    'label' =>
                    'Coincidencia solamente de país',
                    'weight' => 25,
                    'matched' => true,
                ],
            ];
        }

        return [
            'score' => 0.0,
            'reason' => [
                'code' => 'location_mismatch',
                'label' =>
                'La ubicación no coincide',
                'weight' => 25,
                'matched' => false,
            ],
        ];
    }
    private static function calculatePriceScore(
        array $search,
        array $property
    ): array {
        $searchCurrency = strtoupper(
            trim(
                (string)(
                    $search['currency'] ?? ''
                )
            )
        );

        $propertyCurrency = strtoupper(
            trim(
                (string)(
                    $property['currency'] ?? ''
                )
            )
        );

        $minValue = self::toNullableFloat(
            $search['min_value'] ?? null
        );

        $maxValue = self::toNullableFloat(
            $search['max_value'] ?? null
        );

        $propertyPrice = self::toNullableFloat(
            $property['price'] ?? null
        );

        /*
     * Si la propiedad no tiene precio,
     * no podemos evaluar este criterio.
     */
        if (
            $propertyPrice === null ||
            $propertyPrice <= 0
        ) {
            return [
                'score' => 0.0,

                'reason' => [
                    'code' =>
                    'price_not_available',

                    'label' =>
                    'La propiedad no tiene un precio válido para comparar',

                    'weight' =>
                    20,

                    'matched' =>
                    false,
                ],

                'has_significant_mismatch' =>
                true,
            ];
        }

        /*
     * Por ahora no hacemos conversión de monedas.
     * Si ambas están informadas y son distintas,
     * no consideramos comparable el precio.
     */
        $originalPropertyPrice =
            (float)($property['price'] ?? 0);

        $propertyPrice =
            $originalPropertyPrice;

        $conversionApplied = false;

        if (
            $searchCurrency !== '' &&
            $propertyCurrency !== '' &&
            $searchCurrency !== $propertyCurrency
        ) {
            $convertedPrice =
                self::convertAmount(
                    $originalPropertyPrice,
                    $propertyCurrency,
                    $searchCurrency
                );

            if ($convertedPrice === null) {
                return [
                    'score' => 0.0,

                    'reason' => [
                        'code' =>
                        'currency_conversion_unavailable',

                        'label' =>
                        'No hay una cotización disponible para comparar los precios',

                        'weight' =>
                        20,

                        'matched' =>
                        false,

                        'search_currency' =>
                        $searchCurrency,

                        'property_currency' =>
                        $propertyCurrency,
                    ],

                    'has_significant_mismatch' =>
                    true,
                ];
            }

            $propertyPrice =
                $convertedPrice;

            $conversionApplied = true;
        }

        /*
     * Precio dentro del rango solicitado.
     */
        $meetsMin =
            $minValue === null ||
            $minValue <= 0 ||
            $propertyPrice >= $minValue;

        $meetsMax =
            $maxValue === null ||
            $maxValue <= 0 ||
            $propertyPrice <= $maxValue;

        if ($meetsMin && $meetsMax) {
            return [
                'score' => 20.0,

                'reason' => [
                    'code' =>
                    'price_in_range',

                    'label' =>
                    'El precio está dentro del rango buscado',

                    'weight' =>
                    20,

                    'matched' =>
                    true,

                    'price' =>
                    $propertyPrice,
                    'original_price' =>
                    $originalPropertyPrice,

                    'original_currency' =>
                    $propertyCurrency,

                    'comparison_price' =>
                    $propertyPrice,

                    'comparison_currency' =>
                    $searchCurrency !== ''
                        ? $searchCurrency
                        : $propertyCurrency,

                    'currency_conversion_applied' =>
                    $conversionApplied,
                    'min_value' =>
                    $minValue,

                    'max_value' =>
                    $maxValue,
                ],

                'has_significant_mismatch' =>
                false,
            ];
        }

        /*
     * Una propiedad por debajo del mínimo no es
     * necesariamente un problema comercial.
     *
     * Puede significar una oportunidad más económica,
     * por lo que conserva una puntuación alta.
     */
        if (
            $minValue !== null &&
            $minValue > 0 &&
            $propertyPrice < $minValue
        ) {
            return [
                'score' => 18.0,

                'reason' => [
                    'code' =>
                    'price_below_range',

                    'label' =>
                    'El precio está por debajo del rango buscado',

                    'weight' =>
                    20,

                    'matched' =>
                    true,

                    'price' =>
                    $propertyPrice,

                    'min_value' =>
                    $minValue,
                ],

                'has_significant_mismatch' =>
                false,
            ];
        }

        /*
     * A partir de acá sabemos que está
     * por encima del máximo.
     */
        if (
            $maxValue === null ||
            $maxValue <= 0
        ) {
            return [
                'score' => 0.0,

                'reason' => [
                    'code' =>
                    'price_out_of_range',

                    'label' =>
                    'El precio no coincide con el rango buscado',

                    'weight' =>
                    20,

                    'matched' =>
                    false,
                ],

                'has_significant_mismatch' =>
                true,
            ];
        }

        $overPercentage =
            (($propertyPrice - $maxValue) / $maxValue)
            * 100;

        /*
     * Diferencia pequeña:
     * sigue siendo una muy buena alternativa.
     */
        if ($overPercentage <= 5) {
            return [
                'score' => 16.0,

                'reason' => [
                    'code' =>
                    'price_slightly_above',

                    'label' =>
                    'El precio está hasta un 5% por encima del presupuesto',

                    'weight' =>
                    20,

                    'matched' =>
                    false,

                    'difference_percentage' =>
                    round($overPercentage, 2),
                ],

                'has_significant_mismatch' =>
                false,
            ];
        }

        /*
     * Hasta 10%:
     * todavía puede tener sentido comercial.
     */
        if ($overPercentage <= 10) {
            return [
                'score' => 12.0,

                'reason' => [
                    'code' =>
                    'price_above_10',

                    'label' =>
                    'El precio está entre un 5% y un 10% por encima del presupuesto',

                    'weight' =>
                    20,

                    'matched' =>
                    false,

                    'difference_percentage' =>
                    round($overPercentage, 2),
                ],

                'has_significant_mismatch' =>
                false,
            ];
        }

        /*
     * Entre 10% y 20%:
     * ya es una diferencia relevante.
     */
        if ($overPercentage <= 20) {
            return [
                'score' => 6.0,

                'reason' => [
                    'code' =>
                    'price_significantly_above',

                    'label' =>
                    'El precio está entre un 10% y un 20% por encima del presupuesto',

                    'weight' =>
                    20,

                    'matched' =>
                    false,

                    'difference_percentage' =>
                    round($overPercentage, 2),
                ],

                'has_significant_mismatch' =>
                true,
            ];
        }

        /*
     * Más de 20%:
     * la diferencia de precio es fuerte.
     */
        return [
            'score' => 0.0,

            'reason' => [
                'code' =>
                'price_far_above',

                'label' =>
                'El precio supera ampliamente el presupuesto buscado',

                'weight' =>
                20,

                'matched' =>
                false,

                'difference_percentage' =>
                round($overPercentage, 2),
            ],

            'has_significant_mismatch' =>
            true,
        ];
    }

    private static function calculateFeaturesScore(
        array $search,
        array $property
    ): array {
        $rules = [
            [
                'search_field' => 'min_total_area',
                'property_field' => 'total_area',
                'label' => 'Superficie total mínima',
                'weight' => 5,
            ],
            [
                'search_field' => 'min_covered_area',
                'property_field' => 'covered_area',
                'label' => 'Superficie cubierta mínima',
                'weight' => 5,
            ],
            [
                'search_field' => 'min_bedrooms',
                'property_field' => 'bedrooms',
                'label' => 'Dormitorios mínimos',
                'weight' => 4,
            ],
            [
                'search_field' => 'min_bathrooms',
                'property_field' => 'bathrooms',
                'label' => 'Baños mínimos',
                'weight' => 3,
            ],
            [
                'search_field' => 'min_garages',
                'property_field' => 'garages',
                'label' => 'Cocheras mínimas',
                'weight' => 3,
            ],
        ];

        $score = 0.0;
        $possibleScore = 0.0;
        $reasons = [];
        $penalties = [];
        $hasMinimumMismatch = false;

        foreach ($rules as $rule) {
            $required = self::toNullableFloat(
                $search[$rule['search_field']] ?? null
            );

            if ($required === null || $required <= 0) {
                continue;
            }

            $possibleScore += $rule['weight'];

            $actual = self::toNullableFloat(
                $property[$rule['property_field']] ?? null
            );

            $matched =
                $actual !== null &&
                $actual >= $required;

            if ($matched) {
                $score += $rule['weight'];
            } else {
                $hasMinimumMismatch = true;

                $penalties[] = [
                    'code' =>
                    $rule['search_field'] . '_mismatch',
                    'label' =>
                    $rule['label'],
                    'required' =>
                    $required,
                    'actual' =>
                    $actual,
                ];
            }

            $reasons[] = [
                'code' =>
                $rule['search_field'] . '_match',
                'label' =>
                $rule['label'],
                'weight' =>
                $rule['weight'],
                'matched' =>
                $matched,
                'required' =>
                $required,
                'actual' =>
                $actual,
            ];
        }

        return [
            'score' =>
            $score,

            'possible_score' =>
            $possibleScore,

            'has_minimum_mismatch' =>
            $hasMinimumMismatch,

            'reasons' =>
            $reasons,

            'penalties' =>
            $penalties,
        ];
    }
    private static function calculateSwapScore(
        array $search,
        array $exchangeOffers,
        array $property,
        ?array $requirements
    ): array {
        $score = 0.0;
        $reasons = [];
        $penalties = [];

        /*
     * --------------------------------------------------
     * Validaciones iniciales
     * --------------------------------------------------
     */

        if ($exchangeOffers === []) {
            return [
                'score' => 0.0,

                'reasons' => [
                    [
                        'code' =>
                        'exchange_offer_missing',

                        'label' =>
                        'No hay un inmueble ofrecido para la permuta',

                        'weight' =>
                        20,

                        'matched' =>
                        false,
                    ],
                ],

                'penalties' => [
                    [
                        'code' =>
                        'exchange_offer_missing',

                        'label' =>
                        'La búsqueda indica permuta, pero no tiene una oferta cargada',
                    ],
                ],
            ];
        }

        if (!$requirements) {
            return [
                'score' => 0.0,

                'reasons' => [
                    [
                        'code' =>
                        'swap_not_confirmed',

                        'label' =>
                        'La propiedad no informó condiciones de permuta',

                        'weight' =>
                        20,

                        'matched' =>
                        false,
                    ],
                ],

                'penalties' => [
                    [
                        'code' =>
                        'property_requirements_missing',

                        'label' =>
                        'No se puede confirmar que el propietario acepte permuta',
                    ],
                ],
            ];
        }

        /*
     * --------------------------------------------------
     * Modalidades aceptadas por el propietario
     * --------------------------------------------------
     */

        $acceptsTotalSwap =
            (int)(
                $requirements['accepts_total_swap'] ?? 0
            ) === 1;

        $acceptsSwapPlusCash =
            (int)(
                $requirements['accepts_swap_plus_cash'] ?? 0
            ) === 1;

        $acceptsMultipleSwap =
            (int)(
                $requirements['accepts_multiple_swap'] ?? 0
            ) === 1;

        $acceptsOpenProposals =
            (int)(
                $requirements['accepts_open_proposals'] ?? 0
            ) === 1;

        $acceptsAnySwap =
            $acceptsTotalSwap ||
            $acceptsSwapPlusCash ||
            $acceptsMultipleSwap ||
            $acceptsOpenProposals;

        /*
     * 1. Modalidad aceptada: 5 puntos.
     */

        if (!$acceptsAnySwap) {
            return [
                'score' => 0.0,

                'reasons' => [
                    [
                        'code' =>
                        'swap_mode_rejected',

                        'label' =>
                        'La propiedad no tiene habilitada una modalidad de permuta',

                        'weight' =>
                        5,

                        'matched' =>
                        false,
                    ],
                ],

                'penalties' => [
                    [
                        'code' =>
                        'swap_not_accepted',

                        'label' =>
                        'La propiedad objetivo no acepta permuta',
                    ],
                ],
            ];
        }

        $score += 5;

        $reasons[] = [
            'code' =>
            'swap_mode_accepted',

            'label' =>
            'La propiedad acepta operaciones con permuta',

            'weight' =>
            5,

            'matched' =>
            true,
        ];

        /*
     * --------------------------------------------------
     * Precio de propiedad objetivo
     * --------------------------------------------------
     */

        $propertyPrice =
            self::toNullableFloat(
                $property['price'] ?? null
            );

        $propertyCurrency =
            strtoupper(
                trim(
                    (string)(
                        $property['currency']
                        ?? ''
                    )
                )
            );

        if (
            $propertyPrice === null ||
            $propertyPrice <= 0 ||
            $propertyCurrency === ''
        ) {
            return [
                'score' =>
                $score,

                'reasons' =>
                array_merge(
                    $reasons,
                    [
                        [
                            'code' =>
                            'target_property_value_missing',

                            'label' =>
                            'La propiedad objetivo no tiene un valor válido para evaluar la permuta',

                            'weight' =>
                            15,

                            'matched' =>
                            false,
                        ],
                    ]
                ),

                'penalties' => [
                    [
                        'code' =>
                        'target_property_value_missing',

                        'label' =>
                        'Falta precio o moneda de la propiedad objetivo',
                    ],
                ],
            ];
        }

        /*
     * --------------------------------------------------
     * Elegimos la mejor propiedad ofrecida.
     *
     * Todas las ofertas se convierten a la moneda
     * de la propiedad objetivo antes de compararlas.
     * --------------------------------------------------
     */

        $selectedOffer = null;
        $selectedOfferComparablePrice = null;
        $selectedOfferOriginalPrice = null;
        $selectedOfferOriginalCurrency = null;
        $selectedOfferConversionApplied = false;

        foreach ($exchangeOffers as $offer) {
            $offerPrice =
                self::toNullableFloat(
                    $offer['estimated_price'] ?? null
                );

            $offerCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $offer['currency']
                            ?? ''
                        )
                    )
                );

            if (
                $offerPrice === null ||
                $offerPrice <= 0 ||
                $offerCurrency === ''
            ) {
                continue;
            }

            $comparablePrice =
                self::convertAmount(
                    $offerPrice,
                    $offerCurrency,
                    $propertyCurrency
                );

            if ($comparablePrice === null) {
                continue;
            }

            if (
                $selectedOffer === null ||
                $selectedOfferComparablePrice === null ||
                $comparablePrice >
                $selectedOfferComparablePrice
            ) {
                $selectedOffer =
                    $offer;

                $selectedOfferComparablePrice =
                    $comparablePrice;

                $selectedOfferOriginalPrice =
                    $offerPrice;

                $selectedOfferOriginalCurrency =
                    $offerCurrency;

                $selectedOfferConversionApplied =
                    $offerCurrency !==
                    $propertyCurrency;
            }
        }

        if (
            $selectedOffer === null ||
            $selectedOfferComparablePrice === null
        ) {
            $reasons[] = [
                'code' =>
                'swap_values_not_comparable',

                'label' =>
                'No se pueden comparar los valores de la permuta',

                'weight' =>
                15,

                'matched' =>
                false,
            ];

            $penalties[] = [
                'code' =>
                'swap_currency_conversion_unavailable',

                'label' =>
                'No hay precios válidos o una cotización disponible para comparar la permuta',
            ];

            return [
                'score' =>
                round($score, 2),

                'reasons' =>
                $reasons,

                'penalties' =>
                $penalties,
            ];
        }

        $offerPrice =
            $selectedOfferComparablePrice;

        /*
     * --------------------------------------------------
     * 2. Valor del inmueble ofrecido:
     * 5 puntos.
     *
     * price_min / price_max pueden estar expresados
     * en otra moneda.
     * --------------------------------------------------
     */

        $requirementCurrency =
            strtoupper(
                trim(
                    (string)(
                        $requirements['price_currency']
                        ?? $propertyCurrency
                    )
                )
            );

        if ($requirementCurrency === '') {
            $requirementCurrency =
                $propertyCurrency;
        }

        $priceMinRaw =
            self::toNullableFloat(
                $requirements['price_min'] ?? null
            );

        $priceMaxRaw =
            self::toNullableFloat(
                $requirements['price_max'] ?? null
            );

        $priceMin = null;
        $priceMax = null;

        if ($priceMinRaw !== null) {
            $priceMin =
                self::convertAmount(
                    $priceMinRaw,
                    $requirementCurrency,
                    $propertyCurrency
                );
        }

        if ($priceMaxRaw !== null) {
            $priceMax =
                self::convertAmount(
                    $priceMaxRaw,
                    $requirementCurrency,
                    $propertyCurrency
                );
        }

        /*
     * Si había un límite informado pero no
     * pudimos convertirlo, no lo consideramos match.
     */
        $offerRangeConversionAvailable =
            ($priceMinRaw === null ||
                $priceMin !== null)
            &&
            ($priceMaxRaw === null ||
                $priceMax !== null);

        $withinAcceptedOfferRange =
            $offerRangeConversionAvailable &&
            (
                $priceMin === null ||
                $priceMin <= 0 ||
                $offerPrice >= $priceMin
            ) &&
            (
                $priceMax === null ||
                $priceMax <= 0 ||
                $offerPrice <= $priceMax
            );

        if ($withinAcceptedOfferRange) {
            $score += 5;
        } else {
            $penalties[] = [
                'code' =>
                'exchange_offer_value_out_of_range',

                'label' =>
                'El valor del inmueble ofrecido no está dentro del rango aceptado',

                'offered_value' =>
                $offerPrice,

                'accepted_min' =>
                $priceMin,

                'accepted_max' =>
                $priceMax,

                'currency' =>
                $propertyCurrency,
            ];
        }

        $reasons[] = [
            'code' =>
            'exchange_offer_value_match',

            'label' =>
            'Valor del inmueble ofrecido compatible',

            'weight' =>
            5,

            'matched' =>
            $withinAcceptedOfferRange,

            'offered_value' =>
            $offerPrice,

            'accepted_min' =>
            $priceMin,

            'accepted_max' =>
            $priceMax,

            'currency' =>
            $propertyCurrency,

            'original_offered_value' =>
            $selectedOfferOriginalPrice,

            'original_offered_currency' =>
            $selectedOfferOriginalCurrency,

            'currency_conversion_applied' =>
            $selectedOfferConversionApplied,
        ];

        /*
     * --------------------------------------------------
     * Diferencia económica real.
     *
     * POSITIVA:
     * propiedad buscada vale más.
     * El buscador debe agregar dinero.
     * Para el propietario objetivo es "a_favor".
     *
     * NEGATIVA:
     * propiedad ofrecida vale más.
     * El propietario objetivo debería agregar dinero.
     * Para él es "en_contra".
     *
     * CERO:
     * permuta total.
     * --------------------------------------------------
     */

        $signedDifference =
            round(
                $propertyPrice -
                    $offerPrice,
                2
            );

        $absoluteDifference =
            round(
                abs($signedDifference),
                2
            );

        if ($signedDifference > 0) {
            $actualDirection =
                'a_favor';
        } elseif ($signedDifference < 0) {
            $actualDirection =
                'en_contra';
        } else {
            $actualDirection =
                'total';
        }

        /*
     * --------------------------------------------------
     * 3. Capacidad económica del buscador:
     * 5 puntos.
     *
     * Sólo necesita aportar efectivo cuando
     * signedDifference > 0.
     * --------------------------------------------------
     */

        $availableDifferenceRaw =
            self::toNullableFloat(
                $search['cash_difference_max'] ?? null
            );

        $availableDifferenceCurrency =
            strtoupper(
                trim(
                    (string)(
                        $search['cash_difference_currency'] ?? ''
                    )
                )
            );

        $availableDifference = null;

        if (
            $availableDifferenceRaw !== null &&
            $availableDifferenceRaw >= 0
        ) {
            if (
                $availableDifferenceCurrency === ''
            ) {
                $availableDifferenceCurrency =
                    $propertyCurrency;
            }

            $availableDifference =
                self::convertAmount(
                    $availableDifferenceRaw,
                    $availableDifferenceCurrency,
                    $propertyCurrency
                );
        }

        /*
     * Sin diferencia o diferencia a favor
     * del buscador: no necesita aportar efectivo.
     */
        if ($signedDifference <= 0) {
            $canCoverDifference =
                true;
        } else {
            $searchAcceptsCash =
                (int)(
                    $search['payment_mode_cash'] ?? 0
                ) === 1;

            $canCoverDifference =
                $searchAcceptsCash &&
                $availableDifference !== null &&
                $availableDifference >=
                $absoluteDifference;
        }

        if ($canCoverDifference) {
            $score += 5;
        } else {
            $penalties[] = [
                'code' =>
                'insufficient_cash_difference',

                'label' =>
                'El efectivo disponible no alcanza para cubrir la diferencia',

                'required_difference' =>
                $absoluteDifference,

                'available_difference' =>
                $availableDifference,

                'currency' =>
                $propertyCurrency,
            ];
        }

        $reasons[] = [
            'code' =>
            'cash_difference_capacity',

            'label' =>
            'Capacidad para cubrir la diferencia en efectivo',

            'weight' =>
            5,

            'matched' =>
            $canCoverDifference,

            'required_difference' =>
            $signedDifference > 0
                ? $absoluteDifference
                : 0.0,

            'available_difference' =>
            $availableDifference,

            'currency' =>
            $propertyCurrency,
        ];

        /*
     * --------------------------------------------------
     * 4. Condiciones económicas del propietario:
     * 5 puntos.
     * --------------------------------------------------
     */

        $differenceCurrency =
            strtoupper(
                trim(
                    (string)(
                        $requirements['cash_difference_currency'] ?? ''
                    )
                )
            );

        if ($differenceCurrency === '') {
            $differenceCurrency =
                $propertyCurrency;
        }

        $differenceMinRaw =
            self::toNullableFloat(
                $requirements['cash_difference_min'] ?? null
            );

        $differenceMaxRaw =
            self::toNullableFloat(
                $requirements['cash_difference_max'] ?? null
            );

        $differenceMin = null;
        $differenceMax = null;

        if ($differenceMinRaw !== null) {
            $differenceMin =
                self::convertAmount(
                    $differenceMinRaw,
                    $differenceCurrency,
                    $propertyCurrency
                );
        }

        if ($differenceMaxRaw !== null) {
            $differenceMax =
                self::convertAmount(
                    $differenceMaxRaw,
                    $differenceCurrency,
                    $propertyCurrency
                );
        }

        $differenceRangeConversionAvailable =
            ($differenceMinRaw === null ||
                $differenceMin !== null)
            &&
            ($differenceMaxRaw === null ||
                $differenceMax !== null);

        $differenceDirection =
            strtolower(
                trim(
                    (string)(
                        $requirements['cash_difference_direction'] ?? ''
                    )
                )
            );

        /*
     * Validamos primero la modalidad económica.
     */
        if ($actualDirection === 'total') {
            $modeCompatible =
                $acceptsTotalSwap ||
                $acceptsOpenProposals;

            $directionCompatible =
                $modeCompatible;
        } else {
            $modeCompatible =
                $acceptsSwapPlusCash ||
                $acceptsOpenProposals;

            $directionCompatible =
                $modeCompatible &&
                (
                    $differenceDirection ===
                    'indistinto' ||
                    $differenceDirection ===
                    $actualDirection ||
                    (
                        $differenceDirection === '' &&
                        $acceptsOpenProposals
                    )
                );
        }

        $differenceWithinOwnerRange =
            $differenceRangeConversionAvailable &&
            $directionCompatible &&
            (
                $differenceMin === null ||
                $differenceMin <= 0 ||
                $absoluteDifference >=
                $differenceMin
            ) &&
            (
                $differenceMax === null ||
                $differenceMax <= 0 ||
                $absoluteDifference <=
                $differenceMax
            );

        if ($differenceWithinOwnerRange) {
            $score += 5;
        } else {
            $penalties[] = [
                'code' =>
                'owner_difference_conditions_mismatch',

                'label' =>
                'La diferencia calculada no cumple las condiciones del propietario',

                'difference' =>
                $absoluteDifference,

                'accepted_min' =>
                $differenceMin,

                'accepted_max' =>
                $differenceMax,

                'required_direction' =>
                $differenceDirection,

                'actual_direction' =>
                $actualDirection,

                'currency' =>
                $propertyCurrency,
            ];
        }

        $reasons[] = [
            'code' =>
            'owner_difference_conditions',

            'label' =>
            'Diferencia aceptada por el propietario',

            'weight' =>
            5,

            'matched' =>
            $differenceWithinOwnerRange,

            'difference' =>
            $absoluteDifference,

            'signed_difference' =>
            $signedDifference,

            'accepted_min' =>
            $differenceMin,

            'accepted_max' =>
            $differenceMax,

            'required_direction' =>
            $differenceDirection,

            'actual_direction' =>
            $actualDirection,

            'currency' =>
            $propertyCurrency,
        ];

        /*
     * Metadatos de la cotización usada.
     *
     * Como CurrencyConversionService tiene cache,
     * esto no genera consultas repetidas.
     */
        $rateInfo =
            CurrencyConversionService::getCurrentRateInfo();

        $reasons[] = [
            'code' =>
            'currency_reference',

            'label' =>
            'Cotización de referencia utilizada',

            'weight' =>
            0,

            'matched' =>
            true,

            'rate' =>
            $rateInfo,
        ];

        return [
            'score' =>
            round($score, 2),

            'reasons' =>
            $reasons,

            'penalties' =>
            $penalties,
        ];
    }

    private static function saveCompatibility(
        PDO $pdo,
        array $search,
        array $property,
        array $evaluation
    ): int {
        $reasonsJson = json_encode(
            [
                'reasons' => $evaluation['reasons'],
                'penalties' => $evaluation['penalties'],
                'engine_version' => 'search-property-v2',
            ],
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        $matchReason = self::buildMatchReason(
            $evaluation
        );

        $stExisting = $pdo->prepare("
            SELECT
    id,
    score,
    status
FROM compatibilities
            WHERE property_id = :property_id
              AND search_request_id = :search_request_id
            LIMIT 1
        ");

        $stExisting->execute([
            'property_id' => (int)$property['id'],
            'search_request_id' => (int)$search['id'],
        ]);

        $existing =
            $stExisting->fetch(
                PDO::FETCH_ASSOC
            );

        $existingId =
            (int)($existing['id'] ?? 0);

        if ($existingId > 0) {
            $stUpdate = $pdo->prepare("
                UPDATE compatibilities
                SET
                    compatibility_type =
                        'property_search_request',
                    source_type = 'search_request',
                    source_id = :source_id,
                    target_type = 'property',
                    target_id = :target_id,
                    source_real_estate_id =
                        :source_real_estate_id,
                    target_real_estate_id =
                        :target_real_estate_id,
                    detected_from = 'system',
                    score = :score,
                    match_level = :match_level,
                    match_reason = :match_reason,
                    reasons_json = :reasons_json,
                    calculated_at = NOW(),
status = CASE
    WHEN status = 'archived'
        THEN 'detected'
    ELSE status
END,
archived_at = CASE
    WHEN status = 'archived'
        THEN NULL
    ELSE archived_at
END,
deleted_at = NULL,
updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                LIMIT 1
            ");

            $stUpdate->execute([
                'source_id' => (int)$search['id'],
                'target_id' => (int)$property['id'],
                'source_real_estate_id' =>
                (int)$search['real_estate_id'],
                'target_real_estate_id' =>
                (int)$property['real_estate_id'],
                'score' => $evaluation['score'],
                'match_level' =>
                $evaluation['match_level'],
                'match_reason' => $matchReason,
                'reasons_json' => $reasonsJson,
                'id' => $existingId,
            ]);
            $previousScore =
                (float)($existing['score'] ?? 0);

            $newScore =
                (float)$evaluation['score'];

            $wasMatch =
                $previousScore >=
                self::MIN_SCORE_TO_NOTIFY
                &&
                ($existing['status'] ?? '') !==
                'archived';

            $isMatch =
                $newScore >=
                self::MIN_SCORE_TO_NOTIFY;

            if (!$wasMatch && $isMatch) {
                self::createMatchEvent(
                    $pdo,
                    'direct_new',
                    $existingId,
                    [
                        'score' => $newScore,
                        'previous_score' =>
                        $previousScore,
                    ]
                );
            } elseif (
                $wasMatch &&
                $isMatch &&
                abs(
                    $newScore -
                        $previousScore
                ) >= 5
            ) {
                self::createMatchEvent(
                    $pdo,
                    'direct_changed',
                    $existingId,
                    [
                        'score' => $newScore,
                        'previous_score' =>
                        $previousScore,
                    ]
                );
            } elseif (
                $wasMatch &&
                !$isMatch
            ) {
                self::createMatchEvent(
                    $pdo,
                    'direct_lost',
                    $existingId,
                    [
                        'score' => $newScore,
                        'previous_score' =>
                        $previousScore,
                    ]
                );
            }
            return $existingId;
        }

        $stInsert = $pdo->prepare("
            INSERT INTO compatibilities (
                compatibility_type,
                source_type,
                source_id,
                target_type,
                target_id,
                property_id,
                related_property_id,
                search_request_id,
                source_real_estate_id,
                target_real_estate_id,
                detected_from,
                score,
                match_level,
                match_reason,
                reasons_json,
                calculated_at,
                source_response,
                target_response,
                status
            ) VALUES (
                'property_search_request',
                'search_request',
                :source_id,
                'property',
                :target_id,
                :property_id,
                NULL,
                :search_request_id,
                :source_real_estate_id,
                :target_real_estate_id,
                'system',
                :score,
                :match_level,
                :match_reason,
                :reasons_json,
                NOW(),
                'pending',
                'pending',
                'detected'
            )
        ");

        $stInsert->execute([
            'source_id' => (int)$search['id'],
            'target_id' => (int)$property['id'],
            'property_id' => (int)$property['id'],
            'search_request_id' => (int)$search['id'],
            'source_real_estate_id' =>
            (int)$search['real_estate_id'],
            'target_real_estate_id' =>
            (int)$property['real_estate_id'],
            'score' => $evaluation['score'],
            'match_level' => $evaluation['match_level'],
            'match_reason' => $matchReason,
            'reasons_json' => $reasonsJson,
        ]);
        $compatibilityId =
            (int)$pdo->lastInsertId();

        if (
            (float)$evaluation['score'] >=
            self::MIN_SCORE_TO_NOTIFY
        ) {
            self::createMatchEvent(
                $pdo,
                'direct_new',
                $compatibilityId,
                [
                    'score' =>
                    (float)$evaluation['score'],
                ]
            );
        }

        return $compatibilityId;
    }
    private static function archiveStaleCompatibilities(
        PDO $pdo,
        int $searchRequestId,
        array $activePropertyIds
    ): int {
        $params = [
            'search_request_id' =>
            $searchRequestId,

            'min_score' =>
            self::MIN_SCORE_TO_NOTIFY,
        ];

        /*
     * Construimos una única condición para
     * identificar las compatibilidades que
     * dejaron de ser válidas.
     */
        $baseWhere = "
        compatibility_type =
            'property_search_request'

        AND search_request_id =
            :search_request_id

        AND deleted_at IS NULL

        AND status IN (
            'detected',
            'dismissed'
        )
    ";

        if ($activePropertyIds !== []) {
            $propertyPlaceholders = [];

            foreach (
                array_values(
                    array_unique(
                        array_map(
                            'intval',
                            $activePropertyIds
                        )
                    )
                ) as $index => $propertyId
            ) {
                if ($propertyId <= 0) {
                    continue;
                }

                $key =
                    'active_property_' .
                    $index;

                $propertyPlaceholders[] =
                    ':' . $key;

                $params[$key] =
                    $propertyId;
            }

            if ($propertyPlaceholders !== []) {
                $baseWhere .= "
                AND property_id NOT IN (
                    " .
                    implode(
                        ', ',
                        $propertyPlaceholders
                    ) .
                    "
                )
            ";
            }
        }

        /*
     * Antes de archivar guardamos solamente
     * las oportunidades que eran matches
     * notificables.
     */
        $stLost = $pdo->prepare("
        SELECT id
        FROM compatibilities

        WHERE
            {$baseWhere}

          AND score >= :min_score
    ");

        $stLost->execute($params);

        $lostIds =
            $stLost->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        /*
     * min_score es únicamente para el SELECT.
     * El UPDATE debe archivar también las
     * compatibilidades inferiores a 75.
     */
        $updateParams =
            $params;

        unset(
            $updateParams['min_score']
        );

        $st = $pdo->prepare("
        UPDATE compatibilities

        SET
            status = 'archived',
            archived_at = NOW(),
            updated_at =
                CURRENT_TIMESTAMP

        WHERE
            {$baseWhere}
    ");

        $st->execute(
            $updateParams
        );

        $archivedCount =
            $st->rowCount();

        if (
            $archivedCount > 0 &&
            $lostIds !== []
        ) {
            self::createDirectLostEvents(
                $pdo,
                $lostIds
            );
        }

        return $archivedCount;
    }
    private static function buildMatchReason(
        array $evaluation
    ): string {
        $labels = [];

        foreach ($evaluation['reasons'] as $reason) {
            if (
                is_array($reason) &&
                ($reason['matched'] ?? false)
            ) {
                $label = trim(
                    (string)($reason['label'] ?? '')
                );

                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        if ($labels === []) {
            return 'Compatibilidad parcial detectada';
        }

        return implode('. ', array_slice($labels, 0, 4));
    }

    private static function resolveMatchLevel(
        float $score
    ): string {
        if ($score >= 90) {
            return 'total';
        }

        if ($score >= 75) {
            return 'high';
        }

        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }

    private static function discardResult(
        string $code,
        string $label
    ): array {
        return [
            'discarded' => true,
            'score' => 0.0,
            'match_level' => 'low',
            'reasons' => [
                [
                    'code' => $code,
                    'label' => $label,
                    'matched' => false,
                ],
            ],
            'penalties' => [],
        ];
    }

    private static function normalizeText(
        mixed $value
    ): string {
        $text = trim(
            mb_strtolower((string)$value, 'UTF-8')
        );

        $normalized = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $text
        );

        return trim(
            $normalized !== false
                ? strtolower($normalized)
                : $text
        );
    }

    private static function toNullableFloat(
        mixed $value
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }
    private static function chunkIds(
        array $ids
    ): array {
        $normalizedIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn($id): int =>
                        (int)$id,
                        $ids
                    ),
                    static fn(int $id): bool =>
                    $id > 0
                )
            )
        );

        if ($normalizedIds === []) {
            return [];
        }

        return array_chunk(
            $normalizedIds,
            self::BATCH_SIZE
        );
    }

    private static function buildIdPlaceholders(
        array $ids,
        string $prefix
    ): array {
        $params = [];
        $placeholders = [];

        foreach (
            array_values($ids)
            as $index => $id
        ) {
            $key =
                $prefix . '_' . $index;

            $placeholders[] =
                ':' . $key;

            $params[$key] =
                (int)$id;
        }

        return [
            'placeholders' => $placeholders,
            'params' => $params,
        ];
    }

    private static function createDirectLostEvents(
        PDO $pdo,
        array $compatibilityIds
    ): void {
        foreach (
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $compatibilityIds
                    )
                )
            ) as $compatibilityId
        ) {
            if ($compatibilityId <= 0) {
                continue;
            }

            self::createMatchEvent(
                $pdo,
                'direct_lost',
                $compatibilityId
            );
        }
    }

    private static function createMatchEvent(
        PDO $pdo,
        string $eventType,
        int $compatibilityId,
        array $payload = []
    ): void {
        $st = $pdo->prepare("
        INSERT INTO match_events (
            event_type,
            entity_type,
            entity_id,
            payload_json,
            occurred_at
        ) VALUES (
            :event_type,
            'compatibility',
            :entity_id,
            :payload_json,
            NOW()
        )
    ");

        $st->execute([
            'event_type' =>
            $eventType,

            'entity_id' =>
            $compatibilityId,

            'payload_json' =>
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }
}
