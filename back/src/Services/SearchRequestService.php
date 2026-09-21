<?php

namespace App\Services;

use App\DB;
use PDO;
use Exception;
use Throwable;
use App\Services\AI\SearchRequestQualityService;

class SearchRequestService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
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

        if (!in_array((int)$user['role'], [2, 3], true)) {
            throw new Exception("No tenés permisos para administrar búsquedas");
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

        if (!in_array((int)$user['role'], [2, 3, 4], true)) {
            throw new Exception("No tenés permisos para ver búsquedas");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        return $user;
    }
    private static function getOwnedSearchRequestRow(int $userId, int $id): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $st = $pdo->prepare("
            SELECT *
            FROM search_requests
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            'id' => $id,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $item = $st->fetch();

        if (!$item) {
            throw new Exception("Búsqueda no encontrada");
        }

        return [$user, $item];
    }

    public static function listMySearchRequests(
        int $userId,
        array $filters = []
    ): array {
        $pdo = self::db();

        /*
     * Esta sección permite administrar búsquedas, por lo que
     * usamos los mismos permisos que para crear y editarlas.
     */
        $user = self::getValidPublisherUser($userId);

        $where = [
            "sr.deleted_at IS NULL",
            "sr.real_estate_id = :real_estate_id",
        ];

        $params = [
            'real_estate_id' => (int)$user['real_estate_id'],
        ];

        /*
     * El estado es opcional. Si no se envía, devuelve todas
     * las búsquedas propias no eliminadas.
     */
        if (!empty($filters['status'])) {
            $allowedStatuses = [
                'draft',
                'pending_review',
                'published',
                'paused',
                'rejected',
                'archived',
                'closed',
            ];

            $status = trim((string)$filters['status']);

            if (in_array($status, $allowedStatuses, true)) {
                $where[] = "sr.status = :status";
                $params['status'] = $status;
            }
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);

            if ($q !== '') {
                $where[] = "(
                sr.title LIKE :q
                OR sr.description LIKE :q
                OR sr.country LIKE :q
                OR sr.province LIKE :q
                OR sr.city LIKE :q
                OR sr.zone LIKE :q
                OR CAST(sr.id AS CHAR) LIKE :q
            )";

                $params['q'] = '%' . $q . '%';
            }
        }

        $limit = (int)($filters['limit'] ?? 20);

        if ($limit <= 0) {
            $limit = 20;
        }

        if ($limit > 100) {
            $limit = 100;
        }

        $page = (int)($filters['page'] ?? 1);

        if ($page <= 0) {
            $page = 1;
        }

        $offset = ($page - 1) * $limit;

        $countSql = "
        SELECT COUNT(*) AS total
        FROM search_requests sr
        WHERE " . implode(" AND ", $where);

        $stCount = $pdo->prepare($countSql);

        foreach ($params as $key => $value) {
            $stCount->bindValue(
                ':' . $key,
                $value
            );
        }

        $stCount->execute();

        $total = (int)(
            $stCount->fetchColumn() ?: 0
        );

        /*
     * Cuando no hay resultados mantenemos una sola página
     * para conservar el contrato esperado por el frontend.
     */
        $pages = max(
            1,
            (int)ceil($total / $limit)
        );

        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "
        SELECT
            sr.*,

            (
                SELECT GROUP_CONCAT(
                    DISTINCT srpt.property_type
                    ORDER BY srpt.property_type
                    SEPARATOR ','
                )
                FROM search_request_property_types srpt
                WHERE srpt.search_request_id = sr.id
            ) AS property_types,

            (
                SELECT COUNT(*)
                FROM search_request_property_types srpt
                WHERE srpt.search_request_id = sr.id
            ) AS property_types_count,

            (
                SELECT GROUP_CONCAT(
                    DISTINCT sra.amenity_code
                    ORDER BY sra.amenity_code
                    SEPARATOR ','
                )
                FROM search_request_amenities sra
                WHERE sra.search_request_id = sr.id
                  AND sra.deleted_at IS NULL
            ) AS amenities,

            (
                SELECT COUNT(*)
                FROM search_request_amenities sra
                WHERE sra.search_request_id = sr.id
                  AND sra.deleted_at IS NULL
            ) AS amenities_count

        FROM search_requests sr

        WHERE " . implode(" AND ", $where) . "

        ORDER BY
            CASE sr.status
                WHEN 'published' THEN 1
                WHEN 'draft' THEN 2
                WHEN 'paused' THEN 3
                WHEN 'archived' THEN 4
                WHEN 'closed' THEN 5
                ELSE 6
            END ASC,
            sr.updated_at DESC,
            sr.created_at DESC

        LIMIT :limit
        OFFSET :offset
    ";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                ':' . $key,
                $value
            );
        }

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $items = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        $from = $total > 0
            ? $offset + 1
            : 0;

        $to = $total > 0
            ? min($offset + $limit, $total)
            : 0;

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
    public static function getDetail(int $userId, int $id): array
    {
        [$user, $item] = self::getOwnedSearchRequestRow($userId, $id);
        $pdo = self::db();

        $item['property_types'] = self::getPropertyTypes($pdo, (int)$item['id']);
        $item['amenities'] = self::getAmenities($pdo, (int)$item['id']);
        $item['exchange_offers'] =
            self::getExchangeOffers(
                $pdo,
                (int)$item['id']
            );
        $quality =
            SearchRequestQualityService::analyze(
                $id
            );
        return [
            'search_request' => $item,
            'property_types' => $item['property_types'],
            'amenities' => $item['amenities'],
            'exchange_offers' =>
            $item['exchange_offers'],
            'access' => [
                'can_edit' => in_array($item['status'], ['draft', 'paused', 'archived', 'published'], true),
                'can_publish' => in_array($item['status'], ['draft', 'paused', 'archived'], true),
                'can_pause' => $item['status'] === 'published',
                'can_archive' => in_array($item['status'], ['draft', 'paused', 'published'], true),
                'can_delete' => $item['status'] !== 'deleted',
            ],
            'quality' => $quality,
        ];
    }
    private static function getExchangeOffers(
        PDO $pdo,
        int $searchRequestId
    ): array {
        $st = $pdo->prepare("
        SELECT
            seo.*,
            p.title AS property_title,
            p.price AS current_price,
            p.currency AS current_currency

        FROM search_request_exchange_offers seo

        LEFT JOIN properties p
            ON p.id = seo.property_id
            AND p.deleted_at IS NULL

        WHERE seo.search_request_id = :search_request_id
          AND seo.deleted_at IS NULL

        ORDER BY seo.id ASC
    ");

        $st->execute([
            'search_request_id' => $searchRequestId,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function syncExchangeOffers(
        PDO $pdo,
        int $searchRequestId,
        int $realEstateId,
        mixed $offers
    ): void {
        if (!is_array($offers)) {
            throw new Exception(
                'Los bienes ofrecidos deben enviarse como una lista',
                422
            );
        }

        $allowedOfferTypes = [
            'property',
            'vehicle',
            'cash',
            'other',
        ];

        $allowedPropertyTypes = [
            'house',
            'apartment',
            'land',
            'commercial',
            'office',
            'warehouse',
            'country_house',
            'farm',
            'garage',
            'other',
        ];

        $allowedVehicleTypes = [
            'car',
            'motorcycle',
            'other',
        ];

        $allowedCurrencies = [
            'ARS',
            'USD',
        ];

        $allowedCountryCodes = [
            'AR',
            'US',
            'IT',
        ];

        $stDelete = $pdo->prepare("
        DELETE FROM search_request_exchange_offers
        WHERE search_request_id = :search_request_id
    ");

        $stDelete->execute([
            'search_request_id' => $searchRequestId,
        ]);

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
            total_area,
            covered_area,
            bedrooms,
            bathrooms,
            garages,
            antiquity
        FROM properties
        WHERE id = :id
          AND real_estate_id = :real_estate_id
          AND deleted_at IS NULL
          AND status = 'published'
          AND is_visible = 1
        LIMIT 1
    ");

        $stInsert = $pdo->prepare("
        INSERT INTO search_request_exchange_offers (
            search_request_id,
            offer_type,
            property_id,
            title,
            description,
            property_type,
            vehicle_type,
            vehicle_brand,
            vehicle_model,
            vehicle_year,
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
            antiquity
        ) VALUES (
            :search_request_id,
            :offer_type,
            :property_id,
            :title,
            :description,
            :property_type,
            :vehicle_type,
            :vehicle_brand,
            :vehicle_model,
            :vehicle_year,
            :estimated_price,
            :currency,
            :country_code,
            :country,
            :province,
            :city,
            :zone,
            :total_area,
            :covered_area,
            :bedrooms,
            :bathrooms,
            :garages,
            :antiquity
        )
    ");

        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                throw new Exception(
                    'Uno de los bienes ofrecidos tiene un formato inválido',
                    422
                );
            }

            $offerTypeValue =
                $offer['offer_type'] ?? null;

            if (!is_string($offerTypeValue)) {
                throw new Exception(
                    'El tipo de bien ofrecido tiene un formato inválido',
                    422
                );
            }

            $offerType = trim($offerTypeValue);

            if (
                !in_array(
                    $offerType,
                    $allowedOfferTypes,
                    true
                )
            ) {
                throw new Exception(
                    'El tipo de bien ofrecido no es válido',
                    422
                );
            }

            $propertyId = null;
            $property = null;

            if (
                $offerType === 'property' &&
                array_key_exists('property_id', $offer) &&
                $offer['property_id'] !== null &&
                $offer['property_id'] !== ''
            ) {
                $validatedPropertyId = filter_var(
                    $offer['property_id'],
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1,
                        ],
                    ]
                );

                if ($validatedPropertyId === false) {
                    throw new Exception(
                        'La propiedad ofrecida tiene un identificador inválido',
                        422
                    );
                }

                $propertyId =
                    (int)$validatedPropertyId;

                $stProperty->execute([
                    'id' => $propertyId,
                    'real_estate_id' => $realEstateId,
                ]);

                $property =
                    $stProperty->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$property) {
                    throw new Exception(
                        'La propiedad ofrecida en permuta no es válida',
                        422
                    );
                }
            }

            $title =
                $property['title']
                ?? self::nullableString(
                    $offer['title'] ?? null,
                    'El título del bien ofrecido'
                );

            $description =
                $property['description']
                ?? self::nullableString(
                    $offer['description'] ?? null,
                    'La descripción del bien ofrecido'
                );

            $propertyType =
                $property['property_type']
                ?? self::nullableString(
                    $offer['property_type'] ?? null,
                    'El tipo de propiedad ofrecida'
                );

            if (
                $propertyType !== null &&
                !in_array(
                    $propertyType,
                    $allowedPropertyTypes,
                    true
                )
            ) {
                throw new Exception(
                    'El tipo de propiedad ofrecida no es válido',
                    422
                );
            }

            $estimatedPrice =
                self::nullableNumber(
                    $property['price']
                        ?? ($offer['estimated_price'] ?? null),
                    'El valor estimado del bien ofrecido'
                );

            $currency =
                $property['currency']
                ?? ($offer['currency'] ?? 'USD');

            if (
                !is_string($currency) ||
                !in_array(
                    $currency,
                    $allowedCurrencies,
                    true
                )
            ) {
                throw new Exception(
                    'La moneda del bien ofrecido no es válida',
                    422
                );
            }

            $vehicleType =
                $offerType === 'vehicle'
                ? self::nullableString(
                    $offer['vehicle_type'] ?? null,
                    'El tipo de vehículo'
                )
                : null;

            if (
                $offerType === 'vehicle' &&
                !in_array(
                    $vehicleType,
                    $allowedVehicleTypes,
                    true
                )
            ) {
                throw new Exception(
                    'El tipo de vehículo no es válido',
                    422
                );
            }

            $vehicleBrand =
                $offerType === 'vehicle'
                ? self::nullableString(
                    $offer['vehicle_brand'] ?? null,
                    'La marca del vehículo'
                )
                : null;

            $vehicleModel =
                $offerType === 'vehicle'
                ? self::nullableString(
                    $offer['vehicle_model'] ?? null,
                    'El modelo del vehículo'
                )
                : null;

            $vehicleYear =
                $offerType === 'vehicle'
                ? self::nullableInt(
                    $offer['vehicle_year'] ?? null,
                    'El año del vehículo',
                    1900
                )
                : null;

            if (
                $vehicleYear !== null &&
                $vehicleYear >
                ((int)date('Y') + 1)
            ) {
                throw new Exception(
                    'El año del vehículo no es válido',
                    422
                );
            }

            if (
                in_array(
                    $offerType,
                    ['cash', 'vehicle', 'other'],
                    true
                ) &&
                (
                    $estimatedPrice === null ||
                    $estimatedPrice <= 0
                )
            ) {
                throw new Exception(
                    'Indicá un valor estimado válido para la oferta',
                    422
                );
            }

            if (
                $offerType === 'property' &&
                $propertyId === null
            ) {
                if ($propertyType === null) {
                    throw new Exception(
                        'Indicá el tipo de propiedad ofrecida',
                        422
                    );
                }

                if ($description === null) {
                    throw new Exception(
                        'Indicá una descripción para la propiedad ofrecida',
                        422
                    );
                }

                if (
                    $estimatedPrice === null ||
                    $estimatedPrice <= 0
                ) {
                    throw new Exception(
                        'Indicá un valor estimado válido para la propiedad ofrecida',
                        422
                    );
                }
            }

            $countryCode =
                $property['country_code']
                ?? self::nullableString(
                    $offer['country_code'] ?? null,
                    'El código de país del bien ofrecido'
                );

            if (
                $countryCode !== null &&
                !in_array(
                    $countryCode,
                    $allowedCountryCodes,
                    true
                )
            ) {
                throw new Exception(
                    'El país del bien ofrecido no es válido',
                    422
                );
            }

            $country =
                $property['country']
                ?? self::nullableString(
                    $offer['country'] ?? null,
                    'El país del bien ofrecido'
                );

            $province =
                $property['province']
                ?? self::nullableString(
                    $offer['province'] ?? null,
                    'La provincia del bien ofrecido'
                );

            $city =
                $property['city']
                ?? self::nullableString(
                    $offer['city'] ?? null,
                    'La ciudad del bien ofrecido'
                );

            $zone =
                $property['zone']
                ?? self::nullableString(
                    $offer['zone'] ?? null,
                    'La zona del bien ofrecido'
                );

            $totalArea =
                self::nullableNumber(
                    $property['total_area']
                        ?? ($offer['total_area'] ?? null),
                    'La superficie total del bien ofrecido'
                );

            $coveredArea =
                self::nullableNumber(
                    $property['covered_area']
                        ?? ($offer['covered_area'] ?? null),
                    'La superficie cubierta del bien ofrecido'
                );

            if (
                $totalArea !== null &&
                $coveredArea !== null &&
                $coveredArea > $totalArea
            ) {
                throw new Exception(
                    'La superficie cubierta del bien ofrecido no puede superar la superficie total',
                    422
                );
            }

            $bedrooms =
                self::nullableInt(
                    $property['bedrooms']
                        ?? ($offer['bedrooms'] ?? null),
                    'La cantidad de dormitorios del bien ofrecido'
                );

            $bathrooms =
                self::nullableInt(
                    $property['bathrooms']
                        ?? ($offer['bathrooms'] ?? null),
                    'La cantidad de baños del bien ofrecido'
                );

            $garages =
                self::nullableInt(
                    $property['garages']
                        ?? ($offer['garages'] ?? null),
                    'La cantidad de cocheras del bien ofrecido'
                );

            $antiquity =
                self::nullableInt(
                    $property['antiquity']
                        ?? ($offer['antiquity'] ?? null),
                    'La antigüedad del bien ofrecido'
                );

            $stInsert->execute([
                'search_request_id' =>
                $searchRequestId,

                'offer_type' =>
                $offerType,

                'property_id' =>
                $propertyId,

                'title' =>
                $title,

                'description' =>
                $description,

                'property_type' =>
                $propertyType,

                'vehicle_type' =>
                $vehicleType,

                'vehicle_brand' =>
                $vehicleBrand,

                'vehicle_model' =>
                $vehicleModel,

                'vehicle_year' =>
                $vehicleYear,

                'estimated_price' =>
                $estimatedPrice,

                'currency' =>
                $currency,

                'country_code' =>
                $countryCode,

                'country' =>
                $country,

                'province' =>
                $province,

                'city' =>
                $city,

                'zone' =>
                $zone,

                'total_area' =>
                $totalArea,

                'covered_area' =>
                $coveredArea,

                'bedrooms' =>
                $bedrooms,

                'bathrooms' =>
                $bathrooms,

                'garages' =>
                $garages,

                'antiquity' =>
                $antiquity,
            ]);
        }
    }

    public static function createDraft(int $userId, array $data): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        self::validate($data, true);

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                INSERT INTO search_requests (
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
                    is_visible
                ) VALUES (
                    :real_estate_id,
                    :created_by_user_id,
                    :title,
                    :description,
                    :country_code,
                    :country,
                    :province,
                    :city,
                    :zone,
                    :property_condition,
                    :currency,
                    :min_value,
                    :max_value,
                    :min_total_area,
                    :min_covered_area,
                    :min_bedrooms,
                    :min_bathrooms,
                    :min_garages,
                    :max_antiquity,
                    :urgency,
                    :payment_mode_cash,
                    :payment_mode_swap,
                    :cash_difference_max,
                    :cash_difference_currency,
                    :open_to_other_zones,
                    :notes,
                    'draft',
                    0
                )
            ");

            $st->execute([
                'real_estate_id' => (int)$user['real_estate_id'],
                'created_by_user_id' => $userId,
                'title' => trim((string)$data['title']),
                'description' => trim((string)$data['description']),
                'country_code' => trim((string)$data['country_code']),
                'country' => trim((string)$data['country']),
                'province' => trim((string)$data['province']),
                'city' => self::nullableString($data['city'] ?? null),
                'zone' => self::nullableString($data['zone'] ?? null),
                'property_condition' => $data['property_condition'] ?? 'any',
                'currency' => $data['currency'] ?? 'USD',
                'min_value' => self::nullableNumber($data['min_value'] ?? null),
                'max_value' => self::nullableNumber($data['max_value'] ?? null),
                'min_total_area' => self::nullableNumber($data['min_total_area'] ?? null),
                'min_covered_area' => self::nullableNumber($data['min_covered_area'] ?? null),
                'min_bedrooms' => self::nullableInt($data['min_bedrooms'] ?? null),
                'min_bathrooms' => self::nullableInt($data['min_bathrooms'] ?? null),
                'min_garages' => self::nullableInt($data['min_garages'] ?? null),
                'max_antiquity' => self::nullableInt($data['max_antiquity'] ?? null),
                'urgency' => $data['urgency'] ?? 'medium',
                'payment_mode_cash' => !empty($data['payment_mode_cash']) ? 1 : 0,
                'payment_mode_swap' => !empty($data['payment_mode_swap']) ? 1 : 0,
                'cash_difference_max' => self::nullableNumber($data['cash_difference_max'] ?? null),
                'cash_difference_currency' => $data['cash_difference_currency'] ?? 'USD',
                'open_to_other_zones' => !empty($data['open_to_other_zones']) ? 1 : 0,
                'notes' => self::nullableString($data['notes'] ?? null),
            ]);

            $id = (int)$pdo->lastInsertId();

            self::syncPropertyTypes($pdo, $id, $data['property_types'] ?? []);
            self::syncAmenities($pdo, $id, $data['amenities'] ?? []);
            self::syncExchangeOffers(
                $pdo,
                $id,
                (int)$user['real_estate_id'],
                !empty($data['payment_mode_swap'])
                    ? ($data['exchange_offers'] ?? [])
                    : []
            );
            self::logStatus($pdo, $id, null, 'draft', $userId);

            $pdo->commit();

            return self::getDetail($userId, $id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateDraft(int $userId, int $id, array $data): array
    {
        $pdo = self::db();
        [$user, $current] =
            self::getOwnedSearchRequestRow(
                $userId,
                $id
            );

        if (!in_array($current['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se puede editar la búsqueda en el estado actual");
        }

        $completeData = array_merge(
            $current,
            $data
        );

        self::validate(
            $completeData,
            false
        );

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                UPDATE search_requests SET
                    title = :title,
                    description = :description,
                    country_code = :country_code,
                    country = :country,
                    province = :province,
                    city = :city,
                    zone = :zone,
                    property_condition = :property_condition,
                    currency = :currency,
                    min_value = :min_value,
                    max_value = :max_value,
                    min_total_area = :min_total_area,
                    min_covered_area = :min_covered_area,
                    min_bedrooms = :min_bedrooms,
                    min_bathrooms = :min_bathrooms,
                    min_garages = :min_garages,
                    max_antiquity = :max_antiquity,
                    urgency = :urgency,
                    payment_mode_cash = :payment_mode_cash,
                    payment_mode_swap = :payment_mode_swap,
                    cash_difference_max = :cash_difference_max,
                    cash_difference_currency = :cash_difference_currency,
                    open_to_other_zones = :open_to_other_zones,
                    notes = :notes
                WHERE id = :id
                  AND real_estate_id = :real_estate_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $st->execute([
                'id' => $id,
                'real_estate_id' => (int)$current['real_estate_id'],
                'title' => trim((string)($data['title'] ?? $current['title'])),
                'description' => trim((string)($data['description'] ?? $current['description'])),
                'country_code' => trim((string)($data['country_code'] ?? $current['country_code'])),
                'country' => trim((string)($data['country'] ?? $current['country'])),
                'province' => trim((string)($data['province'] ?? $current['province'])),
                'city' => self::nullableString($data['city'] ?? $current['city']),
                'zone' => self::nullableString($data['zone'] ?? $current['zone']),
                'property_condition' => $data['property_condition'] ?? $current['property_condition'],
                'currency' => $data['currency'] ?? $current['currency'],
                'min_value' => self::nullableNumber($data['min_value'] ?? $current['min_value']),
                'max_value' => self::nullableNumber($data['max_value'] ?? $current['max_value']),
                'min_total_area' => self::nullableNumber($data['min_total_area'] ?? $current['min_total_area']),
                'min_covered_area' => self::nullableNumber($data['min_covered_area'] ?? $current['min_covered_area']),
                'min_bedrooms' => self::nullableInt($data['min_bedrooms'] ?? $current['min_bedrooms']),
                'min_bathrooms' => self::nullableInt($data['min_bathrooms'] ?? $current['min_bathrooms']),
                'min_garages' => self::nullableInt($data['min_garages'] ?? $current['min_garages']),
                'max_antiquity' => self::nullableInt($data['max_antiquity'] ?? $current['max_antiquity']),
                'urgency' => $data['urgency'] ?? $current['urgency'],
                'payment_mode_cash' => isset($data['payment_mode_cash'])
                    ? (!empty($data['payment_mode_cash']) ? 1 : 0)
                    : (int)$current['payment_mode_cash'],
                'payment_mode_swap' => isset($data['payment_mode_swap'])
                    ? (!empty($data['payment_mode_swap']) ? 1 : 0)
                    : (int)$current['payment_mode_swap'],
                'cash_difference_max' => self::nullableNumber($data['cash_difference_max'] ?? $current['cash_difference_max']),
                'cash_difference_currency' => $data['cash_difference_currency'] ?? $current['cash_difference_currency'],
                'open_to_other_zones' => isset($data['open_to_other_zones'])
                    ? (!empty($data['open_to_other_zones']) ? 1 : 0)
                    : (int)$current['open_to_other_zones'],
                'notes' => self::nullableString($data['notes'] ?? $current['notes']),
            ]);

            if (array_key_exists('property_types', $data)) {
                self::syncPropertyTypes($pdo, $id, $data['property_types'] ?? []);
            }

            if (array_key_exists('amenities', $data)) {
                self::syncAmenities($pdo, $id, $data['amenities'] ?? []);
            }
            $effectivePaymentModeSwap =
                isset($data['payment_mode_swap'])
                ? (!empty($data['payment_mode_swap']) ? 1 : 0)
                : (int)$current['payment_mode_swap'];

            if ($effectivePaymentModeSwap === 0) {
                self::syncExchangeOffers(
                    $pdo,
                    $id,
                    (int)$user['real_estate_id'],
                    []
                );
            } elseif (array_key_exists('exchange_offers', $data)) {
                self::syncExchangeOffers(
                    $pdo,
                    $id,
                    (int)$user['real_estate_id'],
                    $data['exchange_offers'] ?? []
                );
            }
            $pdo->commit();

            /*
 * Si la búsqueda estaba publicada, recalculamos
 * las compatibilidades después de guardar los cambios.
 */
            if ($current['status'] === 'published') {
                self::queueCompatibilityRecalculation($id);
            }

            return self::getDetail($userId, $id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function publish(
        int $userId,
        int $id
    ): array {
        $pdo = self::db();

        [, $current] =
            self::getOwnedSearchRequestRow(
                $userId,
                $id
            );

        $current['property_types'] =
            self::getPropertyTypes(
                $pdo,
                $id
            );

        self::validate(
            $current,
            true
        );

        $result = self::changeStatus(
            $userId,
            $id,
            'published',
            true
        );

        self::queueCompatibilityRecalculation(
            $id
        );

        return $result;
    }

    public static function pause(int $userId, int $id): array
    {
        $result = self::changeStatus(
            $userId,
            $id,
            'paused',
            false
        );

        self::queueCompatibilityArchive($id);

        return $result;
    }

    public static function archive(int $userId, int $id): array
    {
        $result = self::changeStatus(
            $userId,
            $id,
            'archived',
            false
        );

        self::queueCompatibilityArchive($id);

        return $result;
    }

    public static function delete(int $userId, int $id): array
    {
        $pdo = self::db();
        [, $current] = self::getOwnedSearchRequestRow($userId, $id);

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                UPDATE search_requests
                SET
                    status = 'deleted',
                    is_visible = 0,
                    deleted_at = NOW()
                WHERE id = :id
                  AND real_estate_id = :real_estate_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $st->execute([
                'id' => $id,
                'real_estate_id' => (int)$current['real_estate_id'],
            ]);

            self::logStatus($pdo, $id, $current['status'], 'deleted', $userId);

            $pdo->commit();

            self::queueCompatibilityArchive($id);

            return [
                'deleted' => true,
                'id' => $id,
                'status' => 'deleted',
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function changeStatus(int $userId, int $id, string $newStatus, bool $visible): array
    {
        $pdo = self::db();
        [, $current] = self::getOwnedSearchRequestRow($userId, $id);

        $allowed = match ($newStatus) {
            'published' => ['draft', 'paused', 'archived'],
            'paused' => ['published'],
            'archived' => ['draft', 'paused', 'published'],
            default => [],
        };

        if (!in_array($current['status'], $allowed, true)) {
            throw new Exception("No se puede cambiar el estado desde '{$current['status']}' a '{$newStatus}'");
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                UPDATE search_requests
                SET
                    status = :status,
                    is_visible = :is_visible,
                    published_at = CASE WHEN :status = 'published' THEN NOW() ELSE published_at END,
                    paused_at = CASE WHEN :status = 'paused' THEN NOW() ELSE paused_at END,
                    archived_at = CASE WHEN :status = 'archived' THEN NOW() ELSE archived_at END
                WHERE id = :id
                  AND real_estate_id = :real_estate_id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $st->execute([
                'status' => $newStatus,
                'is_visible' => $visible ? 1 : 0,
                'id' => $id,
                'real_estate_id' => (int)$current['real_estate_id'],
            ]);

            self::logStatus($pdo, $id, $current['status'], $newStatus, $userId);

            $pdo->commit();

            return self::getDetail($userId, $id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Encola el recálculo de compatibilidades sin impedir que
     * la operación principal de la búsqueda se complete.
     */
    private static function queueCompatibilityRecalculation(
        int $searchRequestId
    ): void {
        try {
            CompatibilityJobService::enqueueSearchRequestRecalculation(
                $searchRequestId
            );
        } catch (Throwable $e) {
            error_log(
                '[SEARCH REQUEST COMPATIBILITY QUEUE] No se pudo encolar ' .
                    'el recálculo de la búsqueda ' . $searchRequestId . ': ' .
                    $e->getMessage()
            );
        }
    }

    /**
     * Encola el archivado de compatibilidades cuando la búsqueda
     * deja de estar publicada.
     */
    private static function queueCompatibilityArchive(
        int $searchRequestId
    ): void {
        try {
            CompatibilityJobService::enqueueSearchRequestArchive(
                $searchRequestId
            );
        } catch (Throwable $e) {
            error_log(
                '[SEARCH REQUEST COMPATIBILITY QUEUE] No se pudo encolar ' .
                    'el archivado de compatibilidades de la búsqueda ' .
                    $searchRequestId . ': ' .
                    $e->getMessage()
            );
        }
    }

    private static function validate(
        array $data,
        bool $strict = true
    ): void {
        $requiredStrings = [
            'title' => 'El título',
            'description' => 'La descripción',
            'country_code' => 'El código de país',
            'country' => 'El país',
            'province' => 'La provincia',
        ];

        foreach ($requiredStrings as $field => $label) {
            if (
                $strict ||
                array_key_exists($field, $data)
            ) {
                $value = self::nullableString(
                    $data[$field] ?? null,
                    $label
                );

                if ($strict && $value === null) {
                    throw new Exception(
                        "{$label} es obligatorio",
                        422
                    );
                }
            }
        }

        $optionalStrings = [
            'city' => 'La ciudad',
            'zone' => 'La zona',
            'notes' => 'Las notas',
        ];

        foreach ($optionalStrings as $field => $label) {
            if (array_key_exists($field, $data)) {
                self::nullableString(
                    $data[$field],
                    $label
                );
            }
        }

        if (
            $strict ||
            array_key_exists('country_code', $data)
        ) {
            $countryCode =
                $data['country_code'] ?? null;

            if (
                !is_string($countryCode) ||
                !in_array(
                    trim($countryCode),
                    ['AR', 'US', 'IT'],
                    true
                )
            ) {
                throw new Exception(
                    'El país seleccionado no es válido',
                    422
                );
            }
        }

        if (
            $strict ||
            array_key_exists('property_condition', $data)
        ) {
            $condition =
                $data['property_condition'] ?? 'any';

            if (
                !is_string($condition) ||
                !in_array(
                    $condition,
                    [
                        'any',
                        'new',
                        'used',
                        'to_renovate',
                    ],
                    true
                )
            ) {
                throw new Exception(
                    'El estado de la propiedad no es válido',
                    422
                );
            }
        }

        foreach (
            ['currency', 'cash_difference_currency']
            as $currencyField
        ) {
            if (
                $strict ||
                array_key_exists($currencyField, $data)
            ) {
                $currency =
                    $data[$currencyField] ?? 'USD';

                if (
                    !is_string($currency) ||
                    !in_array(
                        $currency,
                        ['ARS', 'USD'],
                        true
                    )
                ) {
                    throw new Exception(
                        'La moneda seleccionada no es válida',
                        422
                    );
                }
            }
        }

        if (
            $strict ||
            array_key_exists('urgency', $data)
        ) {
            $urgency =
                $data['urgency'] ?? 'medium';

            if (
                !is_string($urgency) ||
                !in_array(
                    $urgency,
                    ['low', 'medium', 'high'],
                    true
                )
            ) {
                throw new Exception(
                    'La urgencia seleccionada no es válida',
                    422
                );
            }
        }

        $numericFields = [
            'min_value' => 'El valor mínimo',
            'max_value' => 'El valor máximo',
            'min_total_area' =>
            'La superficie total mínima',
            'min_covered_area' =>
            'La superficie cubierta mínima',
            'cash_difference_max' =>
            'La diferencia máxima en efectivo',
        ];

        foreach ($numericFields as $field => $label) {
            if (array_key_exists($field, $data)) {
                self::nullableNumber(
                    $data[$field],
                    $label
                );
            }
        }

        $integerFields = [
            'min_bedrooms' =>
            'La cantidad mínima de dormitorios',
            'min_bathrooms' =>
            'La cantidad mínima de baños',
            'min_garages' =>
            'La cantidad mínima de cocheras',
            'max_antiquity' =>
            'La antigüedad máxima',
        ];

        foreach ($integerFields as $field => $label) {
            if (array_key_exists($field, $data)) {
                self::nullableInt(
                    $data[$field],
                    $label
                );
            }
        }

        $booleanFields = [
            'payment_mode_cash',
            'payment_mode_swap',
            'open_to_other_zones',
        ];

        foreach ($booleanFields as $field) {
            if (
                array_key_exists($field, $data) &&
                !in_array(
                    $data[$field],
                    [true, false, 0, 1, '0', '1'],
                    true
                )
            ) {
                throw new Exception(
                    "El campo {$field} tiene un formato inválido",
                    422
                );
            }
        }

        if (
            $strict ||
            array_key_exists('payment_mode_cash', $data) ||
            array_key_exists('payment_mode_swap', $data)
        ) {
            $cash =
                !empty($data['payment_mode_cash']);
            $swap =
                !empty($data['payment_mode_swap']);

            if (!$cash && !$swap) {
                throw new Exception(
                    'Debe elegir al menos una forma de pago',
                    422
                );
            }
        }

        if ($strict) {
            $types =
                $data['property_types'] ?? [];

            if (
                !is_array($types) ||
                count($types) === 0
            ) {
                throw new Exception(
                    'Debés indicar al menos un tipo de propiedad buscada',
                    422
                );
            }
        }

        $minValue = self::nullableNumber(
            $data['min_value'] ?? null,
            'El valor mínimo'
        );

        $maxValue = self::nullableNumber(
            $data['max_value'] ?? null,
            'El valor máximo'
        );

        if (
            $minValue !== null &&
            $maxValue !== null &&
            $minValue > $maxValue
        ) {
            throw new Exception(
                'El valor mínimo no puede ser mayor al valor máximo',
                422
            );
        }
    }

    private static function syncPropertyTypes(
        PDO $pdo,
        int $id,
        mixed $types
    ): void {
        if (!is_array($types)) {
            throw new Exception(
                'Los tipos de propiedad deben enviarse como una lista',
                422
            );
        }

        $allowedTypes = [
            'house',
            'apartment',
            'land',
            'commercial',
            'office',
            'warehouse',
            'country_house',
            'farm',
            'garage',
            'other',
        ];

        $cleanTypes = [];

        foreach ($types as $type) {
            if (!is_string($type)) {
                throw new Exception(
                    'Uno de los tipos de propiedad tiene un formato inválido',
                    422
                );
            }

            $type = trim($type);

            if ($type === '') {
                continue;
            }

            if (
                !in_array(
                    $type,
                    $allowedTypes,
                    true
                )
            ) {
                throw new Exception(
                    'El tipo de propiedad seleccionado no es válido',
                    422
                );
            }

            $cleanTypes[$type] = true;
        }

        $pdo->prepare("
        DELETE FROM search_request_property_types
        WHERE search_request_id = :id
    ")->execute([
            'id' => $id,
        ]);

        if (!$cleanTypes) {
            return;
        }

        $stInsert = $pdo->prepare("
        INSERT INTO search_request_property_types (
            search_request_id,
            property_type
        )
        VALUES (
            :search_request_id,
            :property_type
        )
    ");

        foreach (array_keys($cleanTypes) as $type) {
            $stInsert->execute([
                'search_request_id' => $id,
                'property_type' => $type,
            ]);
        }
    }
    private static function syncAmenities(
        PDO $pdo,
        int $id,
        mixed $items
    ): void {
        if (!is_array($items)) {
            throw new Exception(
                'Los amenities deben enviarse como una lista',
                422
            );
        }

        $allowedAmenities = [
            'balcony',
            'patio',
            'terrace',
            'pool',
            'quincho',
            'garden',
            'barbecue',
            'sum',
            'gym',
            'security',
            'doorman',
            'laundry',
            'elevator',
            'garage',
            'storage',
            'green_area',
            'cowork',
            'kids_area',
            'pet_friendly',
            'rooftop',
            'jacuzzi',
        ];

        $cleanItems = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new Exception(
                    'Uno de los amenities tiene un formato inválido',
                    422
                );
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            if (
                !in_array(
                    $item,
                    $allowedAmenities,
                    true
                )
            ) {
                throw new Exception(
                    'El amenity seleccionado no es válido',
                    422
                );
            }

            $cleanItems[$item] = true;
        }

        $pdo->prepare("
        DELETE FROM search_request_amenities
        WHERE search_request_id = :id
    ")->execute([
            'id' => $id,
        ]);

        if (!$cleanItems) {
            return;
        }

        $stInsert = $pdo->prepare("
        INSERT INTO search_request_amenities (
            search_request_id,
            amenity_code
        )
        VALUES (
            :search_request_id,
            :amenity_code
        )
    ");

        foreach (array_keys($cleanItems) as $item) {
            $stInsert->execute([
                'search_request_id' => $id,
                'amenity_code' => $item,
            ]);
        }
    }

    private static function getPropertyTypes(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("
            SELECT property_type
            FROM search_request_property_types
            WHERE search_request_id = :id
            ORDER BY id ASC
        ");
        $st->execute(['id' => $id]);

        return array_column($st->fetchAll() ?: [], 'property_type');
    }

    private static function getAmenities(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM search_request_amenities
            WHERE search_request_id = :id
            ORDER BY id ASC
        ");
        $st->execute(['id' => $id]);

        return array_column($st->fetchAll() ?: [], 'amenity_code');
    }

    private static function logStatus(PDO $pdo, int $id, ?string $oldStatus, string $newStatus, int $userId): void
    {
        $pdo->prepare("
            INSERT INTO search_request_status_history (
                search_request_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_source
            ) VALUES (
                :search_request_id,
                :old_status,
                :new_status,
                :changed_by_user_id,
                'user'
            )
        ")->execute([
            'search_request_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId,
        ]);
    }

    private static function nullableString(
        mixed $value,
        string $fieldName = 'El valor'
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new Exception(
                "{$fieldName} tiene un formato inválido",
                422
            );
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private static function nullableNumber(
        mixed $value,
        string $fieldName = 'El valor',
        ?float $minimum = 0
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            (!is_int($value) &&
                !is_float($value) &&
                !is_string($value)) ||
            !is_numeric($value)
        ) {
            throw new Exception(
                "{$fieldName} debe ser un número válido",
                422
            );
        }

        $number = (float)$value;

        if (!is_finite($number)) {
            throw new Exception(
                "{$fieldName} debe ser un número válido",
                422
            );
        }

        if (
            $minimum !== null &&
            $number < $minimum
        ) {
            throw new Exception(
                "{$fieldName} no puede ser negativo",
                422
            );
        }

        return $number;
    }

    private static function nullableInt(
        mixed $value,
        string $fieldName = 'El valor',
        int $minimum = 0
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            (!is_int($value) &&
                !is_float($value) &&
                !is_string($value)) ||
            !is_numeric($value)
        ) {
            throw new Exception(
                "{$fieldName} debe ser un número entero válido",
                422
            );
        }

        $number = (float)$value;

        if (
            !is_finite($number) ||
            floor($number) !== $number
        ) {
            throw new Exception(
                "{$fieldName} debe ser un número entero válido",
                422
            );
        }

        if ($number < $minimum) {
            throw new Exception(
                "{$fieldName} no puede ser negativo",
                422
            );
        }

        return (int)$number;
    }

    public static function listExploreSearchRequests(int $userId, array $filters = []): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $where = [
            "sr.deleted_at IS NULL",
            "sr.status = 'published'",
            "sr.is_visible = 1",
            "sr.real_estate_id <> :real_estate_id",
        ];

        $params = [
            'real_estate_id' => (int)$user['real_estate_id'],
        ];

        if (!empty($filters['q'])) {
            $where[] = "(
            sr.title LIKE :q
            OR sr.description LIKE :q
            OR sr.country LIKE :q
            OR sr.province LIKE :q
            OR sr.city LIKE :q
            OR sr.zone LIKE :q
            OR CAST(sr.id AS CHAR) LIKE :q
        )";
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        if (!empty($filters['property_type'])) {
            $where[] = "EXISTS (
            SELECT 1
            FROM search_request_property_types srpt_filter
            WHERE srpt_filter.search_request_id = sr.id
              AND srpt_filter.property_type = :property_type
        )";
            $params['property_type'] = trim((string)$filters['property_type']);
        }

        if (!empty($filters['amenities']) && is_array($filters['amenities'])) {
            $amenities = [];

            foreach ($filters['amenities'] as $amenity) {
                $amenity = trim((string)$amenity);
                if ($amenity !== '') {
                    $amenities[$amenity] = true;
                }
            }

            foreach (array_keys($amenities) as $i => $amenity) {
                $param = "amenity_$i";

                $where[] = "EXISTS (
            SELECT 1
            FROM search_request_amenities sra_filter_$i
            WHERE sra_filter_$i.search_request_id = sr.id
              AND sra_filter_$i.amenity_code = :$param
        )";

                $params[$param] = $amenity;
            }
        }

        $limit = (int)($filters['limit'] ?? 20);
        if ($limit <= 0) $limit = 20;
        if ($limit > 100) $limit = 100;

        $page = (int)($filters['page'] ?? 1);
        if ($page <= 0) $page = 1;

        $offset = ($page - 1) * $limit;

        $countSql = "
        SELECT COUNT(*) AS total
        FROM search_requests sr
        WHERE " . implode(" AND ", $where);

        $stCount = $pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $stCount->bindValue(":$k", $v);
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
            sr.*,
            (
                SELECT GROUP_CONCAT(DISTINCT srpt.property_type ORDER BY srpt.property_type SEPARATOR ',')
                FROM search_request_property_types srpt
                WHERE srpt.search_request_id = sr.id
            ) AS property_types,
            (
                SELECT COUNT(*)
                FROM search_request_property_types srpt
                WHERE srpt.search_request_id = sr.id
            ) AS property_types_count,
            (
    SELECT GROUP_CONCAT(DISTINCT sra.amenity_code ORDER BY sra.amenity_code SEPARATOR ',')
    FROM search_request_amenities sra
    WHERE sra.search_request_id = sr.id
) AS amenities,
(
    SELECT COUNT(*)
    FROM search_request_amenities sra
    WHERE sra.search_request_id = sr.id
) AS amenities_count
        FROM search_requests sr
        WHERE " . implode(" AND ", $where) . "
        ORDER BY sr.published_at DESC, sr.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }

        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll() ?: [];

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

    public static function getExploreDetail(int $userId, int $id): array
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
            'id' => $id,
        ];

        if (!empty($user['real_estate_id'])) {
            $where[] = "real_estate_id <> :real_estate_id";
            $params['real_estate_id'] = (int)$user['real_estate_id'];
        }

        $st = $pdo->prepare("
   SELECT
    id,
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
    status,
    published_at,
    created_at,
    updated_at
FROM search_requests
    WHERE " . implode(" AND ", $where) . "
    LIMIT 1
");

        $st->execute($params);
        $item = $st->fetch();

        if (!$item) {
            throw new Exception("Búsqueda no encontrada");
        }

        $item['property_types'] = self::getPropertyTypes($pdo, (int)$item['id']);
        $item['amenities'] = self::getAmenities($pdo, (int)$item['id']);

        return [
            'search_request' => $item,
            'property_types' => $item['property_types'],
            'amenities' => $item['amenities'],
            'access' => [
                'can_edit' => false,
                'can_publish' => false,
                'can_pause' => false,
                'can_archive' => false,
                'can_delete' => false,
            ],
        ];
    }
}
