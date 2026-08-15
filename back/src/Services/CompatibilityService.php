<?php

namespace App\Services;

use PDO;
use Exception;
use App\Services\ConversationService;

class CompatibilityService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    /**
     * Lista recomendaciones donde participa
     * la inmobiliaria del usuario autenticado.
     */
    public static function listRecommendations(
        int $userId,
        array $query = []
    ): array {
        $pdo = self::db();

        $user = self::getUser($pdo, $userId);

        $realEstateId = (int)($user['real_estate_id'] ?? 0);

        if ($realEstateId <= 0) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria.',
                422
            );
        }

        $limit = max(
            1,
            min(50, (int)($query['limit'] ?? 12))
        );

        $page = max(
            1,
            (int)($query['page'] ?? 1)
        );

        $offset = ($page - 1) * $limit;

        /*
     * active:
     *   matches actualmente accionables.
     *
     * history:
     *   descartados o archivados.
     *
     * all:
     *   todos.
     */
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
                'Vista de compatibilidades inválida.',
                422
            );
        }

        $where = [
            'c.deleted_at IS NULL',
            "
            (
                c.source_real_estate_id = :real_estate_id
                OR
                c.target_real_estate_id = :real_estate_id
            )
        ",
            "c.compatibility_type = 'property_search_request'",
        ];

        $params = [
            'real_estate_id' => $realEstateId,
        ];

        /*
     * Vista activa.
     */
        if ($view === 'active') {
            $where[] = "
            c.status IN (
                'detected',
                'one_side_interested',
                'mutual_interest',
                'chat_enabled'
            )
        ";
        }

        /*
     * Historial.
     *
     * Incluye tanto descartados como
     * compatibilidades archivadas por el motor.
     */
        if ($view === 'history') {
            $where[] = "
            c.status IN (
                'dismissed',
                'archived'
            )
        ";
        }

        /*
     * Filtro por nivel.
     */
        if (!empty($query['match_level'])) {
            $matchLevel = trim(
                (string)$query['match_level']
            );

            $validMatchLevels = [
                'low',
                'medium',
                'high',
                'total',
            ];

            if (
                !in_array(
                    $matchLevel,
                    $validMatchLevels,
                    true
                )
            ) {
                throw new Exception(
                    'Nivel de compatibilidad inválido.',
                    422
                );
            }

            $where[] = 'c.match_level = :match_level';
            $params['match_level'] = $matchLevel;
        }

        /*
     * Score mínimo.
     */
        if (
            isset($query['min_score']) &&
            $query['min_score'] !== ''
        ) {
            if (!is_numeric($query['min_score'])) {
                throw new Exception(
                    'El puntaje mínimo es inválido.',
                    422
                );
            }

            $minScore = max(
                0,
                min(
                    100,
                    (float)$query['min_score']
                )
            );

            $where[] = 'c.score >= :min_score';
            $params['min_score'] = $minScore;
        }

        /*
     * Solo compatibilidades donde
     * este lado todavía no respondió.
     *
     * Tiene sentido principalmente
     * en la vista activa.
     */
        $onlyPending =
            isset($query['pending']) &&
            in_array(
                strtolower(
                    (string)$query['pending']
                ),
                ['1', 'true'],
                true
            );

        if ($onlyPending) {
            $where[] = "
            (
                (
                    c.source_real_estate_id = :pending_source_re
                    AND c.source_response = 'pending'
                )
                OR
                (
                    c.target_real_estate_id = :pending_target_re
                    AND c.target_response = 'pending'
                )
            )
        ";

            $params['pending_source_re'] =
                $realEstateId;

            $params['pending_target_re'] =
                $realEstateId;
        }

        $whereSql = implode(
            "\n AND ",
            $where
        );

        /*
     * Total.
     */
        $countSql = "
        SELECT COUNT(*)
        FROM compatibilities c
        WHERE {$whereSql}
    ";

        $stCount = $pdo->prepare(
            $countSql
        );

        self::executeStatement(
            $stCount,
            $params
        );

        $total = (int)$stCount->fetchColumn();

        /*
     * Datos.
     */
        $sql = "
        SELECT
            c.id,
            c.compatibility_type,
            c.source_type,
            c.source_id,
            c.target_type,
            c.target_id,

            c.property_id,
            c.search_request_id,

            c.source_real_estate_id,
            c.target_real_estate_id,

            c.detected_from,

            c.score,
            c.match_level,
            c.match_reason,
            c.reasons_json,

            c.source_response,
            c.target_response,
            c.source_responded_at,
            c.target_responded_at,

            c.status,
            c.calculated_at,
            c.chat_enabled_at,
            c.dismissed_at,
            c.archived_at,
            c.created_at,
            c.updated_at,

            (
                SELECT conv.id
                FROM conversations conv
                WHERE conv.compatibility_id = c.id
                  AND conv.deleted_at IS NULL
                LIMIT 1
            ) AS conversation_id,

            p.title AS property_title,
            p.description AS property_description,
            p.property_type,
            p.price AS property_price,
            p.currency AS property_currency,
            p.country AS property_country,
            p.province AS property_province,
            p.city AS property_city,
            p.zone AS property_zone,
            p.total_area AS property_total_area,
            p.covered_area AS property_covered_area,
            p.bedrooms AS property_bedrooms,
            p.bathrooms AS property_bathrooms,
            p.garages AS property_garages,
            p.real_estate_id AS property_real_estate_id,

            (
                SELECT pi.id
                FROM property_images pi
                WHERE pi.property_id = p.id
                  AND pi.deleted_at IS NULL
                ORDER BY
                    pi.is_cover DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            ) AS property_cover_image_id,

            sr.title AS search_title,
            sr.description AS search_description,
            sr.currency AS search_currency,
            sr.min_value AS search_min_value,
            sr.max_value AS search_max_value,
            sr.country AS search_country,
            sr.province AS search_province,
            sr.city AS search_city,
            sr.zone AS search_zone,
            sr.urgency AS search_urgency,
            sr.payment_mode_cash,
            sr.payment_mode_swap,
            sr.cash_difference_max,
            sr.cash_difference_currency,
            sr.open_to_other_zones,
            sr.real_estate_id AS search_real_estate_id

        FROM compatibilities c

        INNER JOIN properties p
            ON p.id = c.property_id
           AND p.deleted_at IS NULL

        INNER JOIN search_requests sr
            ON sr.id = c.search_request_id
           AND sr.deleted_at IS NULL

        WHERE {$whereSql}

        ORDER BY
            CASE
                WHEN c.status = 'chat_enabled' THEN 1
                WHEN c.status = 'mutual_interest' THEN 2
                WHEN c.status = 'one_side_interested' THEN 3
                WHEN c.status = 'detected' THEN 4
                WHEN c.status = 'dismissed' THEN 5
                WHEN c.status = 'archived' THEN 6
                ELSE 7
            END ASC,
            c.score DESC,
            c.calculated_at DESC,
            c.id DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ";

        $st = $pdo->prepare($sql);

        self::executeStatement(
            $st,
            $params
        );

        $rows = $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        $items = [];

        foreach ($rows as $row) {
            $items[] =
                self::formatRecommendation(
                    $row,
                    $realEstateId
                );
        }

        $pages = max(
            1,
            (int)ceil(
                $total / $limit
            )
        );

        return [
            'items' => $items,

            'meta' => [
                'view' => $view,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
                'total' => $total,

                'from' =>
                $total > 0
                    ? $offset + 1
                    : 0,

                'to' =>
                $total > 0
                    ? min(
                        $offset + $limit,
                        $total
                    )
                    : 0,
            ],
        ];
    }

    public static function getRecommendationDetail(
        int $userId,
        int $compatibilityId
    ): array {
        $pdo = self::db();

        $user = self::getUser($pdo, $userId);

        $realEstateId = (int)($user['real_estate_id'] ?? 0);

        if ($realEstateId <= 0) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria.',
                422
            );
        }

        if ($compatibilityId <= 0) {
            throw new Exception(
                'Compatibilidad inválida.',
                422
            );
        }

        $sql = "
        SELECT
            c.id,
            c.compatibility_type,
            c.source_type,
            c.source_id,
            c.target_type,
            c.target_id,

            c.property_id,
            c.search_request_id,

            c.source_real_estate_id,
            c.target_real_estate_id,

            c.detected_from,

            c.score,
            c.match_level,
            c.match_reason,
            c.reasons_json,

            c.source_response,
            c.target_response,
            c.source_responded_at,
            c.target_responded_at,

            c.status,
            c.calculated_at,
            c.chat_enabled_at,
            c.dismissed_at,
c.archived_at,
            c.created_at,
            c.updated_at,
(
    SELECT conv.id
    FROM conversations conv
    WHERE conv.compatibility_id = c.id
      AND conv.deleted_at IS NULL
    LIMIT 1
) AS conversation_id,
            p.title AS property_title,
            p.description AS property_description,
            p.property_type,
            p.price AS property_price,
            p.currency AS property_currency,
            p.country AS property_country,
            p.province AS property_province,
            p.city AS property_city,
            p.zone AS property_zone,
            p.total_area AS property_total_area,
            p.covered_area AS property_covered_area,
            p.bedrooms AS property_bedrooms,
            p.bathrooms AS property_bathrooms,
            p.garages AS property_garages,
            p.real_estate_id AS property_real_estate_id,

            (
                SELECT pi.id
                FROM property_images pi
                WHERE pi.property_id = p.id
                  AND pi.deleted_at IS NULL
                ORDER BY
                    pi.is_cover DESC,
                    pi.sort_order ASC,
                    pi.id ASC
                LIMIT 1
            ) AS property_cover_image_id,

            sr.title AS search_title,
            sr.description AS search_description,
            sr.currency AS search_currency,
            sr.min_value AS search_min_value,
            sr.max_value AS search_max_value,
            sr.country AS search_country,
            sr.province AS search_province,
            sr.city AS search_city,
            sr.zone AS search_zone,
            sr.urgency AS search_urgency,
            sr.payment_mode_cash,
            sr.payment_mode_swap,
            sr.cash_difference_max,
            sr.cash_difference_currency,
            sr.open_to_other_zones,
            sr.real_estate_id AS search_real_estate_id,

            source_re.name AS source_real_estate_name,
            target_re.name AS target_real_estate_name

        FROM compatibilities c

        INNER JOIN properties p
            ON p.id = c.property_id
           AND p.deleted_at IS NULL

        INNER JOIN search_requests sr
            ON sr.id = c.search_request_id
           AND sr.deleted_at IS NULL

        LEFT JOIN real_estates source_re
            ON source_re.id = c.source_real_estate_id
           AND source_re.deleted_at IS NULL

        LEFT JOIN real_estates target_re
            ON target_re.id = c.target_real_estate_id
           AND target_re.deleted_at IS NULL

        WHERE c.id = :compatibility_id
          AND c.deleted_at IS NULL
          AND c.compatibility_type = 'property_search_request'
          AND (
              c.source_real_estate_id = :source_real_estate_id
              OR
              c.target_real_estate_id = :target_real_estate_id
          )

        LIMIT 1
    ";

        $st = $pdo->prepare($sql);

        $st->execute([
            'compatibility_id' => $compatibilityId,
            'source_real_estate_id' => $realEstateId,
            'target_real_estate_id' => $realEstateId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception(
                'Compatibilidad no encontrada.',
                404
            );
        }

        return self::formatRecommendation(
            $row,
            $realEstateId
        );
    }

    public static function respond(
        int $userId,
        int $compatibilityId,
        string $response
    ): array {
        $pdo = self::db();

        $user =
            self::getUser(
                $pdo,
                $userId
            );

        $realEstateId =
            (int)($user['real_estate_id'] ?? 0);

        if ($realEstateId <= 0) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria.',
                422
            );
        }

        if ($compatibilityId <= 0) {
            throw new Exception(
                'Compatibilidad inválida.',
                422
            );
        }

        /*
     * pending sirve para reactivar
     * una decisión anterior.
     */
        if (
            !in_array(
                $response,
                [
                    'pending',
                    'interested',
                    'dismissed',
                ],
                true
            )
        ) {
            throw new Exception(
                'Respuesta inválida.',
                422
            );
        }

        $st = $pdo->prepare("
        SELECT *
        FROM compatibilities
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'id' => $compatibilityId,
        ]);

        $compatibility =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$compatibility) {
            throw new Exception(
                'Compatibilidad no encontrada.',
                404
            );
        }

        $isSource =
            (int)$compatibility['source_real_estate_id'] === $realEstateId;

        $isTarget =
            (int)$compatibility['target_real_estate_id'] === $realEstateId;

        if (!$isSource && !$isTarget) {
            throw new Exception(
                'No tenés acceso a esta compatibilidad.',
                403
            );
        }

        /*
     * Si ya existe un chat, el match ya
     * superó la instancia de decisión.
     */
        if (
            $compatibility['status']
            === 'chat_enabled'
        ) {
            throw new Exception(
                'Esta compatibilidad ya tiene una conversación habilitada.',
                422
            );
        }

        /*
     * Si quedó mutual_interest pero el chat
     * falló al crearse, permitimos repetir
     * "interested" para intentar generarlo.
     */
        if (
            $compatibility['status']
            === 'mutual_interest' &&
            $response !== 'interested'
        ) {
            throw new Exception(
                'Esta compatibilidad ya tiene interés mutuo.',
                422
            );
        }

        $responseField =
            $isSource
            ? 'source_response'
            : 'target_response';

        $respondedAtField =
            $isSource
            ? 'source_responded_at'
            : 'target_responded_at';

        $respondedByField =
            $isSource
            ? 'source_responded_by_user_id'
            : 'target_responded_by_user_id';

        /*
     * La transacción de CompatibilityService
     * termina ANTES de intentar crear el chat.
     */
        $pdo->beginTransaction();

        try {
            /*
         * pending = reactivar.
         *
         * En ese caso limpiamos fecha
         * y usuario de respuesta.
         */
            $stUpdate = $pdo->prepare("
            UPDATE compatibilities
            SET
                {$responseField} = :response,

                {$respondedAtField} = CASE
                    WHEN :response_for_date = 'pending'
                    THEN NULL
                    ELSE NOW()
                END,

                {$respondedByField} = CASE
                    WHEN :response_for_user = 'pending'
                    THEN NULL
                    ELSE :responded_by_user_id
                END

            WHERE id = :id
            LIMIT 1
        ");

            $stUpdate->execute([
                'response' =>
                $response,

                'response_for_date' =>
                $response,

                'response_for_user' =>
                $response,

                'responded_by_user_id' =>
                $userId,

                'id' =>
                $compatibilityId,
            ]);

            /*
         * Releemos las dos decisiones
         * una vez guardado el cambio.
         */
            $stCurrent = $pdo->prepare("
            SELECT
                source_response,
                target_response
            FROM compatibilities
            WHERE id = :id
            LIMIT 1
        ");

            $stCurrent->execute([
                'id' =>
                $compatibilityId,
            ]);

            $current =
                $stCurrent->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$current) {
                throw new Exception(
                    'No se pudo recuperar el estado actualizado de la compatibilidad.',
                    500
                );
            }

            $sourceResponse =
                $current['source_response'];

            $targetResponse =
                $current['target_response'];

            /*
         * Estado global.
         *
         * Una sola parte que descarta
         * alcanza para detener el match.
         * No lo eliminamos: pasa a Historial.
         */
            if (
                $sourceResponse === 'dismissed' ||
                $targetResponse === 'dismissed'
            ) {
                $status = 'dismissed';
            } elseif (
                $sourceResponse === 'interested' &&
                $targetResponse === 'interested'
            ) {
                $status = 'mutual_interest';
            } elseif (
                $sourceResponse === 'interested' ||
                $targetResponse === 'interested'
            ) {
                $status =
                    'one_side_interested';
            } else {
                $status = 'detected';
            }

            /*
         * dismissed_at se completa solamente
         * mientras el match está descartado.
         *
         * Si se reactiva, vuelve a NULL.
         */
            $stStatus = $pdo->prepare("
            UPDATE compatibilities
            SET
                status = :status,

                dismissed_at = CASE
                    WHEN :status_for_dismissed = 'dismissed'
                    THEN COALESCE(
                        dismissed_at,
                        NOW()
                    )
                    ELSE NULL
                END

            WHERE id = :id
            LIMIT 1
        ");

            $stStatus->execute([
                'status' =>
                $status,

                'status_for_dismissed' =>
                $status,

                'id' =>
                $compatibilityId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            /*
         * Esto corrige:
         *
         * "There is no active transaction"
         */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        /*
     * IMPORTANTE:
     *
     * ConversationService puede iniciar su
     * propia transacción. Por eso se ejecuta
     * cuando la anterior ya terminó.
     */
        if ($status === 'mutual_interest') {
            ConversationService::createFromCompatibility(
                $compatibilityId
            );
        }

        /*
     * createFromCompatibility() cambia
     * mutual_interest → chat_enabled.
     *
     * Por eso releemos al final.
     */
        return self::getRecommendationDetail(
            $userId,
            $compatibilityId
        );
    }

    public static function saveFeedback(
        int $userId,
        int $compatibilityId,
        array $data
    ): array {
        $pdo = self::db();

        $user = self::getUser($pdo, $userId);

        $realEstateId =
            (int)($user['real_estate_id'] ?? 0);

        if ($realEstateId <= 0) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria.',
                422
            );
        }

        /*
     * Primero comprobamos que realmente participe.
     */
        $stCompatibility = $pdo->prepare("
        SELECT id
        FROM compatibilities
        WHERE id = :id
          AND deleted_at IS NULL
          AND (
              source_real_estate_id = :source_real_estate_id
              OR
              target_real_estate_id = :target_real_estate_id
          )
        LIMIT 1
    ");

        $stCompatibility->execute([
            'id' => $compatibilityId,
            'source_real_estate_id' => $realEstateId,
            'target_real_estate_id' => $realEstateId,
        ]);

        if (!$stCompatibility->fetch()) {
            throw new Exception(
                'Compatibilidad no encontrada.',
                404
            );
        }

        $useful = $data['useful'] ?? null;
        $rating = $data['rating'] ?? null;

        $comment = trim(
            (string)($data['comment'] ?? '')
        );

        if ($useful !== null) {
            $useful = filter_var(
                $useful,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($useful === null) {
                throw new Exception(
                    'Valor useful inválido.',
                    422
                );
            }
        }

        if ($rating !== null && $rating !== '') {
            $rating = (int)$rating;

            if ($rating < 1 || $rating > 5) {
                throw new Exception(
                    'La calificación debe estar entre 1 y 5.',
                    422
                );
            }
        } else {
            $rating = null;
        }

        if (mb_strlen($comment) > 2000) {
            throw new Exception(
                'El comentario es demasiado largo.',
                422
            );
        }

        $st = $pdo->prepare("
        INSERT INTO compatibility_feedback (
            compatibility_id,
            real_estate_id,
            user_id,
            useful,
            rating,
            comment
        )
        VALUES (
            :compatibility_id,
            :real_estate_id,
            :user_id,
            :useful,
            :rating,
            :comment
        )

        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            useful = VALUES(useful),
            rating = VALUES(rating),
            comment = VALUES(comment),
            updated_at = NOW()
    ");

        $st->execute([
            'compatibility_id' =>
            $compatibilityId,

            'real_estate_id' =>
            $realEstateId,

            'user_id' =>
            $userId,

            'useful' =>
            $useful === null
                ? null
                : ($useful ? 1 : 0),

            'rating' =>
            $rating,

            'comment' =>
            $comment !== ''
                ? $comment
                : null,
        ]);

        return [
            'compatibility_id' =>
            $compatibilityId,

            'useful' => $useful,
            'rating' => $rating,
            'comment' =>
            $comment !== ''
                ? $comment
                : null,
        ];
    }
    /**
     * 
     * 
     * Usuario + inmobiliaria.
     */
    private static function getUser(
        PDO $pdo,
        int $userId
    ): array {
        if ($userId <= 0) {
            throw new Exception(
                'Usuario inválido.',
                422
            );
        }

        $st = $pdo->prepare("
            SELECT
                id,
                role,
                real_estate_id,
                is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $userId,
        ]);

        $user = $st->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$user) {
            throw new Exception(
                'Usuario no encontrado.',
                404
            );
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception(
                'El usuario está inactivo.',
                403
            );
        }

        if (
            !in_array(
                (int)$user['role'],
                [
                    self::ROLE_REAL_ESTATE,
                    self::ROLE_AGENT,
                ],
                true
            )
        ) {
            throw new Exception(
                'No tenés permisos para consultar compatibilidades.',
                403
            );
        }

        return $user;
    }

    /**
     * Convierte la fila SQL al contrato que
     * consumirá el frontend.
     */
    private static function formatRecommendation(
        array $row,
        int $realEstateId
    ): array {
        $isSource =
            (int)$row['source_real_estate_id']
            === $realEstateId;

        $reasonsData =
            self::decodeReasons(
                $row['reasons_json'] ?? null
            );

        $myResponse =
            $isSource
            ? $row['source_response']
            : $row['target_response'];

        $counterpartResponse =
            $isSource
            ? $row['target_response']
            : $row['source_response'];

        $myRespondedAt =
            $isSource
            ? ($row['source_responded_at'] ?? null)
            : ($row['target_responded_at'] ?? null);

        $counterpartRespondedAt =
            $isSource
            ? ($row['target_responded_at'] ?? null)
            : ($row['source_responded_at'] ?? null);

        /*
     * No enviamos nombre de la contraparte.
     * La identidad queda fuera de esta etapa.
     */
        $counterpartRealEstateId =
            $isSource
            ? (int)$row['target_real_estate_id']
            : (int)$row['source_real_estate_id'];

        $status =
            (string)$row['status'];

        $conversationId =
            self::nullableInt(
                $row['conversation_id'] ?? null
            );

        return [
            'id' =>
            (int)$row['id'],

            'score' =>
            (float)$row['score'],

            'match_level' =>
            $row['match_level'],

            'status' =>
            $status,

            'conversation_id' =>
            $conversationId,

            'detected_from' =>
            $row['detected_from'],

            'match_reason' =>
            $row['match_reason'],

            'calculated_at' =>
            $row['calculated_at'],

            'created_at' =>
            $row['created_at'],

            'updated_at' =>
            $row['updated_at'],

            'dismissed_at' =>
            $row['dismissed_at'] ?? null,

            'archived_at' =>
            $row['archived_at'] ?? null,

            /*
         * Mi posición dentro del match.
         */
            'my_side' =>
            $isSource
                ? 'search_request'
                : 'property',

            /*
         * Decisiones.
         */
            'my_response' =>
            $myResponse,

            'my_responded_at' =>
            $myRespondedAt,

            'counterpart_response' =>
            $counterpartResponse,

            'counterpart_responded_at' =>
            $counterpartRespondedAt,

            /*
         * Acciones disponibles para frontend.
         */
            'actions' => [
                'can_respond' =>
                !in_array(
                    $status,
                    [
                        'chat_enabled',
                        'archived',
                    ],
                    true
                ),

                'can_reactivate' =>
                $myResponse === 'dismissed' &&
                    $status === 'dismissed',

                'can_open_conversation' =>
                $status === 'chat_enabled' &&
                    $conversationId !== null,
            ],

            /*
         * No exponemos identidad todavía.
         */
            'counterpart' => [
                'real_estate_id' =>
                $counterpartRealEstateId,
            ],

            /*
         * Propiedad.
         */
            'property' => [
                'id' =>
                (int)$row['property_id'],

                'real_estate_id' =>
                (int)$row['property_real_estate_id'],

                'title' =>
                $row['property_title'],

                'description' =>
                $row['property_description'],

                'property_type' =>
                $row['property_type'],

                'price' =>
                self::nullableFloat(
                    $row['property_price']
                ),

                'currency' =>
                $row['property_currency'],

                'location' => [
                    'country' =>
                    $row['property_country'],

                    'province' =>
                    $row['property_province'],

                    'city' =>
                    $row['property_city'],

                    'zone' =>
                    $row['property_zone'],
                ],

                'total_area' =>
                self::nullableFloat(
                    $row['property_total_area']
                ),

                'covered_area' =>
                self::nullableFloat(
                    $row['property_covered_area']
                ),

                'bedrooms' =>
                self::nullableInt(
                    $row['property_bedrooms']
                ),

                'bathrooms' =>
                self::nullableInt(
                    $row['property_bathrooms']
                ),

                'garages' =>
                self::nullableInt(
                    $row['property_garages']
                ),

                'cover_image_id' =>
                self::nullableInt(
                    $row['property_cover_image_id']
                ),

                'cover_image_url' =>
                !empty($row['property_cover_image_id'])
                    ? '/property-images/' .
                    (int)$row['property_cover_image_id'] .
                    '/view'
                    : null,
            ],

            /*
         * Búsqueda.
         */
            'search_request' => [
                'id' =>
                (int)$row['search_request_id'],

                'real_estate_id' =>
                (int)$row['search_real_estate_id'],

                'title' =>
                $row['search_title'],

                'description' =>
                $row['search_description'],

                'budget' => [
                    'currency' =>
                    $row['search_currency'],

                    'min' =>
                    self::nullableFloat(
                        $row['search_min_value']
                    ),

                    'max' =>
                    self::nullableFloat(
                        $row['search_max_value']
                    ),
                ],

                'location' => [
                    'country' =>
                    $row['search_country'],

                    'province' =>
                    $row['search_province'],

                    'city' =>
                    $row['search_city'],

                    'zone' =>
                    $row['search_zone'],
                ],

                'urgency' =>
                $row['search_urgency'],

                'payment_modes' => [
                    'cash' =>
                    (bool)$row['payment_mode_cash'],

                    'swap' =>
                    (bool)$row['payment_mode_swap'],
                ],

                'cash_difference' => [
                    'max' =>
                    self::nullableFloat(
                        $row['cash_difference_max']
                    ),

                    'currency' =>
                    $row['cash_difference_currency'],
                ],

                'open_to_other_zones' =>
                (bool)$row['open_to_other_zones'],
            ],

            /*
         * Explicación.
         */
            'reasons' =>
            $reasonsData['reasons'],

            'penalties' =>
            $reasonsData['penalties'],

            'engine_version' =>
            $reasonsData['engine_version'],

            /*
         * Permuta.
         */
            'swap' =>
            self::extractSwapSummary(
                $reasonsData
            ),
        ];
    }
    /**
     * Lee reasons_json de forma segura.
     */
    private static function decodeReasons(
        ?string $json
    ): array {
        $empty = [
            'reasons' => [],
            'penalties' => [],
            'engine_version' => null,
        ];

        if (!$json) {
            return $empty;
        }

        $decoded = json_decode(
            $json,
            true
        );

        if (!is_array($decoded)) {
            return $empty;
        }

        /*
         * Compatibilidad con registros viejos
         * que pudieron guardar un nivel extra.
         */
        if (
            isset($decoded['reasons']['reasons']) &&
            is_array(
                $decoded['reasons']['reasons']
            )
        ) {
            $decoded = $decoded['reasons'];
        }

        return [
            'reasons' =>
            isset($decoded['reasons']) &&
                is_array($decoded['reasons'])
                ? $decoded['reasons']
                : [],

            'penalties' =>
            isset($decoded['penalties']) &&
                is_array($decoded['penalties'])
                ? $decoded['penalties']
                : [],

            'engine_version' =>
            isset($decoded['engine_version'])
                ? (string)$decoded['engine_version']
                : null,
        ];
    }

    /**
     * Extrae información económica relevante
     * de los motivos ya calculados.
     */
    private static function extractSwapSummary(
        array $reasonsData
    ): array {
        $summary = [
            'evaluated' => false,
            'accepted' => null,

            'offered_value' => null,

            'required_difference' => null,

            'available_difference' => null,

            'accepted_difference_min' => null,
            'accepted_difference_max' => null,

            'direction' => null,
            'currency' => null,
        ];

        foreach (
            $reasonsData['reasons'] ?? []
            as $reason
        ) {
            if (!is_array($reason)) {
                continue;
            }

            $code = $reason['code'] ?? null;

            switch ($code) {
                case 'swap_mode_accepted':
                    $summary['evaluated'] = true;
                    $summary['accepted'] = true;
                    break;

                case 'swap_mode_rejected':
                    $summary['evaluated'] = true;
                    $summary['accepted'] = false;
                    break;

                case 'exchange_offer_value_match':
                    $summary['evaluated'] = true;

                    $summary['offered_value'] =
                        self::nullableFloat(
                            $reason['offered_value'] ?? null
                        );

                    $summary['currency'] =
                        $reason['currency']
                        ?? $summary['currency'];
                    break;

                case 'cash_difference_capacity':
                    $summary['evaluated'] = true;

                    $summary['required_difference'] =
                        self::nullableFloat(
                            $reason['required_difference']
                                ?? null
                        );

                    $summary['available_difference'] =
                        self::nullableFloat(
                            $reason['available_difference']
                                ?? null
                        );

                    $summary['currency'] =
                        $reason['currency']
                        ?? $summary['currency'];
                    break;

                case 'owner_difference_conditions':
                    $summary['evaluated'] = true;

                    $summary['accepted_difference_min'] =
                        self::nullableFloat(
                            $reason['accepted_min']
                                ?? null
                        );

                    $summary['accepted_difference_max'] =
                        self::nullableFloat(
                            $reason['accepted_max']
                                ?? null
                        );

                    $summary['direction'] =
                        $reason['direction'] ?? null;

                    $summary['currency'] =
                        $reason['currency']
                        ?? $summary['currency'];
                    break;
            }
        }

        return $summary;
    }

    private static function nullableFloat(
        mixed $value
    ): ?float {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {
            return null;
        }

        return (float)$value;
    }

    private static function nullableInt(
        mixed $value
    ): ?int {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {
            return null;
        }

        return (int)$value;
    }

    private static function executeStatement(
        \PDOStatement $statement,
        array $params
    ): void {
        foreach ($params as $key => $value) {
            $parameter = ':' . ltrim(
                (string)$key,
                ':'
            );

            if (is_int($value)) {
                $statement->bindValue(
                    $parameter,
                    $value,
                    PDO::PARAM_INT
                );
            } else {
                $statement->bindValue(
                    $parameter,
                    $value
                );
            }
        }

        $statement->execute();
    }
}
