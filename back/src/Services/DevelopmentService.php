<?php

namespace App\Services;

use PDO;
use Exception;

class DevelopmentService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;

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

        return '/development-images/' . $imageId . '/view';
    }

    private static function getValidUser(int $userId): array
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

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        return $user;
    }

    private static function getValidPublisherUser(int $userId): array
    {
        $user = self::getValidUser($userId);

        if (!in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT], true)) {
            throw new Exception("No tenés permisos para publicar desarrollos");
        }

        return $user;
    }

    private static function assertMembershipAllowsPublishing(int $realEstateId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT id, status, can_publish_projects, end_date
        FROM memberships
        WHERE real_estate_id = :real_estate_id
          AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
        $st->execute(['real_estate_id' => $realEstateId]);
        $membership = $st->fetch();

        if (!$membership) {
            throw new Exception("La inmobiliaria no tiene una membresía activa");
        }

        if ((int)($membership['status'] ?? -1) !== 1) {
            throw new Exception("La membresía de la inmobiliaria no está activa");
        }

        if ((int)($membership['can_publish_projects'] ?? 0) !== 1) {
            throw new Exception("Tu plan no permite publicar desarrollos");
        }

        if (
            !empty($membership['end_date']) &&
            strtotime((string)$membership['end_date']) < strtotime(date('Y-m-d'))
        ) {
            throw new Exception("La membresía de la inmobiliaria está vencida");
        }
    }

    private static function getOwnedDevelopment(int $userId, int $developmentId): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            'id' => $developmentId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $development = $st->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        return [$user, $development];
    }

    private static function getVisibleDevelopment(int $userId, int $developmentId): array
    {
        self::getValidUser($userId);
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $developmentId]);
        $development = $st->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        return $development;
    }

    private static function validatePayload(array $data, bool $partial = false): array
    {
        $payload = [
            'title' => trim((string)($data['title'] ?? '')),
            'slug' => trim((string)($data['slug'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'short_description' => trim((string)($data['short_description'] ?? '')),

            'developer_name' => trim((string)($data['developer_name'] ?? '')),
            'construction_company' => trim((string)($data['construction_company'] ?? '')),

            'development_stage' => trim((string)($data['development_stage'] ?? '')),
            'delivery_date_estimated' => $data['delivery_date_estimated'] ?? null,

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

            'price_from' => $data['price_from'] ?? null,
            'price_to' => $data['price_to'] ?? null,
            'currency' => trim((string)($data['currency'] ?? 'USD')),

            'total_units' => $data['total_units'] ?? null,
            'available_units' => $data['available_units'] ?? null,

            'whatsapp_url' => trim((string)($data['whatsapp_url'] ?? '')),
            'brochure_url' => trim((string)($data['brochure_url'] ?? '')),
            'video_url' => trim((string)($data['video_url'] ?? '')),
            'amenities' => $data['amenities'] ?? [],
        ];

        $validStages = ['land', 'prelaunch', 'launch', 'presale', 'under_construction', 'finished'];
        $validCurrencies = ['ARS', 'USD'];

        if ($payload['development_stage'] !== '' && !in_array($payload['development_stage'], $validStages, true)) {
            throw new Exception("Etapa del desarrollo inválida");
        }

        if ($payload['currency'] !== '' && !in_array($payload['currency'], $validCurrencies, true)) {
            throw new Exception("Moneda inválida");
        }

        if (array_key_exists('amenities', $data) && !is_array($data['amenities'])) {
            throw new Exception("Amenities debe ser un array");
        }

        if (!$partial) {
            $required = [
                'title' => 'El título es obligatorio',
                'description' => 'La descripción es obligatoria',
                'development_stage' => 'La etapa del desarrollo es obligatoria',
                'country' => 'El país es obligatorio',
                'province' => 'La provincia es obligatoria',
                'city' => 'La ciudad es obligatoria',
            ];

            foreach ($required as $field => $message) {
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
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);
        $payload = self::validatePayload($data, false);

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
        INSERT INTO developments (
            real_estate_id,
            created_by_user_id,
            updated_by_user_id,
            title,
            slug,
            description,
            short_description,
            developer_name,
            construction_company,
            status,
            visibility,
            development_stage,
            delivery_date_estimated,
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
            price_from,
            price_to,
            currency,
            total_units,
            available_units,
            whatsapp_url,
            brochure_url,
            video_url
        ) VALUES (
            :real_estate_id,
            :created_by_user_id,
            :updated_by_user_id,
            :title,
            :slug,
            :description,
            :short_description,
            :developer_name,
            :construction_company,
            'draft',
            'public_network',
            :development_stage,
            :delivery_date_estimated,
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
            :price_from,
            :price_to,
            :currency,
            :total_units,
            :available_units,
            :whatsapp_url,
            :brochure_url,
            :video_url
        )
    ");

            $st->execute([
                'real_estate_id' => (int)$user['real_estate_id'],
                'created_by_user_id' => (int)$user['id'],
                'updated_by_user_id' => (int)$user['id'],
                'title' => $payload['title'],
                'slug' => $payload['slug'] !== '' ? $payload['slug'] : null,
                'description' => $payload['description'],
                'short_description' => $payload['short_description'] !== '' ? $payload['short_description'] : null,
                'developer_name' => $payload['developer_name'] !== '' ? $payload['developer_name'] : null,
                'construction_company' => $payload['construction_company'] !== '' ? $payload['construction_company'] : null,
                'development_stage' => $payload['development_stage'],
                'delivery_date_estimated' => $payload['delivery_date_estimated'] ?: null,
                'country_code' => $payload['country_code'] !== '' ? $payload['country_code'] : null,
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
                'price_from' => $payload['price_from'] !== '' ? $payload['price_from'] : null,
                'price_to' => $payload['price_to'] !== '' ? $payload['price_to'] : null,
                'currency' => $payload['currency'],
                'total_units' => $payload['total_units'] !== '' ? $payload['total_units'] : null,
                'available_units' => $payload['available_units'] !== '' ? $payload['available_units'] : null,
                'whatsapp_url' => $payload['whatsapp_url'] !== '' ? $payload['whatsapp_url'] : null,
                'brochure_url' => $payload['brochure_url'] !== '' ? $payload['brochure_url'] : null,
                'video_url' => $payload['video_url'] !== '' ? $payload['video_url'] : null,
            ]);

            $id = (int)$pdo->lastInsertId();

            self::syncAmenities($pdo, $id, $payload['amenities']);

            $pdo->commit();

            return self::getDetail($userId, $id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateDraft(int $userId, int $developmentId, array $data): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        if (in_array($development['status'], ['closed'], true)) {
            throw new Exception("El desarrollo no puede editarse en su estado actual");
        }

        $payload = self::validatePayload($data, true);
        $pdo = self::db();

        $fields = [];
        $params = [
            'id' => $developmentId,
            'updated_by_user_id' => (int)$user['id'],
        ];

        $map = [
            'title',
            'slug',
            'description',
            'short_description',
            'developer_name',
            'construction_company',
            'development_stage',
            'delivery_date_estimated',
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
            'price_from',
            'price_to',
            'currency',
            'total_units',
            'available_units',
            'whatsapp_url',
            'brochure_url',
            'video_url',
        ];

        foreach ($map as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $payload[$field];
                $params[$field] = $value === '' ? null : $value;
            }
        }

        $fields[] = "updated_by_user_id = :updated_by_user_id";

        $sql = "
            UPDATE developments
            SET " . implode(", ", $fields) . "
            WHERE id = :id
            LIMIT 1
        ";

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            if (array_key_exists('amenities', $data)) {
                self::syncAmenities($pdo, $developmentId, $payload['amenities']);
            }

            $pdo->commit();

            return self::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetail(int $userId, int $developmentId): array
    {
        $user = self::getValidUser($userId);
        $pdo = self::db();

        $isPublisherRole = in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT], true);

        if ($isPublisherRole) {
            try {
                [, $development] = self::getOwnedDevelopment($userId, $developmentId);
            } catch (\Throwable $e) {
                $development = self::getVisibleDevelopment($userId, $developmentId);

                if (($development['status'] ?? '') !== 'published') {
                    throw new Exception("Desarrollo no encontrado");
                }
            }
        } else {
            $development = self::getVisibleDevelopment($userId, $developmentId);

            if (($development['status'] ?? '') !== 'published') {
                throw new Exception("Desarrollo no encontrado");
            }
        }

        $stImages = $pdo->prepare("
            SELECT id, development_id, file_path, sort_order, is_cover, created_at
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");
        $stImages->execute(['development_id' => $developmentId]);
        $images = $stImages->fetchAll() ?: [];

        $images = array_map(function ($img) {
            $img['view_url'] = self::imageViewUrl(isset($img['id']) ? (int)$img['id'] : null);
            return $img;
        }, $images);

        $stTypes = $pdo->prepare("
            SELECT *
            FROM development_unit_types
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");
        $stTypes->execute(['development_id' => $developmentId]);
        $unitTypes = $stTypes->fetchAll() ?: [];

        $stAmenities = $pdo->prepare("
            SELECT amenity_code
            FROM development_amenities
            WHERE development_id = :development_id
            ORDER BY id ASC
        ");
        $stAmenities->execute(['development_id' => $developmentId]);
        $amenities = $stAmenities->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'development' => $development,
            'images' => $images,
            'unit_types' => $unitTypes,
            'amenities' => $amenities,
        ];
    }

    public static function listMyDevelopments(int $userId, array $filters = []): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);

        $where = [
            "d.real_estate_id = :real_estate_id",
            "d.deleted_at IS NULL",
        ];

        $params = [
            'real_estate_id' => (int)$user['real_estate_id'],
        ];

        if (!empty($filters['status'])) {
            $where[] = "d.status = :status";
            $params['status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['q'])) {
            $where[] = "(
            d.title LIKE :q
            OR d.description LIKE :q
            OR d.city LIKE :q
            OR d.zone LIKE :q
            OR d.province LIKE :q
            OR CAST(d.id AS CHAR) LIKE :q
        )";
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $limit = (int)($filters['limit'] ?? 20);
        if ($limit <= 0) $limit = 20;
        if ($limit > 100) $limit = 100;

        $page = (int)($filters['page'] ?? 1);
        if ($page <= 0) $page = 1;

        $offset = ($page - 1) * $limit;

        $countSql = "
        SELECT COUNT(*) AS total
        FROM developments d
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
            d.*,
            (
                SELECT di.id
                FROM development_images di
                WHERE di.development_id = d.id
                  AND di.deleted_at IS NULL
                ORDER BY di.is_cover DESC, di.sort_order ASC, di.id ASC
                LIMIT 1
            ) AS cover_image_id,
            (
                SELECT CONCAT(
                    '[',
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', di.id,
                            'view_url', CONCAT('/development-images/', di.id, '/view'),
                            'sort_order', di.sort_order,
                            'is_cover', di.is_cover
                        )
                        ORDER BY di.is_cover DESC, di.sort_order ASC, di.id ASC
                        SEPARATOR ','
                    ),
                    ']'
                )
                FROM development_images di
                WHERE di.development_id = d.id
                  AND di.deleted_at IS NULL
            ) AS images_json,
            (
                SELECT COUNT(*)
                FROM development_unit_types dut
                WHERE dut.development_id = d.id
                  AND dut.deleted_at IS NULL
            ) AS unit_types_count,
            (
                SELECT GROUP_CONCAT(DISTINCT da.amenity_code ORDER BY da.amenity_code SEPARATOR ',')
                FROM development_amenities da
                WHERE da.development_id = d.id
            ) AS amenities,
            (
                SELECT COUNT(*)
                FROM development_amenities da
                WHERE da.development_id = d.id
            ) AS amenities_count
        FROM developments d
        WHERE " . implode(" AND ", $where) . "
        ORDER BY d.created_at DESC
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

        $items = array_map(function ($row) {
            $row['cover_image_id'] = !empty($row['cover_image_id']) ? (int)$row['cover_image_id'] : null;
            $row['cover_image_url'] = self::imageViewUrl($row['cover_image_id']);

            $row['images'] = [];

            if (!empty($row['images_json'])) {
                $decoded = json_decode((string)$row['images_json'], true);
                $row['images'] = is_array($decoded) ? $decoded : [];
            }

            unset($row['images_json']);

            return $row;
        }, $items);

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
    public static function listExploreDevelopments(int $userId, array $filters = []): array
    {
        $pdo = self::db();
        self::getValidUser($userId);

        $where = [
            "d.deleted_at IS NULL",
            "d.status = 'published'",
        ];

        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(
            d.title LIKE :q
            OR d.description LIKE :q
            OR d.city LIKE :q
            OR d.zone LIKE :q
            OR d.province LIKE :q
            OR CAST(d.id AS CHAR) LIKE :q
        )";
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        if (!empty($filters['development_stage'])) {
            $where[] = "d.development_stage = :development_stage";
            $params['development_stage'] = trim((string)$filters['development_stage']);
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
                FROM development_amenities da_filter_$i
                WHERE da_filter_$i.development_id = d.id
                  AND da_filter_$i.amenity_code = :$param
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
        FROM developments d
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
            d.*,
            (
                SELECT di.id
                FROM development_images di
                WHERE di.development_id = d.id
                  AND di.deleted_at IS NULL
                ORDER BY di.is_cover DESC, di.sort_order ASC, di.id ASC
                LIMIT 1
            ) AS cover_image_id,
            (
                SELECT CONCAT(
                    '[',
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', di.id,
                            'view_url', CONCAT('/development-images/', di.id, '/view'),
                            'sort_order', di.sort_order,
                            'is_cover', di.is_cover
                        )
                        ORDER BY di.is_cover DESC, di.sort_order ASC, di.id ASC
                        SEPARATOR ','
                    ),
                    ']'
                )
                FROM development_images di
                WHERE di.development_id = d.id
                  AND di.deleted_at IS NULL
            ) AS images_json,
            (
                SELECT COUNT(*)
                FROM development_unit_types dut
                WHERE dut.development_id = d.id
                  AND dut.deleted_at IS NULL
            ) AS unit_types_count,
            (
                SELECT GROUP_CONCAT(DISTINCT da.amenity_code ORDER BY da.amenity_code SEPARATOR ',')
                FROM development_amenities da
                WHERE da.development_id = d.id
            ) AS amenities,
            (
                SELECT COUNT(*)
                FROM development_amenities da
                WHERE da.development_id = d.id
            ) AS amenities_count
        FROM developments d
        WHERE " . implode(" AND ", $where) . "
        ORDER BY d.published_at DESC, d.created_at DESC
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

        $items = array_map(function ($row) {
            $row['cover_image_id'] = !empty($row['cover_image_id']) ? (int)$row['cover_image_id'] : null;
            $row['cover_image_url'] = self::imageViewUrl($row['cover_image_id']);

            $row['images'] = [];

            if (!empty($row['images_json'])) {
                $decoded = json_decode((string)$row['images_json'], true);
                $row['images'] = is_array($decoded) ? $decoded : [];
            }

            unset($row['images_json']);

            return $row;
        }, $items);

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
    public static function publish(int $userId, int $developmentId): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);
        $pdo = self::db();

        if (!in_array($development['status'], ['draft', 'paused', 'archived'], true)) {
            throw new Exception("El desarrollo no puede publicarse en su estado actual");
        }

        if (empty($development['title']) || empty($development['description']) || empty($development['development_stage']) || empty($development['country']) || empty($development['province']) || empty($development['city'])) {
            throw new Exception("Faltan datos obligatorios para publicar");
        }

        $stImages = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");
        $stImages->execute(['development_id' => $developmentId]);
        $imageCount = (int)($stImages->fetch()['total'] ?? 0);

        if ($imageCount < 1) {
            throw new Exception("Debés subir al menos una imagen para publicar");
        }

        $stTypes = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM development_unit_types
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");
        $stTypes->execute(['development_id' => $developmentId]);
        $unitTypesCount = (int)($stTypes->fetch()['total'] ?? 0);

        if ($unitTypesCount < 1) {
            throw new Exception("Debés cargar al menos una tipología para publicar");
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $development['status'];

            $st = $pdo->prepare("
                UPDATE developments
                SET
                    status = 'published',
                    published_at = CASE
                        WHEN published_at IS NULL THEN NOW()
                        ELSE published_at
                    END,
                    updated_by_user_id = :updated_by_user_id
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([
                'updated_by_user_id' => (int)$user['id'],
                'id' => $developmentId,
            ]);

            $hist = $pdo->prepare("
                INSERT INTO development_status_history (
                    development_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    change_reason,
                    change_source
                ) VALUES (
                    :development_id,
                    :old_status,
                    'published',
                    :changed_by_user_id,
                    NULL,
                    'user'
                )
            ");
            $hist->execute([
                'development_id' => $developmentId,
                'old_status' => $oldStatus,
                'changed_by_user_id' => (int)$user['id'],
            ]);

            $pdo->commit();
            return self::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function pause(int $userId, int $developmentId): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        if ($development['status'] !== 'published') {
            throw new Exception("Solo se pueden pausar desarrollos publicados");
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                UPDATE developments
                SET
                    status = 'paused',
                    paused_at = NOW(),
                    updated_by_user_id = :updated_by_user_id
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([
                'updated_by_user_id' => (int)$user['id'],
                'id' => $developmentId,
            ]);

            $hist = $pdo->prepare("
                INSERT INTO development_status_history (
                    development_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    change_reason,
                    change_source
                ) VALUES (
                    :development_id,
                    'published',
                    'paused',
                    :changed_by_user_id,
                    NULL,
                    'user'
                )
            ");
            $hist->execute([
                'development_id' => $developmentId,
                'changed_by_user_id' => (int)$user['id'],
            ]);

            $pdo->commit();
            return self::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function archive(int $userId, int $developmentId): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        if (!in_array($development['status'], ['draft', 'paused', 'published'], true)) {
            throw new Exception("El desarrollo no puede archivarse en su estado actual");
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $development['status'];

            $st = $pdo->prepare("
                UPDATE developments
                SET
                    status = 'archived',
                    archived_at = NOW(),
                    updated_by_user_id = :updated_by_user_id
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([
                'updated_by_user_id' => (int)$user['id'],
                'id' => $developmentId,
            ]);

            $hist = $pdo->prepare("
                INSERT INTO development_status_history (
                    development_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    change_reason,
                    change_source
                ) VALUES (
                    :development_id,
                    :old_status,
                    'archived',
                    :changed_by_user_id,
                    NULL,
                    'user'
                )
            ");
            $hist->execute([
                'development_id' => $developmentId,
                'old_status' => $oldStatus,
                'changed_by_user_id' => (int)$user['id'],
            ]);

            $pdo->commit();
            return self::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function close(int $userId, int $developmentId): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        if ($development['status'] !== 'published') {
            throw new Exception("Solo se pueden cerrar desarrollos publicados");
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
            UPDATE developments
            SET
                status = 'closed',
                closed_at = NOW(),
                updated_by_user_id = :updated_by_user_id
            WHERE id = :id
            LIMIT 1
        ");
            $st->execute([
                'updated_by_user_id' => (int)$user['id'],
                'id' => $developmentId,
            ]);

            $hist = $pdo->prepare("
            INSERT INTO development_status_history (
                development_id,
                old_status,
                new_status,
                changed_by_user_id,
                change_reason,
                change_source
            ) VALUES (
                :development_id,
                'published',
                'closed',
                :changed_by_user_id,
                NULL,
                'user'
            )
        ");
            $hist->execute([
                'development_id' => $developmentId,
                'changed_by_user_id' => (int)$user['id'],
            ]);

            $pdo->commit();
            return self::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $userId, int $developmentId): array
    {
        [$user, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        if (!in_array($development['status'], ['draft', 'paused', 'published', 'archived', 'closed'], true)) {
            throw new Exception("El desarrollo no puede eliminarse en su estado actual");
        }

        $pdo->beginTransaction();

        try {
            $oldStatus = $development['status'];

            $st = $pdo->prepare("
                UPDATE developments
                SET
                    deleted_at = NOW(),
                    updated_by_user_id = :updated_by_user_id
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([
                'updated_by_user_id' => (int)$user['id'],
                'id' => $developmentId,
            ]);

            $hist = $pdo->prepare("
                INSERT INTO development_status_history (
                    development_id,
                    old_status,
                    new_status,
                    changed_by_user_id,
                    change_reason,
                    change_source
                ) VALUES (
                    :development_id,
                    :old_status,
                    'deleted',
                    :changed_by_user_id,
                    'user_deleted',
                    'user'
                )
            ");
            $hist->execute([
                'development_id' => $developmentId,
                'old_status' => $oldStatus,
                'changed_by_user_id' => (int)$user['id'],
            ]);

            $pdo->commit();

            return [
                'ok' => true,
                'development_id' => $developmentId,
                'deleted_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function syncAmenities(PDO $pdo, int $developmentId, array $items): void
    {
        $pdo->prepare("
        DELETE FROM development_amenities
        WHERE development_id = :development_id
    ")->execute([
            'development_id' => $developmentId,
        ]);

        $clean = [];

        foreach ($items as $item) {
            $item = trim((string)$item);

            if ($item === '') {
                continue;
            }

            $clean[$item] = true;
        }

        foreach (array_keys($clean) as $item) {
            $pdo->prepare("
            INSERT INTO development_amenities (
                development_id,
                amenity_code
            ) VALUES (
                :development_id,
                :amenity_code
            )
        ")->execute([
                'development_id' => $developmentId,
                'amenity_code' => $item,
            ]);
        }
    }
}
