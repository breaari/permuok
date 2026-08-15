<?php

namespace App\Services;

use App\DB;
use PDO;
use Exception;
use Throwable;

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

        return [
            'search_request' => $item,
            'property_types' => $item['property_types'],
            'amenities' => $item['amenities'],
            'access' => [
                'can_edit' => in_array($item['status'], ['draft', 'paused', 'archived', 'published'], true),
                'can_publish' => in_array($item['status'], ['draft', 'paused', 'archived'], true),
                'can_pause' => $item['status'] === 'published',
                'can_archive' => in_array($item['status'], ['draft', 'paused', 'published'], true),
                'can_delete' => $item['status'] !== 'deleted',
            ],
        ];
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
        [, $current] = self::getOwnedSearchRequestRow($userId, $id);

        if (!in_array($current['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se puede editar la búsqueda en el estado actual");
        }

        self::validate($data, false);

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

    public static function publish(int $userId, int $id): array
    {
        $result = self::changeStatus(
            $userId,
            $id,
            'published',
            true
        );

        self::queueCompatibilityRecalculation($id);

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

    private static function validate(array $data, bool $strict = true): void
    {
        if ($strict) {
            if (empty(trim((string)($data['title'] ?? '')))) {
                throw new Exception("Título requerido");
            }

            if (empty(trim((string)($data['description'] ?? '')))) {
                throw new Exception("Descripción requerida");
            }

            if (empty(trim((string)($data['country_code'] ?? ''))) || empty(trim((string)($data['province'] ?? '')))) {
                throw new Exception("Ubicación requerida");
            }
        }

        if (array_key_exists('payment_mode_cash', $data) || array_key_exists('payment_mode_swap', $data) || $strict) {
            $cash = !empty($data['payment_mode_cash']) ? 1 : 0;
            $swap = !empty($data['payment_mode_swap']) ? 1 : 0;

            if ($cash !== 1 && $swap !== 1) {
                throw new Exception("Debe elegir al menos una forma de pago");
            }
        }

        if ($strict) {
            $types = $data['property_types'] ?? [];
            if (!is_array($types) || count($types) === 0) {
                throw new Exception("Debés indicar al menos un tipo de propiedad buscada");
            }
        }

        if (
            isset($data['min_value'], $data['max_value']) &&
            $data['min_value'] !== null &&
            $data['max_value'] !== null &&
            $data['min_value'] !== '' &&
            $data['max_value'] !== '' &&
            (float)$data['min_value'] > (float)$data['max_value']
        ) {
            throw new Exception("El valor mínimo no puede ser mayor al valor máximo");
        }

        if (
            isset($data['cash_difference_max']) &&
            $data['cash_difference_max'] !== null &&
            $data['cash_difference_max'] !== '' &&
            (float)$data['cash_difference_max'] < 0
        ) {
            throw new Exception("La diferencia máxima en efectivo no puede ser negativa");
        }
    }

    private static function syncPropertyTypes(PDO $pdo, int $id, array $types): void
    {
        $pdo->prepare("
            DELETE FROM search_request_property_types
            WHERE search_request_id = :id
        ")->execute(['id' => $id]);

        $clean = [];
        foreach ($types as $type) {
            $type = trim((string)$type);
            if ($type === '') continue;
            $clean[$type] = true;
        }

        foreach (array_keys($clean) as $type) {
            $pdo->prepare("
                INSERT INTO search_request_property_types (search_request_id, property_type)
                VALUES (:search_request_id, :property_type)
            ")->execute([
                'search_request_id' => $id,
                'property_type' => $type,
            ]);
        }
    }

    private static function syncAmenities(PDO $pdo, int $id, array $items): void
    {
        $pdo->prepare("
            DELETE FROM search_request_amenities
            WHERE search_request_id = :id
        ")->execute(['id' => $id]);

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item === '') continue;
            $clean[$item] = true;
        }

        foreach (array_keys($clean) as $item) {
            $pdo->prepare("
                INSERT INTO search_request_amenities (search_request_id, amenity_code)
                VALUES (:search_request_id, :amenity_code)
            ")->execute([
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

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function nullableNumber(mixed $value): string|int|float|null
    {
        if ($value === null || $value === '') return null;
        return $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int)$value;
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
    SELECT *
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
