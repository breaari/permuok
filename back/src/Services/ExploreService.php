<?php

namespace App\Services;

use PDO;

class ExploreService
{
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
    private static function developmentImageViewUrl(?int $imageId): ?string
    {
        if (!$imageId) {
            return null;
        }

        return '/development-images/' . $imageId . '/view';
    }
    private static function getUserRealEstateId(int $userId): ?int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT real_estate_id
        FROM users
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'id' => $userId,
        ]);

        $value = $st->fetchColumn();

        if (!$value) {
            return null;
        }

        return (int)$value;
    }

    public static function search(int $userId, array $filters): array
    {
        $type = $filters['opportunity_type'] ?? 'all';

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(50, max(1, (int)($filters['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $items = [];

        if ($type === 'all' || $type === 'property') {
            $items = array_merge($items, self::searchProperties($userId, $filters, $limit, $offset));
        }

        if ($type === 'all' || $type === 'search_request') {
            $items = array_merge($items, self::searchRequests($userId, $filters, $limit, $offset));
        }

        if ($type === 'all' || $type === 'development') {
            $items = array_merge($items, self::searchDevelopments($userId, $filters, $limit, $offset));
        }

        usort($items, function ($a, $b) use ($filters) {
            $sort = $filters['sort'] ?? 'recent';

            if ($sort === 'value_asc') {
                return ($a['sort_value'] ?? PHP_INT_MAX) <=> ($b['sort_value'] ?? PHP_INT_MAX);
            }

            if ($sort === 'value_desc') {
                return ($b['sort_value'] ?? 0) <=> ($a['sort_value'] ?? 0);
            }

            return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
        });

        $items = array_slice($items, 0, $limit);

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total_returned' => count($items),
            'filters' => $filters,
        ];
    }

    private static function searchProperties(int $userId, array $filters, int $limit, int $offset): array
    {
        $pdo = self::db();

        $where = [
            "p.deleted_at IS NULL",
            "p.status = 'published'",
            "p.is_visible = 1",
        ];

        $params = [];

        $currentRealEstateId = self::getUserRealEstateId($userId);

        if ($currentRealEstateId) {
            $where[] = "p.real_estate_id <> :current_real_estate_id";
            $params[':current_real_estate_id'] = $currentRealEstateId;
        }

        self::applyTextFilter($where, $params, $filters, [
            'p.title',
            'p.description',
            'p.country',
            'p.province',
            'p.city',
            'p.zone',
        ]);

        self::applyLocationFilter($where, $params, $filters, 'p');
        self::applyCurrencyFilter($where, $params, $filters, 'p.currency');

        if (!empty($filters['property_type'])) {
            $where[] = "p.property_type = :property_property_type";
            $params[':property_property_type'] = $filters['property_type'];
        }

        if (self::hasValue($filters['value_min'] ?? null)) {
            $where[] = "p.price >= :property_value_min";
            $params[':property_value_min'] = (float)$filters['value_min'];
        }

        if (self::hasValue($filters['value_max'] ?? null)) {
            $where[] = "p.price <= :property_value_max";
            $params[':property_value_max'] = (float)$filters['value_max'];
        }

        if (self::hasValue($filters['bedrooms_min'] ?? null)) {
            $where[] = "p.bedrooms >= :property_bedrooms_min";
            $params[':property_bedrooms_min'] = (int)$filters['bedrooms_min'];
        }

        if (self::hasValue($filters['bathrooms_min'] ?? null)) {
            $where[] = "p.bathrooms >= :property_bathrooms_min";
            $params[':property_bathrooms_min'] = (int)$filters['bathrooms_min'];
        }

        if (self::hasValue($filters['garages_min'] ?? null)) {
            $where[] = "p.garages >= :property_garages_min";
            $params[':property_garages_min'] = (int)$filters['garages_min'];
        }

        if (self::hasValue($filters['area_min'] ?? null)) {
            $where[] = "COALESCE(p.total_area, p.covered_area, 0) >= :property_area_min";
            $params[':property_area_min'] = (float)$filters['area_min'];
        }

        self::applyExchangeModesForProperties($where, $filters);
        self::applyAmenitiesForProperties($where, $params, $filters);


        $sql = "
    SELECT
        p.*,
        pi.id AS cover_image_id,
        'property' AS opportunity_type,
        p.price AS sort_value
    FROM properties p
    LEFT JOIN property_images pi
      ON pi.id = (
        SELECT pi2.id
        FROM property_images pi2
        WHERE pi2.property_id = p.id
          AND pi2.deleted_at IS NULL
        ORDER BY pi2.is_cover DESC, pi2.sort_order ASC, pi2.id ASC
        LIMIT 1
      )
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        return array_map(function ($row) {
            $coverImageId = !empty($row['cover_image_id'])
                ? (int)$row['cover_image_id']
                : null;

            $row['cover_image_url'] = self::imageViewUrl($coverImageId);
            $row['image_url'] = $row['cover_image_url'];

            return [
                'opportunity_type' => 'property',
                'id' => (int)$row['id'],
                'sort_value' => isset($row['sort_value']) ? (float)$row['sort_value'] : null,
                'created_at' => $row['created_at'] ?? null,
                'item' => $row,
            ];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function searchRequests(int $userId, array $filters, int $limit, int $offset): array
    {
        $pdo = self::db();

        $where = [
            "sr.deleted_at IS NULL",
            "sr.status = 'published'",
            "sr.is_visible = 1",
        ];

        $params = [];


        $currentRealEstateId = self::getUserRealEstateId($userId);

        if ($currentRealEstateId) {
            $where[] = "sr.real_estate_id <> :current_real_estate_id";
            $params[':current_real_estate_id'] = $currentRealEstateId;
        }


        self::applyTextFilter($where, $params, $filters, [
            'sr.title',
            'sr.description',
            'sr.country',
            'sr.province',
            'sr.city',
            'sr.zone',
            'sr.notes',
        ]);

        self::applyLocationFilter($where, $params, $filters, 'sr');
        self::applyCurrencyFilter($where, $params, $filters, 'sr.currency');

        if (self::hasValue($filters['value_min'] ?? null)) {
            $where[] = "COALESCE(sr.max_value, 0) >= :search_value_min";
            $params[':search_value_min'] = (float)$filters['value_min'];
        }

        if (self::hasValue($filters['value_max'] ?? null)) {
            $where[] = "COALESCE(sr.min_value, sr.max_value, 0) <= :search_value_max";
            $params[':search_value_max'] = (float)$filters['value_max'];
        }

        if (self::hasValue($filters['bedrooms_min'] ?? null)) {
            $where[] = "sr.min_bedrooms >= :search_bedrooms_min";
            $params[':search_bedrooms_min'] = (int)$filters['bedrooms_min'];
        }

        if (self::hasValue($filters['bathrooms_min'] ?? null)) {
            $where[] = "sr.min_bathrooms >= :search_bathrooms_min";
            $params[':search_bathrooms_min'] = (int)$filters['bathrooms_min'];
        }

        if (self::hasValue($filters['garages_min'] ?? null)) {
            $where[] = "sr.min_garages >= :search_garages_min";
            $params[':search_garages_min'] = (int)$filters['garages_min'];
        }

        if (self::hasValue($filters['area_min'] ?? null)) {
            $where[] = "COALESCE(sr.min_total_area, sr.min_covered_area, 0) >= :search_area_min";
            $params[':search_area_min'] = (float)$filters['area_min'];
        }

        self::applyExchangeModesForSearchRequests($where, $filters);
        self::applyAmenitiesForSearchRequests($where, $params, $filters);

        $sql = "
            SELECT
                sr.*,
                'search_request' AS opportunity_type,
                COALESCE(sr.max_value, sr.min_value, 0) AS sort_value
            FROM search_requests sr
            WHERE " . implode(" AND ", $where) . "
            ORDER BY sr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        return array_map(function ($row) {
            return [
                'opportunity_type' => 'search_request',
                'id' => (int)$row['id'],
                'sort_value' => isset($row['sort_value']) ? (float)$row['sort_value'] : null,
                'created_at' => $row['created_at'] ?? null,
                'item' => $row,
            ];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function searchDevelopments(int $userId, array $filters, int $limit, int $offset): array
    {
        $pdo = self::db();

        $where = [
            "d.deleted_at IS NULL",
            "d.status = 'published'",
        ];

        $params = [];
        $currentRealEstateId = self::getUserRealEstateId($userId);

        if ($currentRealEstateId) {
            $where[] = "d.real_estate_id <> :current_real_estate_id";
            $params[':current_real_estate_id'] = $currentRealEstateId;
        }
        self::applyTextFilter($where, $params, $filters, [
            'd.title',
            'd.description',
            'd.short_description',
            'd.developer_name',
            'd.construction_company',
            'd.country',
            'd.province',
            'd.city',
            'd.zone',
        ]);

        self::applyLocationFilter($where, $params, $filters, 'd');
        self::applyCurrencyFilter($where, $params, $filters, 'd.currency');

        if (self::hasValue($filters['value_min'] ?? null)) {
            $where[] = "COALESCE(d.price_to, d.price_from, 0) >= :development_value_min";
            $params[':development_value_min'] = (float)$filters['value_min'];
        }

        if (self::hasValue($filters['value_max'] ?? null)) {
            $where[] = "COALESCE(d.price_from, d.price_to, 0) <= :development_value_max";
            $params[':development_value_max'] = (float)$filters['value_max'];
        }

        if (!empty($filters['development_stage'])) {
            $where[] = "d.development_stage = :development_stage";
            $params[':development_stage'] = $filters['development_stage'];
        }

        if (!empty($filters['property_type'])) {
            $where[] = "EXISTS (
                SELECT 1
                FROM development_unit_types dut
                WHERE dut.development_id = d.id
                  AND dut.deleted_at IS NULL
                  AND dut.unit_type = :development_property_type
            )";
            $params[':development_property_type'] = $filters['property_type'];
        }

        self::applyAmenitiesForDevelopments($where, $params, $filters);

        $sql = "
    SELECT
        d.*,
        di.id AS cover_image_id,
        'development' AS opportunity_type,
        COALESCE(d.price_from, d.price_to, 0) AS sort_value
    FROM developments d
    LEFT JOIN development_images di
      ON di.id = (
        SELECT di2.id
        FROM development_images di2
        WHERE di2.development_id = d.id
          AND di2.deleted_at IS NULL
        ORDER BY di2.is_cover DESC, di2.sort_order ASC, di2.id ASC
        LIMIT 1
      )
    WHERE " . implode(" AND ", $where) . "
    ORDER BY d.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        return array_map(function ($row) {
            $coverImageId = !empty($row['cover_image_id'])
                ? (int)$row['cover_image_id']
                : null;

            $row['cover_image_url'] = self::developmentImageViewUrl($coverImageId);
            $row['image_url'] = $row['cover_image_url'];

            return [
                'opportunity_type' => 'development',
                'id' => (int)$row['id'],
                'sort_value' => isset($row['sort_value']) ? (float)$row['sort_value'] : null,
                'created_at' => $row['created_at'] ?? null,
                'item' => $row,
            ];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function applyTextFilter(array &$where, array &$params, array $filters, array $columns): void
    {
        if (empty($filters['q'])) return;

        $q = trim((string)$filters['q']);
        if ($q === '') return;

        $parts = [];

        foreach ($columns as $index => $column) {
            $key = ':q_' . $index . '_' . substr(md5($column), 0, 8);
            $parts[] = "{$column} LIKE {$key}";
            $params[$key] = '%' . $q . '%';
        }

        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    private static function applyLocationFilter(array &$where, array &$params, array $filters, string $alias): void
    {
        foreach (['country', 'province', 'city', 'zone'] as $field) {
            if (!empty($filters[$field])) {
                $key = ':' . $alias . '_' . $field;
                $where[] = "{$alias}.{$field} LIKE {$key}";
                $params[$key] = '%' . trim((string)$filters[$field]) . '%';
            }
        }
    }

    private static function applyCurrencyFilter(array &$where, array &$params, array $filters, string $column): void
    {
        if (empty($filters['currency'])) return;

        $key = ':currency_' . substr(md5($column), 0, 8);
        $where[] = "{$column} = {$key}";
        $params[$key] = $filters['currency'];
    }

    private static function applyExchangeModesForProperties(array &$where, array $filters): void
    {
        $modes = self::normalizeArray($filters['exchange_modes'] ?? []);

        if (!$modes) return;

        $modeWhere = [];

        if (in_array('total_swap', $modes, true)) {
            $modeWhere[] = "pr.accepts_total_swap = 1";
        }

        if (in_array('swap_plus_cash', $modes, true)) {
            $modeWhere[] = "pr.accepts_swap_plus_cash = 1";
        }

        if (in_array('multiple_swap', $modes, true)) {
            $modeWhere[] = "pr.accepts_multiple_swap = 1";
        }

        if (in_array('open_proposals', $modes, true)) {
            $modeWhere[] = "pr.accepts_open_proposals = 1";
        }

        if (!$modeWhere) return;

        $where[] = "EXISTS (
            SELECT 1
            FROM property_requirements pr
            WHERE pr.property_id = p.id
              AND (" . implode(" OR ", $modeWhere) . ")
        )";
    }

    private static function applyExchangeModesForSearchRequests(array &$where, array $filters): void
    {
        $modes = self::normalizeArray($filters['exchange_modes'] ?? []);

        if (!$modes) return;

        $modeWhere = [];

        if (in_array('cash', $modes, true)) {
            $modeWhere[] = "sr.payment_mode_cash = 1";
        }

        if (
            in_array('total_swap', $modes, true) ||
            in_array('swap_plus_cash', $modes, true) ||
            in_array('multiple_swap', $modes, true) ||
            in_array('open_proposals', $modes, true)
        ) {
            $modeWhere[] = "sr.payment_mode_swap = 1";
        }

        if (!$modeWhere) return;

        $where[] = "(" . implode(" OR ", $modeWhere) . ")";
    }

    private static function applyAmenitiesForDevelopments(array &$where, array &$params, array $filters): void
    {
        $amenities = self::normalizeArray($filters['amenities'] ?? []);

        if (!$amenities) return;

        $placeholders = [];

        foreach ($amenities as $index => $amenity) {
            $key = ':amenity_' . $index;
            $placeholders[] = $key;
            $params[$key] = $amenity;
        }

        $where[] = "EXISTS (
        SELECT 1
        FROM development_amenities da
        WHERE da.development_id = d.id
          AND da.deleted_at IS NULL
          AND da.amenity_code IN (" . implode(',', $placeholders) . ")
    )";
    }

    private static function applyAmenitiesForProperties(array &$where, array &$params, array $filters): void
    {
        $amenities = self::normalizeArray($filters['amenities'] ?? []);

        if (!$amenities) return;

        $placeholders = [];

        foreach ($amenities as $index => $amenity) {
            $key = ':property_amenity_' . $index;
            $placeholders[] = $key;
            $params[$key] = $amenity;
        }

        $where[] = "EXISTS (
        SELECT 1
        FROM property_amenities pa
        WHERE pa.property_id = p.id
          AND pa.amenity_code IN (" . implode(',', $placeholders) . ")
    )";
    }

    private static function applyAmenitiesForSearchRequests(array &$where, array &$params, array $filters): void
    {
        $amenities = self::normalizeArray($filters['amenities'] ?? []);

        if (!$amenities) return;

        $placeholders = [];

        foreach ($amenities as $index => $amenity) {
            $key = ':search_request_amenity_' . $index;
            $placeholders[] = $key;
            $params[$key] = $amenity;
        }

        $where[] = "EXISTS (
        SELECT 1
        FROM search_request_amenities sra
        WHERE sra.search_request_id = sr.id
          AND sra.amenity_code IN (" . implode(',', $placeholders) . ")
    )";
    }
    private static function normalizeArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }

        return [];
    }

    private static function hasValue($value): bool
    {
        return $value !== null && $value !== '';
    }
}
