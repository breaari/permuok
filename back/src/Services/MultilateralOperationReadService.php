<?php

namespace App\Services;

use PDO;
use Exception;

class MultilateralOperationReadService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function listForUser(
        int $userId,
        array $query = []
    ): array {
        $pdo = self::db();

        $realEstateId =
            self::getUserRealEstateId(
                $pdo,
                $userId
            );

        $view = trim(
            (string)($query['view'] ?? 'active')
        );

        if (
            !in_array(
                $view,
                ['active', 'history', 'all'],
                true
            )
        ) {
            throw new Exception(
                'Vista multilateral inválida.',
                422
            );
        }

        $limit = max(
            1,
            min(
                50,
                (int)($query['limit'] ?? 12)
            )
        );

        $page = max(
            1,
            (int)($query['page'] ?? 1)
        );

        $offset =
            ($page - 1) * $limit;

        $where = [
            "
            EXISTS (
                SELECT 1
                FROM multilateral_operation_legs ml
                WHERE ml.operation_id = mo.id
                  AND (
                      ml.source_real_estate_id =
                          :real_estate_id_1
                      OR
                      ml.target_real_estate_id =
                          :real_estate_id_2
                  )
            )
            ",
        ];

        $params = [
            'real_estate_id_1' =>
            $realEstateId,

            'real_estate_id_2' =>
            $realEstateId,
        ];

        if ($view === 'active') {
            $where[] =
                "mo.status = 'detected'";
        }

        if ($view === 'history') {
            $where[] =
                "mo.status = 'archived'";
        }

        $whereSql =
            implode(
                ' AND ',
                $where
            );

        $stCount = $pdo->prepare("
            SELECT COUNT(*)
            FROM multilateral_operations mo
            WHERE {$whereSql}
        ");

        $stCount->execute($params);

        $total =
            (int)$stCount->fetchColumn();

        $st = $pdo->prepare("
            SELECT
                mo.id,
                mo.participants_count,
                mo.score,
                mo.minimum_edge_score,
                mo.average_edge_score,
                mo.status,
                mo.detected_at,
                mo.last_seen_at,
                mo.archived_at,
                mo.created_at,
                mo.updated_at,

                own_leg.position
                    AS own_position,

                own_leg.property_id
                    AS own_target_property_id,

                own_leg.offered_property_id
                    AS own_offered_property_id,

                own_leg.search_request_id
                    AS own_search_request_id,

                own_leg.score
                    AS own_leg_score,

                own_leg.signed_cash_difference
                    AS own_signed_cash_difference,

                own_leg.cash_difference
                    AS own_cash_difference,

               own_leg.cash_difference_direction
    AS own_direction,

                own_leg.comparison_currency
                    AS own_comparison_currency,

                target_property.title
                    AS target_property_title,

                target_property.price
                    AS target_property_price,

                target_property.currency
                    AS target_property_currency,

                target_property.city
                    AS target_property_city,

                target_property.zone
                    AS target_property_zone,

                offered_property.title
                    AS offered_property_title,

                offered_property.price
                    AS offered_property_price,

                offered_property.currency
                    AS offered_property_currency,

                sr.title
                    AS search_title

            FROM multilateral_operations mo

            INNER JOIN multilateral_operation_legs own_leg
                ON own_leg.operation_id = mo.id
               AND own_leg.source_real_estate_id =
                    :own_real_estate_id

            LEFT JOIN properties target_property
                ON target_property.id =
                    own_leg.property_id
               AND target_property.deleted_at IS NULL

            LEFT JOIN properties offered_property
                ON offered_property.id =
                    own_leg.offered_property_id
               AND offered_property.deleted_at IS NULL

            LEFT JOIN search_requests sr
                ON sr.id =
                    own_leg.search_request_id
               AND sr.deleted_at IS NULL

            WHERE {$whereSql}

            ORDER BY
                CASE
                    WHEN mo.status = 'detected'
                        THEN 1
                    ELSE 2
                END ASC,
                mo.score DESC,
                mo.last_seen_at DESC,
                mo.id DESC

            LIMIT {$limit}
            OFFSET {$offset}
        ");

        $listParams =
            $params;

        $listParams['own_real_estate_id'] = $realEstateId;

        $st->execute($listParams);

        $items =
            $st->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($items as &$item) {
            self::normalizeOperation(
                $item
            );
        }
        unset($item);

        return [
            'items' => $items,

            'meta' => [
                'view' => $view,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,

                'pages' =>
                max(
                    1,
                    (int)ceil(
                        $total / $limit
                    )
                ),
            ],
        ];
    }

    public static function detailForUser(
        int $userId,
        int $operationId
    ): array {
        if ($operationId <= 0) {
            throw new Exception(
                'Operación multilateral inválida.',
                422
            );
        }

        $pdo = self::db();

        $realEstateId =
            self::getUserRealEstateId(
                $pdo,
                $userId
            );

        $st = $pdo->prepare("
            SELECT
                mo.id,
                mo.participants_count,
                mo.score,
                mo.minimum_edge_score,
                mo.average_edge_score,
                mo.status,
                mo.commercial_status,
mo.confirmed_at,
mo.declined_at,
                mo.detected_at,
                mo.last_seen_at,
                mo.archived_at,
                mo.created_at,
                mo.updated_at

            FROM multilateral_operations mo

            WHERE mo.id = :operation_id

              AND EXISTS (
                  SELECT 1
                  FROM multilateral_operation_legs ml
                  WHERE ml.operation_id = mo.id
                    AND (
                        ml.source_real_estate_id =
                            :real_estate_id_1
                        OR
                        ml.target_real_estate_id =
                            :real_estate_id_2
                    )
              )

            LIMIT 1
        ");

        $st->execute([
            'operation_id' =>
            $operationId,

            'real_estate_id_1' =>
            $realEstateId,

            'real_estate_id_2' =>
            $realEstateId,
        ]);

        $operation =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$operation) {
            throw new Exception(
                'Oportunidad multilateral no encontrada.',
                404
            );
        }

        self::normalizeOperation(
            $operation
        );

        $stLegs = $pdo->prepare("
            SELECT
                ml.id,
                ml.position,
                ml.compatibility_id,
                ml.search_request_id,

                ml.source_real_estate_id,
                source_re.name
                    AS source_real_estate_name,

                ml.target_real_estate_id,
                target_re.name
                    AS target_real_estate_name,

                ml.property_id,
                target_property.title
                    AS property_title,
                target_property.price
                    AS property_price,
                target_property.currency
                    AS property_currency,
                target_property.property_type,
                target_property.city
                    AS property_city,
                target_property.zone
                    AS property_zone,

                (
                    SELECT pi.id
                    FROM property_images pi
                    WHERE pi.property_id =
                        target_property.id
                      AND pi.deleted_at IS NULL
                    ORDER BY
                        pi.is_cover DESC,
                        pi.sort_order ASC,
                        pi.id ASC
                    LIMIT 1
                ) AS property_cover_image_id,

                ml.offered_property_id,
                offered_property.title
                    AS offered_property_title,
                offered_property.price
                    AS offered_property_price,
                offered_property.currency
                    AS offered_property_currency,
                offered_property.property_type
                    AS offered_property_type,
                offered_property.city
                    AS offered_property_city,
                offered_property.zone
                    AS offered_property_zone,

                (
                    SELECT pi.id
                    FROM property_images pi
                    WHERE pi.property_id =
                        offered_property.id
                      AND pi.deleted_at IS NULL
                    ORDER BY
                        pi.is_cover DESC,
                        pi.sort_order ASC,
                        pi.id ASC
                    LIMIT 1
                ) AS offered_property_cover_image_id,

                sr.title
                    AS search_title,

                ml.exchange_offer_id,
                ml.score,

                ml.offered_value,
                ml.offered_original_value,
                ml.offered_original_currency,

                ml.target_value,
                ml.comparison_currency,

                ml.signed_cash_difference,
ml.cash_difference,
ml.cash_difference_direction
    AS direction

            FROM multilateral_operation_legs ml

            LEFT JOIN real_estates source_re
                ON source_re.id =
                    ml.source_real_estate_id
               AND source_re.deleted_at IS NULL

            LEFT JOIN real_estates target_re
                ON target_re.id =
                    ml.target_real_estate_id
               AND target_re.deleted_at IS NULL

            LEFT JOIN properties target_property
                ON target_property.id =
                    ml.property_id
               AND target_property.deleted_at IS NULL

            LEFT JOIN properties offered_property
                ON offered_property.id =
                    ml.offered_property_id
               AND offered_property.deleted_at IS NULL

            LEFT JOIN search_requests sr
                ON sr.id =
                    ml.search_request_id
               AND sr.deleted_at IS NULL

            WHERE ml.operation_id =
                :operation_id

            ORDER BY
                ml.position ASC,
                ml.id ASC
        ");

        $stLegs->execute([
            'operation_id' =>
            $operationId,
        ]);

        $legs =
            $stLegs->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($legs as &$leg) {
            $leg['is_my_leg'] =
                (int)$leg['source_real_estate_id'] === $realEstateId;

            $leg['score'] =
                (float)$leg['score'];

            $leg['offered_value'] =
                self::nullableFloat(
                    $leg['offered_value']
                );

            $leg['offered_original_value'] =
                self::nullableFloat(
                    $leg['offered_original_value']
                );

            $leg['target_value'] =
                self::nullableFloat(
                    $leg['target_value']
                );

            $leg['signed_cash_difference'] =
                self::nullableFloat(
                    $leg['signed_cash_difference']
                );

            $leg['cash_difference'] =
                self::nullableFloat(
                    $leg['cash_difference']
                );

            $leg['property_price'] =
                self::nullableFloat(
                    $leg['property_price']
                );

            $leg['offered_property_price'] =
                self::nullableFloat(
                    $leg['offered_property_price']
                );

            $leg['property_cover_image_id'] =
                $leg['property_cover_image_id'] !== null
                ? (int)$leg['property_cover_image_id']
                : null;

            $leg['offered_property_cover_image_id'] =
                $leg['offered_property_cover_image_id'] !== null
                ? (int)$leg['offered_property_cover_image_id']
                : null;
        }
        unset($leg);
        $stMyResponse = $pdo->prepare("
    SELECT response
    FROM multilateral_operation_responses
    WHERE operation_id = :operation_id
      AND real_estate_id = :real_estate_id
    LIMIT 1
");

        $stMyResponse->execute([
            'operation_id' =>
            $operationId,

            'real_estate_id' =>
            $realEstateId,
        ]);

        $myResponse =
            $stMyResponse->fetchColumn()
            ?: null;

        $contacts = [];

        if (
            ($operation['commercial_status'] ?? null)
            === 'confirmed'
        ) {
            $stContacts = $pdo->prepare("
        SELECT DISTINCT
            mor.real_estate_id,
            re.name AS real_estate_name,

            u.id AS user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone

        FROM multilateral_operation_responses mor

        INNER JOIN users u
            ON u.id = mor.responded_by_user_id
           AND u.deleted_at IS NULL
           AND u.is_active = 1

        INNER JOIN real_estates re
            ON re.id = mor.real_estate_id
           AND re.deleted_at IS NULL

        WHERE mor.operation_id = :operation_id
          AND mor.response = 'interested'

        ORDER BY
            re.name ASC,
            u.first_name ASC,
            u.last_name ASC
    ");

            $stContacts->execute([
                'operation_id' => $operationId,
            ]);

            $contacts =
                $stContacts->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

            foreach ($contacts as &$contact) {
                $contact['real_estate_id'] =
                    (int)$contact['real_estate_id'];

                $contact['user_id'] =
                    (int)$contact['user_id'];

                $contact['is_me'] =
                    $contact['real_estate_id']
                    === $realEstateId;
            }

            unset($contact);
        }

        return [
            'operation' => $operation,
            'legs' => $legs,
            'my_real_estate_id' => $realEstateId,
            'my_response' => $myResponse,
            'contacts' => $contacts,
        ];
    }

    private static function getUserRealEstateId(
        PDO $pdo,
        int $userId
    ): int {
        $st = $pdo->prepare("
            SELECT
                id,
                real_estate_id
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $userId,
        ]);

        $user =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$user) {
            throw new Exception(
                'Usuario no encontrado.',
                404
            );
        }

        $realEstateId =
            (int)(
                $user['real_estate_id']
                ?? 0
            );

        if ($realEstateId <= 0) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria.',
                422
            );
        }

        return $realEstateId;
    }

    private static function normalizeOperation(
        array &$operation
    ): void {
        $operation['id'] =
            (int)$operation['id'];

        $operation['participants_count'] =
            (int)$operation['participants_count'];

        $operation['score'] =
            (float)$operation['score'];

        $operation['minimum_edge_score'] =
            (float)$operation['minimum_edge_score'];

        $operation['average_edge_score'] =
            (float)$operation['average_edge_score'];

        if (
            array_key_exists(
                'own_position',
                $operation
            )
        ) {
            $operation['own_position'] =
                (int)$operation['own_position'];

            $operation['own_leg_score'] =
                (float)$operation['own_leg_score'];

            $operation['own_signed_cash_difference'] =
                self::nullableFloat(
                    $operation['own_signed_cash_difference']
                );

            $operation['own_cash_difference'] =
                self::nullableFloat(
                    $operation['own_cash_difference']
                );

            $operation['target_property_price'] =
                self::nullableFloat(
                    $operation['target_property_price']
                );

            $operation['offered_property_price'] =
                self::nullableFloat(
                    $operation['offered_property_price']
                );
        }
    }

    private static function nullableFloat(
        mixed $value
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }
}
