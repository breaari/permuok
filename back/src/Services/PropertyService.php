<?php

namespace App\Services;

use PDO;
use Exception;
use Throwable;

class PropertyService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;
    private const ROLE_INVESTOR = 4;
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function imageViewUrl(?int $imageId): ?string
    {
        if (!$imageId) {
            return null;
        }

        return '/property-images/' . $imageId . '/view';
    }

    private static function getPropertyAmenities(int $propertyId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT amenity_code
        FROM property_amenities
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        ORDER BY id ASC
    ");

        $st->execute([
            'property_id' => $propertyId,
        ]);

        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function replacePropertyAmenities(
        int $propertyId,
        array $amenities
    ): void {
        $pdo = self::db();

        $stDelete = $pdo->prepare("
        UPDATE property_amenities
        SET deleted_at = NOW()
        WHERE property_id = :property_id
          AND deleted_at IS NULL
    ");

        $stDelete->execute([
            'property_id' => $propertyId,
        ]);

        $amenities = array_values(array_unique(array_filter(
            array_map(
                fn($item) => trim((string)$item),
                $amenities
            )
        )));

        if (!$amenities) {
            return;
        }

        $stInsert = $pdo->prepare("
        INSERT INTO property_amenities (
            property_id,
            amenity_code
        )
        VALUES (
            :property_id,
            :amenity_code
        )
    ");

        foreach ($amenities as $amenity) {
            $stInsert->execute([
                'property_id' => $propertyId,
                'amenity_code' => $amenity,
            ]);
        }
    }

    private static function getValidPublisherUser(int $userId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, role, real_estate_id, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $user = $st->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT], true)) {
            throw new Exception("No tenés permisos para publicar propiedades");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        return $user;
    }

    private static function getValidViewerUser(int $userId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT id, role, real_estate_id, is_active
        FROM users
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute(['id' => $userId]);
        $user = $st->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT, self::ROLE_INVESTOR], true)) {
            throw new Exception("No tenés permisos para ver propiedades");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        return $user;
    }

    private static function getOwnedProperty(int $userId, int $propertyId): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $st = $pdo->prepare("
            SELECT *
            FROM properties
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            'id' => $propertyId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $property = $st->fetch();

        if (!$property) {
            throw new Exception("Propiedad no encontrada");
        }

        return [$user, $property];
    }

    private static function queueQualityRecalculation(
        int $propertyId
    ): void {
        try {
            CompatibilityJobService::enqueuePropertyQualityRecalculation(
                $propertyId
            );
        } catch (Throwable $e) {
            error_log(
                '[PROPERTY QUALITY QUEUE] ' .
                    'No se pudo encolar el recálculo ' .
                    'de calidad de la propiedad ' .
                    $propertyId . ': ' .
                    $e->getMessage()
            );
        }
    }

    private static function queueCompatibilityRecalculation(
        int $propertyId
    ): void {
        try {
            CompatibilityJobService::enqueuePropertyRecalculation(
                $propertyId
            );
        } catch (Throwable $e) {
            error_log(
                '[PROPERTY COMPATIBILITY QUEUE] ' .
                    'No se pudo encolar el recálculo ' .
                    'de la propiedad ' .
                    $propertyId . ': ' .
                    $e->getMessage()
            );
        }
    }

    private static function queueCompatibilityArchive(
        int $propertyId
    ): void {
        try {
            CompatibilityJobService::enqueuePropertyArchive(
                $propertyId
            );
        } catch (Throwable $e) {
            error_log(
                '[PROPERTY COMPATIBILITY QUEUE] ' .
                    'No se pudo encolar el archivado ' .
                    'de la propiedad ' .
                    $propertyId . ': ' .
                    $e->getMessage()
            );
        }
    }
    private static function validatePropertyPayload(array $data, bool $partial = false): array
    {
        $payload = [
            'title' => trim((string)($data['title'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'property_type' => trim((string)($data['property_type'] ?? '')),
            'price' => $data['price'] ?? null,
            'currency' => trim((string)($data['currency'] ?? 'USD')),

            'country_code' => trim((string)($data['country_code'] ?? '')),
            'country' => trim((string)($data['country'] ?? '')),
            'province' => trim((string)($data['province'] ?? '')),
            'city' => trim((string)($data['city'] ?? '')),
            'zone' => trim((string)($data['zone'] ?? '')),

            'address' => trim((string)($data['address'] ?? '')),
            'formatted_address' => trim((string)($data['formatted_address'] ?? '')),
            'postal_code' => trim((string)($data['postal_code'] ?? '')),
            'place_id' => trim((string)($data['place_id'] ?? '')),

            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,

            'total_area' => $data['total_area'] ?? null,
            'covered_area' => $data['covered_area'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? null,
            'bathrooms' => $data['bathrooms'] ?? null,
            'garages' => $data['garages'] ?? null,
            'antiquity' => $data['antiquity'] ?? null,
        ];

        $validTypes = ['house', 'apartment', 'land', 'commercial', 'office', 'warehouse', 'other'];
        if ($payload['property_type'] !== '' && !in_array($payload['property_type'], $validTypes, true)) {
            throw new Exception("Tipo de propiedad inválido");
        }

        if ($payload['currency'] !== '' && !in_array($payload['currency'], ['ARS', 'USD'], true)) {
            throw new Exception("Moneda inválida");
        }

        if (!$partial) {
            $required = [
                'title' => 'El título es obligatorio',
                'description' => 'La descripción es obligatoria',
                'property_type' => 'El tipo de propiedad es obligatorio',
                'price' => 'El precio es obligatorio',
                'country_code' => 'El país es obligatorio',
                'country' => 'El país es obligatorio',
                'province' => 'La provincia es obligatoria',
                'city' => 'La ciudad es obligatoria',
            ];

            foreach ($required as $field => $message) {
                if ($field === 'price') {
                    if ($payload['price'] === null || $payload['price'] === '' || !is_numeric($payload['price'])) {
                        throw new Exception($message);
                    }
                    continue;
                }

                if ($payload[$field] === '') {
                    throw new Exception($message);
                }
            }
        }

        return $payload;
    }

    public static function createDraft(int $userId, array $data): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);
        $payload = self::validatePropertyPayload($data, false);

        $st = $pdo->prepare("
            INSERT INTO properties (
                real_estate_id,
                created_by_user_id,
                updated_by_user_id,
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
                place_id,
                latitude,
                longitude,
                total_area,
                covered_area,
                bedrooms,
                bathrooms,
                garages,
                antiquity,
                status,
                is_visible
            ) VALUES (
                :real_estate_id,
                :created_by_user_id,
                :updated_by_user_id,
                :title,
                :description,
                :property_type,
                :price,
                :currency,
                :country_code,
                :country,
                :province,
                :city,
                :zone,
                :address,
                :formatted_address,
                :postal_code,
                :place_id,
                :latitude,
                :longitude,
                :total_area,
                :covered_area,
                :bedrooms,
                :bathrooms,
                :garages,
                :antiquity,
                'draft',
                0
            )
        ");

        $st->execute([
            'real_estate_id' => (int)$user['real_estate_id'],
            'created_by_user_id' => (int)$user['id'],
            'updated_by_user_id' => (int)$user['id'],
            'title' => $payload['title'],
            'description' => $payload['description'],
            'property_type' => $payload['property_type'],
            'price' => $payload['price'],
            'currency' => $payload['currency'],
            'country_code' => $payload['country_code'],
            'country' => $payload['country'],
            'province' => $payload['province'],
            'city' => $payload['city'],
            'zone' => $payload['zone'] !== '' ? $payload['zone'] : null,
            'address' => $payload['address'] !== '' ? $payload['address'] : null,
            'formatted_address' => $payload['formatted_address'] !== '' ? $payload['formatted_address'] : null,
            'postal_code' => $payload['postal_code'] !== '' ? $payload['postal_code'] : null,
            'place_id' => $payload['place_id'] !== '' ? $payload['place_id'] : null,
            'latitude' => $payload['latitude'] !== '' ? $payload['latitude'] : null,
            'longitude' => $payload['longitude'] !== '' ? $payload['longitude'] : null,
            'total_area' => $payload['total_area'] !== '' ? $payload['total_area'] : null,
            'covered_area' => $payload['covered_area'] !== '' ? $payload['covered_area'] : null,
            'bedrooms' => $payload['bedrooms'] !== '' ? $payload['bedrooms'] : null,
            'bathrooms' => $payload['bathrooms'] !== '' ? $payload['bathrooms'] : null,
            'garages' => $payload['garages'] !== '' ? $payload['garages'] : null,
            'antiquity' => $payload['antiquity'] !== '' ? $payload['antiquity'] : null,
        ]);

        $id = (int)$pdo->lastInsertId();

        self::replacePropertyAmenities(
            $id,
            $data['amenities'] ?? []
        );

        /*
 * Calculamos la calidad inicial incluso
 * mientras la propiedad está en borrador.
 *
 * Esto permitirá mostrar sugerencias al usuario
 * antes de que publique.
 */
        self::queueQualityRecalculation(
            $id
        );

        return self::getDetail(
            $userId,
            $id
        );
    }

    public static function getDetail(int $userId, int $propertyId): array
    {
        [, $property] = self::getOwnedProperty($userId, $propertyId);
        $pdo = self::db();

        $stImages = $pdo->prepare("
        SELECT id, property_id, file_path, sort_order, is_cover, created_at
        FROM property_images
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ");
        $stImages->execute(['property_id' => $propertyId]);
        $images = $stImages->fetchAll() ?: [];

        $images = array_map(function ($img) {
            $img['view_url'] = self::imageViewUrl(isset($img['id']) ? (int)$img['id'] : null);
            return $img;
        }, $images);

        $stReq = $pdo->prepare("
        SELECT *
        FROM property_requirements
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stReq->execute(['property_id' => $propertyId]);
        $requirements = $stReq->fetch() ?: null;

        $requirementPropertyTypes = [];
        $requirementLocations = [];
        $amenities = self::getPropertyAmenities($propertyId);

        if ($requirements && !empty($requirements['id'])) {
            $requirementId = (int)$requirements['id'];

            $stTypes = $pdo->prepare("
            SELECT property_type
            FROM property_requirement_property_types
            WHERE property_requirement_id = :property_requirement_id
            ORDER BY id ASC
        ");
            $stTypes->execute(['property_requirement_id' => $requirementId]);
            $requirementPropertyTypes = $stTypes->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $stLocations = $pdo->prepare("
            SELECT id, country_code, country, province, city, zone
            FROM property_requirement_locations
            WHERE property_requirement_id = :property_requirement_id
            ORDER BY id ASC
        ");
            $stLocations->execute(['property_requirement_id' => $requirementId]);
            $requirementLocations = $stLocations->fetchAll() ?: [];
        }
        /*
 * Último análisis determinístico de calidad
 * de la publicación.
 */
        $stQuality = $pdo->prepare("
    SELECT
        score,
        quality_level,
        basic_score,
        location_score,
        features_score,
        media_score,
        matchability_score,
        issues_json,
        suggestions_json,
        algorithm_version,
        analyzed_at
    FROM publication_quality_scores
    WHERE entity_type = 'property'
      AND entity_id = :entity_id
    LIMIT 1
");

        $stQuality->execute([
            'entity_id' => $propertyId,
        ]);

        $qualityRow = $stQuality->fetch() ?: null;

        $quality = null;

        if ($qualityRow) {
            $issues = json_decode(
                (string)($qualityRow['issues_json'] ?? '[]'),
                true
            );

            $suggestions = json_decode(
                (string)($qualityRow['suggestions_json'] ?? '[]'),
                true
            );

            $quality = [
                'score' =>
                (float)$qualityRow['score'],

                'quality_level' =>
                (string)$qualityRow['quality_level'],

                'sections' => [
                    'basic' => [
                        'score' =>
                        (float)$qualityRow['basic_score'],
                        'max_score' => 25,
                    ],

                    'location' => [
                        'score' =>
                        (float)$qualityRow['location_score'],
                        'max_score' => 20,
                    ],

                    'features' => [
                        'score' =>
                        (float)$qualityRow['features_score'],
                        'max_score' => 20,
                    ],

                    'media' => [
                        'score' =>
                        (float)$qualityRow['media_score'],
                        'max_score' => 15,
                    ],

                    'matchability' => [
                        'score' =>
                        (float)$qualityRow['matchability_score'],
                        'max_score' => 20,
                    ],
                ],

                'issues' =>
                is_array($issues)
                    ? $issues
                    : [],

                'suggestions' =>
                is_array($suggestions)
                    ? $suggestions
                    : [],

                'algorithm_version' =>
                (string)$qualityRow['algorithm_version'],

                'analyzed_at' =>
                $qualityRow['analyzed_at'],
            ];
        }
        return [
            'property' => $property,
            'images' => $images,
            'requirements' => $requirements,
            'requirement_property_types' => $requirementPropertyTypes,
            'requirement_locations' => $requirementLocations,
            'amenities' => $amenities,
            'quality' => $quality,
        ];
    }

    public static function updateDraft(
        int $userId,
        int $propertyId,
        array $data
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        if (
            !in_array(
                $property['status'],
                [
                    'draft',
                    'paused',
                    'archived',
                    'published',
                ],
                true
            )
        ) {
            throw new Exception(
                "La propiedad no puede editarse en su estado actual"
            );
        }

        $payload = self::validatePropertyPayload(
            $data,
            true
        );

        $pdo = self::db();

        $fields = [];

        $params = [
            'id' => $propertyId,
            'real_estate_id' =>
            (int)$user['real_estate_id'],
            'updated_by_user_id' =>
            (int)$user['id'],
        ];

        $map = [
            'title',
            'description',
            'property_type',
            'price',
            'currency',
            'country_code',
            'country',
            'province',
            'city',
            'zone',
            'address',
            'formatted_address',
            'postal_code',
            'place_id',
            'latitude',
            'longitude',
            'total_area',
            'covered_area',
            'bedrooms',
            'bathrooms',
            'garages',
            'antiquity',
        ];

        foreach ($map as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $fields[] = "{$field} = :{$field}";

            $value = $payload[$field];

            $params[$field] =
                $value === ''
                ? null
                : $value;
        }

        $fields[] =
            "updated_by_user_id = :updated_by_user_id";

        $pdo->beginTransaction();

        try {
            $sql = "
            UPDATE properties
            SET " . implode(', ', $fields) . "
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            if (array_key_exists('amenities', $data)) {
                self::replacePropertyAmenities(
                    $propertyId,
                    $data['amenities'] ?? []
                );
            }

            $pdo->commit();

            /*
         * Si ya estaba publicada, cualquier cambio en precio,
         * ubicación, características o amenities puede modificar
         * sus compatibilidades.
         */
            /*
 * La calidad se recalcula siempre,
 * incluso si todavía es borrador.
 */
            self::queueQualityRecalculation(
                $propertyId
            );

            /*
 * Las compatibilidades solamente necesitan
 * recalcularse cuando la propiedad está publicada.
 */
            if ($property['status'] === 'published') {
                self::queueCompatibilityRecalculation(
                    $propertyId
                );
            }

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function replaceRequirements(
        int $userId,
        int $propertyId,
        array $data
    ): array {
        [, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        $validCurrencies = ['ARS', 'USD'];

        $validTypes = [
            'house',
            'apartment',
            'land',
            'commercial',
            'office',
            'warehouse',
            'other',
        ];

        $validDifferenceDirections = [
            'a_favor',
            'en_contra',
            'indistinto',
        ];

        $validConditions = [
            'nuevo',
            'bueno',
            'regular',
            'a_refaccionar',
        ];

        $criteriaMode = trim(
            (string)($data['criteria_mode'] ?? 'open')
        );

        if (
            !in_array(
                $criteriaMode,
                ['open', 'criteria'],
                true
            )
        ) {
            throw new Exception(
                "criteria_mode inválido"
            );
        }

        $cashDifferenceDirection =
            isset($data['cash_difference_direction']) &&
            $data['cash_difference_direction'] !== ''
            ? trim(
                (string)$data['cash_difference_direction']
            )
            : null;

        if (
            $cashDifferenceDirection !== null &&
            !in_array(
                $cashDifferenceDirection,
                $validDifferenceDirections,
                true
            )
        ) {
            throw new Exception(
                "Dirección de diferencia inválida"
            );
        }

        $propertyCondition =
            isset($data['property_condition']) &&
            $data['property_condition'] !== ''
            ? trim(
                (string)$data['property_condition']
            )
            : null;

        if (
            $propertyCondition !== null &&
            !in_array(
                $propertyCondition,
                $validConditions,
                true
            )
        ) {
            throw new Exception(
                "Condición de propiedad inválida"
            );
        }

        $propertyTypes =
            $data['property_types'] ?? [];

        if (!is_array($propertyTypes)) {
            throw new Exception(
                "property_types debe ser un array"
            );
        }

        $propertyTypes = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn($type) =>
                        trim((string)$type),
                        $propertyTypes
                    )
                )
            )
        );

        foreach ($propertyTypes as $type) {
            if (!in_array($type, $validTypes, true)) {
                throw new Exception(
                    "Uno de los tipos de propiedad deseada es inválido"
                );
            }
        }

        $locations = $data['locations'] ?? [];

        if (!is_array($locations)) {
            throw new Exception(
                "locations debe ser un array"
            );
        }

        foreach ($locations as $location) {
            if (!is_array($location)) {
                throw new Exception(
                    "Cada location debe ser un objeto"
                );
            }

            $countryCode = trim(
                (string)($location['country_code'] ?? '')
            );

            $country = trim(
                (string)($location['country'] ?? '')
            );

            $province = trim(
                (string)($location['province'] ?? '')
            );

            if (
                $criteriaMode === 'criteria' &&
                (
                    $countryCode === '' ||
                    $country === '' ||
                    $province === ''
                )
            ) {
                throw new Exception(
                    "Cada ubicación debe incluir país, código de país y provincia"
                );
            }
        }

        $cashDifferenceCurrency = trim(
            (string)(
                $data['cash_difference_currency']
                ?? 'USD'
            )
        );

        $priceCurrency = trim(
            (string)(
                $data['price_currency']
                ?? 'USD'
            )
        );

        if (
            !in_array(
                $cashDifferenceCurrency,
                $validCurrencies,
                true
            )
        ) {
            throw new Exception(
                "Moneda de diferencia inválida"
            );
        }

        if (
            !in_array(
                $priceCurrency,
                $validCurrencies,
                true
            )
        ) {
            throw new Exception(
                "Moneda de búsqueda inválida"
            );
        }

        $values = [
            'criteria_mode' => $criteriaMode,
            'accepts_total_swap' =>
            !empty($data['accepts_total_swap']) ? 1 : 0,
            'accepts_swap_plus_cash' =>
            !empty($data['accepts_swap_plus_cash']) ? 1 : 0,
            'accepts_multiple_swap' =>
            !empty($data['accepts_multiple_swap']) ? 1 : 0,
            'accepts_open_proposals' =>
            !empty($data['accepts_open_proposals']) ? 1 : 0,
            'accepts_cash_only' =>
            !empty($data['accepts_cash_only']) ? 1 : 0,
            'cash_difference_direction' =>
            $cashDifferenceDirection,
            'cash_difference_min' =>
            $data['cash_difference_min'] ?? null,
            'cash_difference_max' =>
            $data['cash_difference_max'] ?? null,
            'cash_difference_currency' =>
            $cashDifferenceCurrency,
            'price_min' =>
            $data['price_min'] ?? null,
            'price_max' =>
            $data['price_max'] ?? null,
            'price_currency' =>
            $priceCurrency,
            'min_total_area' =>
            $data['min_total_area'] ?? null,
            'max_total_area' =>
            $data['max_total_area'] ?? null,
            'min_covered_area' =>
            $data['min_covered_area'] ?? null,
            'max_covered_area' =>
            $data['max_covered_area'] ?? null,
            'min_bedrooms' =>
            $data['min_bedrooms'] ?? null,
            'min_bathrooms' =>
            $data['min_bathrooms'] ?? null,
            'min_garages' =>
            $data['min_garages'] ?? null,
            'max_antiquity' =>
            $data['max_antiquity'] ?? null,
            'open_to_other_zones' =>
            !empty($data['open_to_other_zones']) ? 1 : 0,
            'notes' =>
            trim((string)($data['notes'] ?? '')) ?: null,
            'property_condition' =>
            $propertyCondition,
        ];

        $pdo->beginTransaction();

        try {
            $stCurrent = $pdo->prepare("
            SELECT id
            FROM property_requirements
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $stCurrent->execute([
                'property_id' => $propertyId,
            ]);

            $current = $stCurrent->fetch(
                PDO::FETCH_ASSOC
            );

            if ($current) {
                $requirementId =
                    (int)$current['id'];

                $stUpdate = $pdo->prepare("
                UPDATE property_requirements
                SET
                    criteria_mode = :criteria_mode,
                    accepts_total_swap =
                        :accepts_total_swap,
                    accepts_swap_plus_cash =
                        :accepts_swap_plus_cash,
                    accepts_multiple_swap =
                        :accepts_multiple_swap,
                    accepts_open_proposals =
                        :accepts_open_proposals,
                    accepts_cash_only =
                        :accepts_cash_only,
                    cash_difference_direction =
                        :cash_difference_direction,
                    cash_difference_min =
                        :cash_difference_min,
                    cash_difference_max =
                        :cash_difference_max,
                    cash_difference_currency =
                        :cash_difference_currency,
                    price_min = :price_min,
                    price_max = :price_max,
                    price_currency = :price_currency,
                    min_total_area = :min_total_area,
                    max_total_area = :max_total_area,
                    min_covered_area =
                        :min_covered_area,
                    max_covered_area =
                        :max_covered_area,
                    min_bedrooms = :min_bedrooms,
                    min_bathrooms = :min_bathrooms,
                    min_garages = :min_garages,
                    max_antiquity = :max_antiquity,
                    open_to_other_zones =
                        :open_to_other_zones,
                    notes = :notes,
                    property_condition =
                        :property_condition
                WHERE id = :id
                LIMIT 1
            ");

                $stUpdate->execute(
                    array_merge(
                        $values,
                        ['id' => $requirementId]
                    )
                );
            } else {
                $stInsert = $pdo->prepare("
                INSERT INTO property_requirements (
                    property_id,
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
                ) VALUES (
                    :property_id,
                    :criteria_mode,
                    :accepts_total_swap,
                    :accepts_swap_plus_cash,
                    :accepts_multiple_swap,
                    :accepts_open_proposals,
                    :accepts_cash_only,
                    :cash_difference_direction,
                    :cash_difference_min,
                    :cash_difference_max,
                    :cash_difference_currency,
                    :price_min,
                    :price_max,
                    :price_currency,
                    :min_total_area,
                    :max_total_area,
                    :min_covered_area,
                    :max_covered_area,
                    :min_bedrooms,
                    :min_bathrooms,
                    :min_garages,
                    :max_antiquity,
                    :open_to_other_zones,
                    :notes,
                    :property_condition
                )
            ");

                $stInsert->execute(
                    array_merge(
                        $values,
                        ['property_id' => $propertyId]
                    )
                );

                $requirementId =
                    (int)$pdo->lastInsertId();
            }

            $pdo->prepare("
            DELETE FROM property_requirement_locations
            WHERE property_requirement_id = :id
        ")->execute([
                'id' => $requirementId,
            ]);

            $pdo->prepare("
            DELETE FROM property_requirement_property_types
            WHERE property_requirement_id = :id
        ")->execute([
                'id' => $requirementId,
            ]);

            if ($criteriaMode === 'criteria') {
                $stLocation = $pdo->prepare("
                INSERT INTO property_requirement_locations (
                    property_requirement_id,
                    country_code,
                    country,
                    province,
                    city,
                    zone
                ) VALUES (
                    :property_requirement_id,
                    :country_code,
                    :country,
                    :province,
                    :city,
                    :zone
                )
            ");

                foreach ($locations as $location) {
                    $stLocation->execute([
                        'property_requirement_id' =>
                        $requirementId,
                        'country_code' =>
                        trim(
                            (string)(
                                $location['country_code']
                                ?? ''
                            )
                        ),
                        'country' =>
                        trim(
                            (string)(
                                $location['country']
                                ?? ''
                            )
                        ),
                        'province' =>
                        trim(
                            (string)(
                                $location['province']
                                ?? ''
                            )
                        ),
                        'city' =>
                        trim(
                            (string)(
                                $location['city']
                                ?? ''
                            )
                        ) ?: null,
                        'zone' =>
                        trim(
                            (string)(
                                $location['zone']
                                ?? ''
                            )
                        ) ?: null,
                    ]);
                }

                $stType = $pdo->prepare("
                INSERT INTO
                    property_requirement_property_types (
                        property_requirement_id,
                        property_type
                    )
                VALUES (
                    :property_requirement_id,
                    :property_type
                )
            ");

                foreach ($propertyTypes as $type) {
                    $stType->execute([
                        'property_requirement_id' =>
                        $requirementId,
                        'property_type' => $type,
                    ]);
                }
            }

            $pdo->commit();

            /*
 * Los criterios de intercambio forman parte
 * del potencial de matching de la publicación.
 */
            self::queueQualityRecalculation(
                $propertyId
            );

            if ($property['status'] === 'published') {
                self::queueCompatibilityRecalculation(
                    $propertyId
                );
            }

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function publish(
        int $userId,
        int $propertyId
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        if (
            !in_array(
                $property['status'],
                ['draft', 'paused', 'archived'],
                true
            )
        ) {
            throw new Exception(
                "La propiedad no puede publicarse en su estado actual"
            );
        }

        $requiredFields = [
            'title',
            'description',
            'property_type',
            'price',
            'country_code',
            'country',
            'province',
            'city',
        ];

        foreach ($requiredFields as $field) {
            if (
                empty($property[$field]) &&
                $property[$field] !== '0'
            ) {
                throw new Exception(
                    "Faltan datos obligatorios para publicar"
                );
            }
        }

        $stImages = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM property_images
        WHERE property_id = :property_id
          AND deleted_at IS NULL
    ");

        $stImages->execute([
            'property_id' => $propertyId,
        ]);

        $imageCount = (int)(
            $stImages->fetchColumn() ?: 0
        );

        if ($imageCount < 1) {
            throw new Exception(
                "Debés subir al menos una imagen para publicar"
            );
        }

        $stRequirements = $pdo->prepare("
        SELECT *
        FROM property_requirements
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stRequirements->execute([
            'property_id' => $propertyId,
        ]);

        $requirements = $stRequirements->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$requirements) {
            throw new Exception(
                "Debés completar los criterios de intercambio"
            );
        }

        $hasAnyMode =
            (int)$requirements['accepts_total_swap'] === 1 ||
            (int)$requirements['accepts_swap_plus_cash'] === 1 ||
            (int)$requirements['accepts_multiple_swap'] === 1 ||
            (int)$requirements['accepts_open_proposals'] === 1 ||
            (int)$requirements['accepts_cash_only'] === 1;

        if (!$hasAnyMode) {
            throw new Exception(
                "Debés definir al menos una modalidad de intercambio"
            );
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $property['status'];

            $st = $pdo->prepare("
            UPDATE properties
            SET
                status = 'published',
                is_visible = 1,
                published_at = CASE
                    WHEN published_at IS NULL
                        THEN NOW()
                    ELSE published_at
                END,
                paused_at = NULL,
                archived_at = NULL,
                updated_by_user_id =
                    :updated_by_user_id
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'updated_by_user_id' =>
                (int)$user['id'],
                'id' => $propertyId,
                'real_estate_id' =>
                (int)$user['real_estate_id'],
            ]);

            $hist = $pdo->prepare("
            INSERT INTO property_status_history (
                property_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :property_id,
                :old_status,
                'published',
                :changed_by_user_id,
                NULL,
                'user'
            )
        ");

            $hist->execute([
                'property_id' => $propertyId,
                'old_status' => $oldStatus,
                'changed_by_user_id' =>
                (int)$user['id'],
            ]);

            $pdo->commit();
            self::queueQualityRecalculation(
                $propertyId
            );

            self::queueCompatibilityRecalculation(
                $propertyId
            );

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
    public static function pause(
        int $userId,
        int $propertyId
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        if ($property['status'] !== 'published') {
            throw new Exception(
                "Solo se pueden pausar propiedades publicadas"
            );
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
            UPDATE properties
            SET
                status = 'paused',
                is_visible = 0,
                paused_at = NOW(),
                updated_by_user_id =
                    :updated_by_user_id
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'updated_by_user_id' =>
                (int)$user['id'],
                'id' => $propertyId,
                'real_estate_id' =>
                (int)$user['real_estate_id'],
            ]);

            $hist = $pdo->prepare("
            INSERT INTO property_status_history (
                property_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :property_id,
                'published',
                'paused',
                :changed_by_user_id,
                NULL,
                'user'
            )
        ");

            $hist->execute([
                'property_id' => $propertyId,
                'changed_by_user_id' =>
                (int)$user['id'],
            ]);

            $pdo->commit();

            self::queueCompatibilityArchive(
                $propertyId
            );

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function archive(
        int $userId,
        int $propertyId
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        if (
            !in_array(
                $property['status'],
                ['draft', 'paused', 'published'],
                true
            )
        ) {
            throw new Exception(
                "La propiedad no puede archivarse en su estado actual"
            );
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $property['status'];

            $st = $pdo->prepare("
            UPDATE properties
            SET
                status = 'archived',
                is_visible = 0,
                archived_at = NOW(),
                updated_by_user_id =
                    :updated_by_user_id
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'updated_by_user_id' =>
                (int)$user['id'],
                'id' => $propertyId,
                'real_estate_id' =>
                (int)$user['real_estate_id'],
            ]);

            $hist = $pdo->prepare("
            INSERT INTO property_status_history (
                property_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :property_id,
                :old_status,
                'archived',
                :changed_by_user_id,
                NULL,
                'user'
            )
        ");

            $hist->execute([
                'property_id' => $propertyId,
                'old_status' => $oldStatus,
                'changed_by_user_id' =>
                (int)$user['id'],
            ]);

            $pdo->commit();

            self::queueCompatibilityArchive(
                $propertyId
            );

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function close(
        int $userId,
        int $propertyId,
        string $closingType
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        $validClosingTypes = [
            'sold',
            'exchanged',
            'withdrawn',
            'expired',
        ];

        if (
            !in_array(
                $closingType,
                $validClosingTypes,
                true
            )
        ) {
            throw new Exception(
                "Tipo de cierre inválido"
            );
        }

        if (
            !in_array(
                $property['status'],
                ['published', 'paused'],
                true
            )
        ) {
            throw new Exception(
                "La propiedad no puede cerrarse en su estado actual"
            );
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $property['status'];

            $st = $pdo->prepare("
            UPDATE properties
            SET
                status = 'closed',
                is_visible = 0,
                closing_type = :closing_type,
                updated_by_user_id =
                    :updated_by_user_id
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'closing_type' => $closingType,
                'updated_by_user_id' =>
                (int)$user['id'],
                'id' => $propertyId,
                'real_estate_id' =>
                (int)$user['real_estate_id'],
            ]);

            $hist = $pdo->prepare("
            INSERT INTO property_status_history (
                property_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :property_id,
                :old_status,
                'closed',
                :changed_by_user_id,
                :change_reason,
                'user'
            )
        ");

            $hist->execute([
                'property_id' => $propertyId,
                'old_status' => $oldStatus,
                'changed_by_user_id' =>
                (int)$user['id'],
                'change_reason' => $closingType,
            ]);

            $pdo->commit();

            self::queueCompatibilityArchive(
                $propertyId
            );

            return self::getDetail(
                $userId,
                $propertyId
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function listMyProperties(int $userId, array $filters = []): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $where = [
            "p.real_estate_id = :real_estate_id",
            "p.deleted_at IS NULL",
        ];

        $params = [
            'real_estate_id' => (int)$user['real_estate_id'],
        ];

        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[] = "(p.title LIKE :q OR p.city LIKE :q OR p.zone LIKE :q OR CAST(p.id AS CHAR) LIKE :q)";
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $limit = (int)($filters['limit'] ?? 5);
        if ($limit <= 0) $limit = 5;
        if ($limit > 50) $limit = 50;

        $page = (int)($filters['page'] ?? 1);
        if ($page <= 0) $page = 1;

        $offset = ($page - 1) * $limit;

        $countSql = "
        SELECT COUNT(*) AS total
        FROM properties p
        WHERE " . implode(" AND ", $where);

        $stCount = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $stCount->bindValue(':' . $key, $value);
        }
        $stCount->execute();

        $total = (int)($stCount->fetch()['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));

        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "
        SELECT
            p.id,
            p.title,
            p.property_type,
            p.price,
            p.currency,
            p.country,
            p.province,
            p.city,
            p.zone,
            p.total_area,
            p.covered_area,
            p.bedrooms,
            p.bathrooms,
            p.garages,
            p.status,
            p.created_at,
            p.published_at,

            pr.criteria_mode,
            pr.accepts_total_swap,
            pr.accepts_swap_plus_cash,
            pr.accepts_multiple_swap,
            pr.accepts_open_proposals,
            pr.accepts_cash_only,
            pr.notes AS requirement_notes,

            (
                SELECT pi.id
                FROM property_images pi
                WHERE pi.property_id = p.id
                  AND pi.deleted_at IS NULL
                ORDER BY pi.is_cover DESC, pi.sort_order ASC
                LIMIT 1
            ) AS cover_image_id

        FROM properties p
        LEFT JOIN property_requirements pr
            ON pr.property_id = p.id
           AND pr.deleted_at IS NULL
        WHERE " . implode(" AND ", $where) . "
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

        $st = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $st->bindValue(':' . $key, $value);
        }

        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);

        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $items = array_map(function ($row) {
            $row['cover_image_id'] = !empty($row['cover_image_id'])
                ? (int)$row['cover_image_id']
                : null;

            $row['cover_image_url'] = self::imageViewUrl($row['cover_image_id']);

            return $row;
        }, $rows);

        $from = $total > 0 ? $offset + 1 : 0;
        $to = $total > 0 ? min($offset + $limit, $total) : 0;

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'pages' => $pages,
                'from' => $from,
                'to' => $to,
                'total' => $total,
                'limit' => $limit,
            ],
        ];
    }

    public static function delete(
        int $userId,
        int $propertyId
    ): array {
        [$user, $property] = self::getOwnedProperty(
            $userId,
            $propertyId
        );

        $pdo = self::db();

        if (
            !in_array(
                $property['status'],
                [
                    'draft',
                    'published',
                    'paused',
                    'archived',
                    'closed',
                ],
                true
            )
        ) {
            throw new Exception(
                "La propiedad no puede eliminarse en su estado actual"
            );
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $property['status'];

            $st = $pdo->prepare("
            UPDATE properties
            SET
                deleted_at = NOW(),
                is_visible = 0,
                updated_by_user_id =
                    :updated_by_user_id
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'updated_by_user_id' =>
                (int)$user['id'],
                'id' => $propertyId,
                'real_estate_id' =>
                (int)$user['real_estate_id'],
            ]);

            $hist = $pdo->prepare("
            INSERT INTO property_status_history (
                property_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :property_id,
                :old_status,
                'deleted',
                :changed_by_user_id,
                'user_deleted',
                'user'
            )
        ");

            $hist->execute([
                'property_id' => $propertyId,
                'old_status' => $oldStatus,
                'changed_by_user_id' =>
                (int)$user['id'],
            ]);

            $pdo->commit();

            self::queueCompatibilityArchive(
                $propertyId
            );

            return [
                'ok' => true,
                'property_id' => $propertyId,
                'deleted_at' =>
                date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function listExploreProperties(int $userId, array $filters = []): array
    {
        $pdo = self::db();
        $user = self::getValidViewerUser($userId);
        $where = [
            "p.deleted_at IS NULL",
            "p.status = 'published'",
            "p.is_visible = 1",
        ];

        $params = [];

        if (!empty($user['real_estate_id'])) {
            $where[] = "p.real_estate_id <> :real_estate_id";
            $params['real_estate_id'] = (int)$user['real_estate_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = "(
            p.title LIKE :q
            OR p.city LIKE :q
            OR p.zone LIKE :q
            OR p.province LIKE :q
            OR CAST(p.id AS CHAR) LIKE :q
        )";
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        if (!empty($filters['property_type'])) {
            $where[] = "p.property_type = :property_type";
            $params['property_type'] = trim((string)$filters['property_type']);
        }

        $limit = (int)($filters['limit'] ?? 6);
        if ($limit <= 0) $limit = 6;
        if ($limit > 50) $limit = 50;

        $page = (int)($filters['page'] ?? 1);
        if ($page <= 0) $page = 1;

        $offset = ($page - 1) * $limit;

        $countSql = "
        SELECT COUNT(*) AS total
        FROM properties p
        WHERE " . implode(" AND ", $where);

        $stCount = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $stCount->bindValue(':' . $key, $value);
        }
        $stCount->execute();

        $total = (int)($stCount->fetch()['total'] ?? 0);
        $pages = max(1, (int)ceil($total / $limit));

        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "
        SELECT
            p.id,
            p.real_estate_id,
            p.title,
            p.description,
            p.property_type,
            p.price,
            p.currency,
            p.country,
            p.province,
            p.city,
            p.zone,
            p.total_area,
            p.covered_area,
            p.bedrooms,
            p.bathrooms,
            p.garages,
            p.status,
            p.created_at,
            p.published_at,

            pr.criteria_mode,
            pr.accepts_total_swap,
            pr.accepts_swap_plus_cash,
            pr.accepts_multiple_swap,
            pr.accepts_open_proposals,
            pr.accepts_cash_only,
            pr.notes AS requirement_notes,

            (
                SELECT pi.id
                FROM property_images pi
                WHERE pi.property_id = p.id
                  AND pi.deleted_at IS NULL
                ORDER BY pi.is_cover DESC, pi.sort_order ASC, pi.id ASC
                LIMIT 1
            ) AS cover_image_id

        FROM properties p
        LEFT JOIN property_requirements pr
            ON pr.property_id = p.id
           AND pr.deleted_at IS NULL
        WHERE " . implode(" AND ", $where) . "
        ORDER BY p.published_at DESC, p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

        $st = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $st->bindValue(':' . $key, $value);
        }

        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);

        $st->execute();
        $rows = $st->fetchAll() ?: [];

        if (!$rows) {
            return [
                'items' => [],
                'meta' => [
                    'page' => $page,
                    'pages' => $pages,
                    'from' => 0,
                    'to' => 0,
                    'total' => $total,
                    'limit' => $limit,
                ],
            ];
        }

        $propertyIds = array_map(fn($row) => (int)$row['id'], $rows);

        $placeholders = [];
        $imageParams = [];
        foreach ($propertyIds as $index => $propertyId) {
            $ph = ":property_id_$index";
            $placeholders[] = $ph;
            $imageParams[$ph] = $propertyId;
        }

        $imagesSql = "
        SELECT
            pi.id,
            pi.property_id,
            pi.sort_order,
            pi.is_cover,
            pi.created_at
        FROM property_images pi
        WHERE pi.deleted_at IS NULL
          AND pi.property_id IN (" . implode(", ", $placeholders) . ")
        ORDER BY pi.property_id ASC, pi.is_cover DESC, pi.sort_order ASC, pi.id ASC
    ";

        $stImages = $pdo->prepare($imagesSql);
        foreach ($imageParams as $ph => $value) {
            $stImages->bindValue($ph, $value, PDO::PARAM_INT);
        }
        $stImages->execute();

        $imageRows = $stImages->fetchAll() ?: [];

        $imagesByProperty = [];
        foreach ($imageRows as $img) {
            $propertyId = (int)$img['property_id'];
            $imageId = (int)$img['id'];

            $imagesByProperty[$propertyId][] = [
                'id' => $imageId,
                'property_id' => $propertyId,
                'sort_order' => (int)($img['sort_order'] ?? 0),
                'is_cover' => (int)($img['is_cover'] ?? 0),
                'created_at' => $img['created_at'] ?? null,
                'view_url' => self::imageViewUrl($imageId),
            ];
        }

        $items = array_map(function ($row) use ($imagesByProperty) {
            $propertyId = (int)$row['id'];

            $row['cover_image_id'] = !empty($row['cover_image_id'])
                ? (int)$row['cover_image_id']
                : null;

            $row['cover_image_url'] = self::imageViewUrl($row['cover_image_id']);
            $row['images'] = $imagesByProperty[$propertyId] ?? [];

            return $row;
        }, $rows);

        $from = $total > 0 ? $offset + 1 : 0;
        $to = $total > 0 ? min($offset + $limit, $total) : 0;

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'pages' => $pages,
                'from' => $from,
                'to' => $to,
                'total' => $total,
                'limit' => $limit,
            ],
        ];
    }
    public static function getExploreDetail(int $userId, int $propertyId): array
    {
        $pdo = self::db();
        $user = self::getValidViewerUser($userId);

        $where = [
            "id = :id",
            "status = 'published'",
            "is_visible = 1",
            "deleted_at IS NULL",
        ];

        $params = [
            'id' => $propertyId,
        ];

        if (!empty($user['real_estate_id'])) {
            $where[] = "real_estate_id <> :real_estate_id";
            $params['real_estate_id'] = (int)$user['real_estate_id'];
        }

        $st = $pdo->prepare("
    SELECT *
    FROM properties
    WHERE " . implode(" AND ", $where) . "
    LIMIT 1
");

        $st->execute($params);
        $property = $st->fetch();

        if (!$property) {
            throw new Exception("Propiedad no encontrada");
        }

        $stImages = $pdo->prepare("
        SELECT id, property_id, file_path, sort_order, is_cover, created_at
        FROM property_images
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ");
        $stImages->execute(['property_id' => $propertyId]);
        $images = $stImages->fetchAll() ?: [];

        $images = array_map(function ($img) {
            $img['view_url'] = self::imageViewUrl(isset($img['id']) ? (int)$img['id'] : null);
            return $img;
        }, $images);

        $stReq = $pdo->prepare("
        SELECT *
        FROM property_requirements
        WHERE property_id = :property_id
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stReq->execute(['property_id' => $propertyId]);
        $requirements = $stReq->fetch() ?: null;

        $requirementPropertyTypes = [];
        $requirementLocations = [];
        $amenities = self::getPropertyAmenities($propertyId);

        if ($requirements && !empty($requirements['id'])) {
            $requirementId = (int)$requirements['id'];

            $stTypes = $pdo->prepare("
            SELECT property_type
            FROM property_requirement_property_types
            WHERE property_requirement_id = :property_requirement_id
            ORDER BY id ASC
        ");
            $stTypes->execute(['property_requirement_id' => $requirementId]);
            $requirementPropertyTypes = $stTypes->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $stLocations = $pdo->prepare("
            SELECT id, country_code, country, province, city, zone
            FROM property_requirement_locations
            WHERE property_requirement_id = :property_requirement_id
            ORDER BY id ASC
        ");
            $stLocations->execute(['property_requirement_id' => $requirementId]);
            $requirementLocations = $stLocations->fetchAll() ?: [];
        }

        return [
            'property' => $property,
            'images' => $images,
            'requirements' => $requirements,
            'requirement_property_types' => $requirementPropertyTypes,
            'requirement_locations' => $requirementLocations,
            'access' => [
                'can_edit' => false,
                'can_publish' => false,
                'can_pause' => false,
                'can_archive' => false,
                'can_close' => false,
                'can_delete' => false,
            ],
            'amenities' => $amenities,
        ];
    }
}
