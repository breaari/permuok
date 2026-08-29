<?php

namespace App\Services;

use Exception;
use PDO;

class ConversationService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function startConversation(int $userId, array $input): array
    {
        $type = trim((string)($input['opportunity_type'] ?? ''));
        $opportunityId = (int)($input['opportunity_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));

        self::validateOpportunityType($type);

        if ($opportunityId <= 0) {
            throw new Exception('La oportunidad es obligatoria.', 422);
        }

        if ($message === '') {
            throw new Exception('El mensaje inicial es obligatorio.', 422);
        }

        $ownerUserId = self::findOpportunityOwner($type, $opportunityId);

        if (!$ownerUserId) {
            throw new Exception('No se encontró el propietario de la oportunidad.', 404);
        }

        if ($ownerUserId === $userId) {
            throw new Exception('No podés iniciar una conversación sobre tu propia publicación.', 422);
        }

        $pdo = self::db();
        $pdo->beginTransaction();
        $isNewConversation = false;
        try {
            $conversation = self::findExistingConversation(
                $type,
                $opportunityId,
                $userId,
                $ownerUserId
            );

            if (!$conversation) {
                $isNewConversation = true;
                $subject = self::buildConversationSubject($type, $opportunityId);

                $stmt = $pdo->prepare("
                    INSERT INTO conversations (
                        opportunity_type,
                        opportunity_id,
                        created_by_user_id,
                        owner_user_id,
                        subject,
                        status,
                        contact_shared
                    )
                    VALUES (
                        :type,
                        :opportunity_id,
                        :created_by_user_id,
                        :owner_user_id,
                        :subject,
                        'open',
                        0
                    )
                ");

                $stmt->execute([
                    ':type' => $type,
                    ':opportunity_id' => $opportunityId,
                    ':created_by_user_id' => $userId,
                    ':owner_user_id' => $ownerUserId,
                    ':subject' => $subject,
                ]);

                $conversationId = (int)$pdo->lastInsertId();

                self::addParticipant($conversationId, $userId, 'initiator');
                self::addParticipant($conversationId, $ownerUserId, 'owner');

                NotificationService::notifyNewConversation(
                    $ownerUserId,
                    $conversationId,
                    [
                        'subject' => $subject,
                    ],
                    $pdo
                );
            } else {
                $conversationId = (int)$conversation['id'];
            }

            $messageResult = self::createMessage($conversationId, $userId, $message);

            self::touchConversation($conversationId, $messageResult['message']['id']);

            if (
                !$isNewConversation &&
                $ownerUserId !== $userId
            ) {
                NotificationService::notifyNewMessage(
                    $ownerUserId,
                    $conversationId,
                    [
                        'subject' => $subject ?? null,
                    ],
                    $pdo
                );
            }

            $pdo->commit();

            return self::getConversationDetail($userId, $conversationId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    public static function createFromCompatibility(
        int $compatibilityId,
        ?PDO $pdo = null
    ): array {
        $pdo ??= self::db();

        $ownsTransaction = !$pdo->inTransaction();

        if ($compatibilityId <= 0) {
            throw new Exception(
                'Compatibilidad inválida.',
                422
            );
        }

        /*
     * Primero verificamos si ya existe.
     * Esto hace al método idempotente.
     */
        $stExisting = $pdo->prepare("
        SELECT *
        FROM conversations
        WHERE compatibility_id = :compatibility_id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stExisting->execute([
            ':compatibility_id' => $compatibilityId,
        ]);

        $existing =
            $stExisting->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            /*
         * Nos aseguramos también de que
         * la compatibilidad figure habilitada.
         */
            $stCompatibility = $pdo->prepare("
            UPDATE compatibilities
            SET
                status = 'chat_enabled',
                chat_enabled_at = COALESCE(
                    chat_enabled_at,
                    NOW()
                )
            WHERE id = :id
            LIMIT 1
        ");

            $stCompatibility->execute([
                ':id' => $compatibilityId,
            ]);

            return [
                'conversation' => $existing,
                'already_exists' => true,
            ];
        }

        /*
     * Buscamos toda la información necesaria
     * para construir la conversación.
     */
        $st = $pdo->prepare("
        SELECT
            c.id,
            c.property_id,
            c.search_request_id,

            c.source_response,
            c.target_response,

            c.source_real_estate_id,
            c.target_real_estate_id,

            c.source_responded_by_user_id,
            c.target_responded_by_user_id,

            p.title AS property_title,
            sr.title AS search_title

        FROM compatibilities c

        INNER JOIN properties p
            ON p.id = c.property_id
           AND p.deleted_at IS NULL

        INNER JOIN search_requests sr
            ON sr.id = c.search_request_id
           AND sr.deleted_at IS NULL

        WHERE c.id = :id
          AND c.deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            ':id' => $compatibilityId,
        ]);

        $compatibility =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$compatibility) {
            throw new Exception(
                'Compatibilidad no encontrada.',
                404
            );
        }

        /*
     * Solamente puede abrirse el chat
     * cuando ambos expresaron interés.
     */
        if (
            $compatibility['source_response']
            !== 'interested' ||
            $compatibility['target_response']
            !== 'interested'
        ) {
            throw new Exception(
                'La conversación solo puede habilitarse cuando ambas partes muestran interés.',
                422
            );
        }

        $sourceUserId =
            (int)(
                $compatibility['source_responded_by_user_id'] ?? 0
            );

        $targetUserId =
            (int)(
                $compatibility['target_responded_by_user_id'] ?? 0
            );

        if (
            $sourceUserId <= 0 ||
            $targetUserId <= 0
        ) {
            throw new Exception(
                'No se pudieron determinar los usuarios que mostraron interés.',
                422
            );
        }

        if ($sourceUserId === $targetUserId) {
            throw new Exception(
                'No se puede crear una conversación con el mismo usuario.',
                422
            );
        }

        /*
     * Para property_search_request usamos
     * la propiedad como oportunidad principal.
     *
     * El lado de la búsqueda funciona como initiator.
     * El lado de la propiedad funciona como owner.
     */
        $propertyId =
            (int)$compatibility['property_id'];

        $propertyTitle =
            trim(
                (string)(
                    $compatibility['property_title'] ?? ''
                )
            );

        $searchTitle =
            trim(
                (string)(
                    $compatibility['search_title'] ?? ''
                )
            );

        $subject =
            'Match: ' .
            (
                $propertyTitle !== ''
                ? $propertyTitle
                : 'Propiedad'
            );

        if ($searchTitle !== '') {
            $subject .=
                ' · ' .
                $searchTitle;
        }

        /*
     * Evitamos pasarnos del varchar(255).
     */
        $subject =
            mb_substr(
                $subject,
                0,
                255
            );

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            /*
         * El UNIQUE de compatibility_id
         * nos protege además contra creación doble.
         */
            $stInsert = $pdo->prepare("
            INSERT INTO conversations (
                compatibility_id,
                opportunity_type,
                opportunity_id,
                created_by_user_id,
                owner_user_id,
                subject,
                contact_shared,
                status
            )
            VALUES (
                :compatibility_id,
                'property',
                :opportunity_id,
                :created_by_user_id,
                :owner_user_id,
                :subject,
                0,
                'open'
            )
        ");

            $stInsert->execute([
                ':compatibility_id' =>
                $compatibilityId,

                ':opportunity_id' =>
                $propertyId,

                ':created_by_user_id' =>
                $sourceUserId,

                ':owner_user_id' =>
                $targetUserId,

                ':subject' =>
                $subject,
            ]);

            $conversationId =
                (int)$pdo->lastInsertId();

            /*
         * Reutilizamos los métodos internos
         * que ya usa startConversation().
         */
            self::addParticipant(
                $conversationId,
                $sourceUserId,
                'initiator'
            );

            self::addParticipant(
                $conversationId,
                $targetUserId,
                'owner'
            );

            /*
         * Mensaje automático inicial.
         */
            $systemMessage =
                self::createSystemMessage(
                    $conversationId,
                    'Ambas partes mostraron interés en esta compatibilidad. La conversación fue habilitada automáticamente.'
                );

            self::touchConversation(
                $conversationId,
                (int)$systemMessage['id']
            );

            /*
         * Marcamos el match como chat habilitado.
         */
            $stCompatibility = $pdo->prepare("
            UPDATE compatibilities
            SET
                status = 'chat_enabled',
                chat_enabled_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

            $stCompatibility->execute([
                ':id' => $compatibilityId,
            ]);

            /*
 * Las notificaciones internas y los email_jobs
 * forman parte de la misma transacción que
 * habilita el chat.
 */
            NotificationService::notifyCompatibilityMutualInterest(
                $sourceUserId,
                $compatibilityId,
                $conversationId,
                $pdo
            );

            NotificationService::notifyCompatibilityMutualInterest(
                $targetUserId,
                $compatibilityId,
                $conversationId,
                $pdo
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'conversation' =>
                self::getConversationById(
                    $conversationId
                ),

                'already_exists' => false,
            ];
        } catch (\Throwable $e) {
            if (
                $ownsTransaction &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            /*
         * Si dos requests llegaron casi
         * simultáneamente, el UNIQUE puede
         * haber permitido que el otro cree
         * primero la conversación.
         */
            if (
                $e instanceof \PDOException &&
                (string)$e->getCode() === '23000'
            ) {
                $stExisting->execute([
                    ':compatibility_id' =>
                    $compatibilityId,
                ]);

                $existing =
                    $stExisting->fetch(
                        PDO::FETCH_ASSOC
                    );

                if ($existing) {
                    return [
                        'conversation' =>
                        $existing,

                        'already_exists' =>
                        true,
                    ];
                }
            }

            throw $e;
        }
    }
    public static function listConversations(
        int $userId,
        array $query = []
    ): array {
        $pdo = self::db();

        $limit = max(
            1,
            min(
                50,
                (int)($query['limit'] ?? 20)
            )
        );

        $page = max(
            1,
            (int)($query['page'] ?? 1)
        );

        $offset = ($page - 1) * $limit;

        $showArchived =
            isset($query['archived']) &&
            in_array(
                (string)$query['archived'],
                ['1', 'true'],
                true
            );

        $archivedCondition =
            $showArchived
            ? 'cp.archived_at IS NOT NULL'
            : 'cp.archived_at IS NULL';

        $stmt = $pdo->prepare("
        SELECT
            c.*,

            comp.score AS compatibility_score,
            comp.match_level AS compatibility_match_level,
            comp.property_id AS compatibility_property_id,
            comp.search_request_id AS compatibility_search_request_id,
p.title AS compatibility_property_title,
sr.title AS compatibility_search_title,
            CASE
                WHEN cp.role = 'owner'
                    THEN 'received'

                WHEN cp.role = 'initiator'
                    THEN 'sent'

                ELSE 'participant'
            END AS direction,

            cp.role AS participant_role,
            cp.last_read_message_id,
            cp.last_read_at,
            cp.archived_at,

            lm.body
                AS last_message_body,

            lm.sanitized_body
                AS last_message_sanitized_body,

            lm.sender_user_id
                AS last_message_sender_user_id,

            lm.created_at
                AS last_message_created_at,

            (
                SELECT COUNT(*)
                FROM messages m

                WHERE
                    m.conversation_id = c.id

                    AND m.deleted_at IS NULL

                    AND m.sender_user_id
                        <> :user_id_unread

                    AND (
                        cp.last_read_message_id IS NULL

                        OR m.id
                            > cp.last_read_message_id
                    )
            ) AS unread_count

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        LEFT JOIN compatibilities comp
            ON comp.id = c.compatibility_id
           AND comp.deleted_at IS NULL
LEFT JOIN properties p
    ON p.id = comp.property_id
   AND p.deleted_at IS NULL

LEFT JOIN search_requests sr
    ON sr.id = comp.search_request_id
   AND sr.deleted_at IS NULL
        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE
            cp.user_id = :user_id

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND {$archivedCondition}

        ORDER BY
            COALESCE(
                c.last_message_at,
                c.created_at
            ) DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ");

        $stmt->execute([
            ':user_id' => $userId,
            ':user_id_unread' => $userId,
        ]);

        $items =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'archived' => $showArchived,
        ];
    }

    public static function getInbox(
        int $userId,
        array $query = []
    ): array {
        $pdo = self::db();

        $allowedTabs = [
            'own',
            'external',
            'matches',
        ];

        $allowedStatuses = [
            'all',
            'open',
            'negotiating',
            'visit_scheduled',
            'closed',
            'discarded',
        ];

        $tab = trim(
            (string)($query['tab'] ?? 'own')
        );

        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'own';
        }

        $status = trim(
            (string)($query['status'] ?? 'all')
        );

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $search = trim(
            (string)($query['search'] ?? '')
        );

        $showArchived =
            isset($query['archived']) &&
            in_array(
                (string)$query['archived'],
                ['1', 'true'],
                true
            );

        $page = max(
            1,
            (int)($query['page'] ?? 1)
        );

        $limit = max(
            1,
            min(
                50,
                (int)($query['limit'] ?? 20)
            )
        );

        $offset =
            ($page - 1) * $limit;

        /*
     * MATCHES:
     * cada conversación es una fila.
     */
        if ($tab === 'matches') {
            $result = self::getInboxMatches(
                $pdo,
                $userId,
                $status,
                $search,
                $showArchived,
                $page,
                $limit,
                $offset
            );
        } else {
            $result = self::getInboxGrouped(
                $pdo,
                $userId,
                $tab,
                $status,
                $search,
                $showArchived,
                $page,
                $limit,
                $offset
            );
        }

        $result['summary'] =
            self::getInboxSummary(
                $pdo,
                $userId,
                $showArchived,
                $tab
            );

        return $result;
    }


    public static function getInboxGroup(
        int $userId,
        array $query = []
    ): array {
        $pdo = self::db();

        $type = trim(
            (string)($query['opportunity_type'] ?? '')
        );

        $opportunityId =
            (int)($query['opportunity_id'] ?? 0);

        $tab = trim(
            (string)($query['tab'] ?? '')
        );

        $showArchived =
            isset($query['archived']) &&
            in_array(
                (string)$query['archived'],
                ['1', 'true'],
                true
            );

        $page = max(
            1,
            (int)($query['page'] ?? 1)
        );

        $limit = max(
            1,
            min(
                50,
                (int)($query['limit'] ?? 20)
            )
        );

        if (
            !in_array(
                $type,
                ['property', 'search_request', 'development'],
                true
            )
        ) {
            throw new Exception(
                'Tipo de publicación inválido.',
                422
            );
        }

        if ($opportunityId <= 0) {
            throw new Exception(
                'Publicación inválida.',
                422
            );
        }

        if (!in_array($tab, ['own', 'external'], true)) {
            throw new Exception(
                'Pestaña inválida.',
                422
            );
        }

        $offset =
            ($page - 1) * $limit;

        $role =
            $tab === 'own'
            ? 'owner'
            : 'initiator';

        $archiveCondition =
            $showArchived
            ? 'cp.archived_at IS NOT NULL'
            : 'cp.archived_at IS NULL';

        $countStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        WHERE
            cp.user_id = :user_id

            AND cp.role = :role

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND c.compatibility_id IS NULL

            AND c.opportunity_type = :opportunity_type

            AND c.opportunity_id = :opportunity_id

            AND {$archiveCondition}
    ");

        $countStmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
            ':opportunity_type' => $type,
            ':opportunity_id' => $opportunityId,
        ]);

        $total =
            (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
        SELECT
            c.*,

            CASE
                WHEN cp.role = 'owner'
                    THEN 'received'

                WHEN cp.role = 'initiator'
                    THEN 'sent'

                ELSE 'participant'
            END AS direction,

            cp.role AS participant_role,

            cp.last_read_message_id,
            cp.last_read_at,
            cp.archived_at,

            lm.body
                AS last_message_body,

            lm.sanitized_body
                AS last_message_sanitized_body,

            lm.sender_user_id
                AS last_message_sender_user_id,

            lm.created_at
                AS last_message_created_at,

            (
                SELECT COUNT(*)

                FROM messages um

                WHERE
                    um.conversation_id = c.id

                    AND um.deleted_at IS NULL

                    AND um.sender_user_id
                        <> :user_id_unread

                    AND (
                        cp.last_read_message_id IS NULL

                        OR um.id
                            > cp.last_read_message_id
                    )
            ) AS unread_count

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE
            cp.user_id = :user_id

            AND cp.role = :role

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND c.compatibility_id IS NULL

            AND c.opportunity_type = :opportunity_type

            AND c.opportunity_id = :opportunity_id

            AND {$archiveCondition}

        ORDER BY
            COALESCE(
                c.last_message_at,
                c.created_at
            ) DESC,
            c.id DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ");

        $stmt->execute([
            ':user_id' => $userId,
            ':user_id_unread' => $userId,
            ':role' => $role,
            ':opportunity_type' => $type,
            ':opportunity_id' => $opportunityId,
        ]);

        $items =
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        $totalPages =
            $total > 0
            ? (int)ceil($total / $limit)
            : 0;

        return [
            'type' => 'group_conversations',

            'group' => [
                'key' =>
                $type . ':' . $opportunityId,

                'opportunity_type' => $type,

                'opportunity_id' =>
                $opportunityId,

                'tab' => $tab,
            ],

            'items' => $items,

            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    private static function getInboxMatches(
        PDO $pdo,
        int $userId,
        string $status,
        string $search,
        bool $showArchived,
        int $page,
        int $limit,
        int $offset
    ): array {
        $where = [
            'cp.user_id = :user_id',
            'cp.deleted_at IS NULL',
            'c.deleted_at IS NULL',
            'c.compatibility_id IS NOT NULL',
        ];

        $params = [
            ':user_id' => $userId,
        ];

        $where[] = $showArchived
            ? 'cp.archived_at IS NOT NULL'
            : 'cp.archived_at IS NULL';

        if ($status !== 'all') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = "(
        c.subject LIKE :search_subject
        OR lm.body LIKE :search_body
        OR lm.sanitized_body LIKE :search_sanitized
        OR p.title LIKE :search_property_title
        OR sr.title LIKE :search_request_title
        OR CAST(comp.property_id AS CHAR) LIKE :search_property_id
        OR CAST(comp.search_request_id AS CHAR) LIKE :search_request_id
    )";

            $searchValue = '%' . $search . '%';

            $params[':search_subject'] = $searchValue;
            $params[':search_body'] = $searchValue;
            $params[':search_sanitized'] = $searchValue;
            $params[':search_property_title'] = $searchValue;
            $params[':search_request_title'] = $searchValue;
            $params[':search_property_id'] = $searchValue;
            $params[':search_request_id'] = $searchValue;
        }

        $whereSql = implode(
            "\n AND ",
            $where
        );

        /*
     * Total real para paginación.
     */
        $countStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        LEFT JOIN compatibilities comp
            ON comp.id = c.compatibility_id
           AND comp.deleted_at IS NULL

        LEFT JOIN properties p
            ON p.id = comp.property_id
           AND p.deleted_at IS NULL

        LEFT JOIN search_requests sr
            ON sr.id = comp.search_request_id
           AND sr.deleted_at IS NULL

        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE {$whereSql}
    ");

        $countStmt->execute($params);

        $total = (int)$countStmt->fetchColumn();

        /*
     * Página actual.
     */
        $listParams = $params;

        /*
     * Usamos otro nombre para user_id en
     * la subconsulta de no leídos.
     */
        $listParams[':user_id_unread'] = $userId;

        $stmt = $pdo->prepare("
        SELECT
            c.*,

            comp.score
                AS compatibility_score,

            comp.match_level
                AS compatibility_match_level,

            comp.property_id
                AS compatibility_property_id,

            comp.search_request_id
                AS compatibility_search_request_id,

            p.title
                AS compatibility_property_title,

            sr.title
                AS compatibility_search_title,

            CASE
                WHEN cp.role = 'owner'
                    THEN 'received'

                WHEN cp.role = 'initiator'
                    THEN 'sent'

                ELSE 'participant'
            END AS direction,

            cp.role
                AS participant_role,

            cp.last_read_message_id,
            cp.last_read_at,
            cp.archived_at,

            lm.body
                AS last_message_body,

            lm.sanitized_body
                AS last_message_sanitized_body,

            lm.sender_user_id
                AS last_message_sender_user_id,

            lm.created_at
                AS last_message_created_at,

            (
                SELECT COUNT(*)

                FROM messages um

                WHERE
                    um.conversation_id = c.id

                    AND um.deleted_at IS NULL

                    AND um.sender_user_id
                        <> :user_id_unread

                    AND (
                        cp.last_read_message_id IS NULL

                        OR um.id
                            > cp.last_read_message_id
                    )
            ) AS unread_count

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        LEFT JOIN compatibilities comp
            ON comp.id = c.compatibility_id
           AND comp.deleted_at IS NULL

        LEFT JOIN properties p
            ON p.id = comp.property_id
           AND p.deleted_at IS NULL

        LEFT JOIN search_requests sr
            ON sr.id = comp.search_request_id
           AND sr.deleted_at IS NULL

        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE {$whereSql}

        ORDER BY
            COALESCE(
                c.last_message_at,
                c.created_at
            ) DESC,
            c.id DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ");

        $stmt->execute($listParams);

        $items =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $totalPages =
            $total > 0
            ? (int)ceil($total / $limit)
            : 0;

        return [
            'type' => 'conversations',
            'tab' => 'matches',
            'items' => $items,

            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    private static function getInboxGrouped(
        PDO $pdo,
        int $userId,
        string $tab,
        string $status,
        string $search,
        bool $showArchived,
        int $page,
        int $limit,
        int $offset
    ): array {
        $where = [
            'cp.user_id = :user_id',
            'cp.deleted_at IS NULL',
            'c.deleted_at IS NULL',
            'c.compatibility_id IS NULL',
            'c.opportunity_type IS NOT NULL',
            'c.opportunity_id IS NOT NULL',
        ];

        $params = [
            ':user_id' => $userId,
        ];

        $where[] = $showArchived
            ? 'cp.archived_at IS NOT NULL'
            : 'cp.archived_at IS NULL';

        /*
     * Las conversaciones normales ya guardan
     * correctamente owner / initiator en
     * conversation_participants.
     */
        if ($tab === 'own') {
            $where[] = "cp.role = 'owner'";
        } else {
            $where[] = "cp.role = 'initiator'";
        }

        if ($status !== 'all') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = "(
        c.subject LIKE :search_subject
        OR lm.body LIKE :search_body
        OR lm.sanitized_body LIKE :search_sanitized
    )";

            $searchValue = '%' . $search . '%';

            $params[':search_subject'] = $searchValue;
            $params[':search_body'] = $searchValue;
            $params[':search_sanitized'] = $searchValue;
        }

        $whereSql = implode(
            "\n AND ",
            $where
        );

        /*
     * Cantidad REAL de publicaciones/grupos,
     * no cantidad de conversaciones.
     */
        $countStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM (
            SELECT
                c.opportunity_type,
                c.opportunity_id

            FROM conversation_participants cp

            INNER JOIN conversations c
                ON c.id = cp.conversation_id

            LEFT JOIN messages lm
                ON lm.id = c.last_message_id

            WHERE {$whereSql}

            GROUP BY
                c.opportunity_type,
                c.opportunity_id
        ) grouped_conversations
    ");

        $countStmt->execute($params);

        $total =
            (int)$countStmt->fetchColumn();

        /*
     * Página de publicaciones.
     *
     * conversation_count cuenta las
     * conversaciones que cumplen el filtro.
     *
     * unread_count se calcula para todas las
     * conversaciones incluidas en ese grupo.
     */
        $listParams = $params;
        $listParams[':user_id_unread'] = $userId;

        $stmt = $pdo->prepare("
        SELECT
            c.opportunity_type,
            c.opportunity_id,

            MAX(c.subject)
                AS subject,

            COUNT(DISTINCT c.id)
                AS conversation_count,

            MAX(
                COALESCE(
                    c.last_message_at,
                    c.created_at
                )
            ) AS last_activity_at,
SUBSTRING_INDEX(
    GROUP_CONCAT(
        c.id
        ORDER BY
            COALESCE(
                c.last_message_at,
                c.created_at
            ) DESC,
            c.id DESC
    ),
    ',',
    1
) AS last_conversation_id,
            SUM(
                (
                    SELECT COUNT(*)

                    FROM messages um

                    WHERE
                        um.conversation_id = c.id

                        AND um.deleted_at IS NULL

                        AND um.sender_user_id
                            <> :user_id_unread

                        AND (
                            cp.last_read_message_id
                                IS NULL

                            OR um.id
                                > cp.last_read_message_id
                        )
                )
            ) AS unread_count

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE {$whereSql}

        GROUP BY
            c.opportunity_type,
            c.opportunity_id

        ORDER BY
            last_activity_at DESC,
            c.opportunity_id DESC

        LIMIT {$limit}
        OFFSET {$offset}
    ");

        $stmt->execute($listParams);

        $items =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
        $lastConversationIds = [];

        foreach ($items as $item) {
            $lastConversationId =
                (int)($item['last_conversation_id'] ?? 0);

            if ($lastConversationId > 0) {
                $lastConversationIds[] =
                    $lastConversationId;
            }
        }

        $lastConversations = [];

        if ($lastConversationIds) {
            $lastConversationIds =
                array_values(
                    array_unique($lastConversationIds)
                );

            $placeholders = [];

            $latestParams = [
                ':user_id' => $userId,
                ':user_id_unread' => $userId,
            ];

            foreach (
                $lastConversationIds
                as $index => $conversationId
            ) {
                $placeholder =
                    ':conversation_id_' . $index;

                $placeholders[] =
                    $placeholder;

                $latestParams[$placeholder] =
                    $conversationId;
            }

            $inSql =
                implode(', ', $placeholders);

            $latestStmt = $pdo->prepare("
        SELECT
            c.*,

            CASE
                WHEN cp.role = 'owner'
                    THEN 'received'

                WHEN cp.role = 'initiator'
                    THEN 'sent'

                ELSE 'participant'
            END AS direction,

            cp.role
                AS participant_role,

            cp.last_read_message_id,
            cp.last_read_at,
            cp.archived_at,

            lm.body
                AS last_message_body,

            lm.sanitized_body
                AS last_message_sanitized_body,

            lm.sender_user_id
                AS last_message_sender_user_id,

            lm.created_at
                AS last_message_created_at,

            (
                SELECT COUNT(*)

                FROM messages um

                WHERE
                    um.conversation_id = c.id

                    AND um.deleted_at IS NULL

                    AND um.sender_user_id
                        <> :user_id_unread

                    AND (
                        cp.last_read_message_id
                            IS NULL

                        OR um.id
                            > cp.last_read_message_id
                    )
            ) AS unread_count

        FROM conversations c

        INNER JOIN conversation_participants cp
            ON cp.conversation_id = c.id

           AND cp.user_id = :user_id

           AND cp.deleted_at IS NULL

        LEFT JOIN messages lm
            ON lm.id = c.last_message_id

        WHERE
            c.id IN ({$inSql})

            AND c.deleted_at IS NULL
    ");

            $latestStmt->execute(
                $latestParams
            );

            $latestRows =
                $latestStmt->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

            foreach ($latestRows as $row) {
                $lastConversations[(int)$row['id']] = $row;
            }
        }
        /*
     * Normalizamos tipos porque PDO puede
     * devolver COUNT/SUM como string.
     */
        foreach ($items as &$item) {
            $item['opportunity_id'] =
                (int)$item['opportunity_id'];

            $item['conversation_count'] =
                (int)$item['conversation_count'];

            $item['unread_count'] =
                (int)$item['unread_count'];

            $item['key'] =
                $item['opportunity_type']
                . ':'
                . $item['opportunity_id'];

            $lastConversationId =
                (int)($item['last_conversation_id'] ?? 0);

            $item['last_conversation'] =
                $lastConversations[$lastConversationId] ?? null;

            unset(
                $item['last_conversation_id']
            );
        }

        unset($item);

        $totalPages =
            $total > 0
            ? (int)ceil($total / $limit)
            : 0;

        return [
            'type' => 'groups',
            'tab' => $tab,
            'items' => $items,

            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    private static function getInboxSummary(
        PDO $pdo,
        int $userId,
        bool $showArchived,
        string $activeTab
    ): array {
        $archiveCondition = $showArchived
            ? 'cp.archived_at IS NOT NULL'
            : 'cp.archived_at IS NULL';

        /*
     * Totales por pestaña.
     */
        $tabsStmt = $pdo->prepare("
        SELECT
            SUM(
                CASE
                    WHEN c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'owner'
THEN 1
                    ELSE 0
                END
            ) AS own_conversations,

            COUNT(
    DISTINCT CASE
        WHEN c.compatibility_id IS NULL
         AND c.opportunity_type IS NOT NULL
         AND c.opportunity_id IS NOT NULL
         AND cp.role = 'owner'
        THEN CONCAT(
            c.opportunity_type,
            ':',
            c.opportunity_id
        )
        ELSE NULL
    END
) AS own_groups,

            SUM(
                CASE
                    WHEN c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'initiator'
                    THEN 1
                    ELSE 0
                END
            ) AS external_conversations,

           COUNT(
    DISTINCT CASE
        WHEN c.compatibility_id IS NULL
         AND c.opportunity_type IS NOT NULL
         AND c.opportunity_id IS NOT NULL
         AND cp.role = 'initiator'
        THEN CONCAT(
            c.opportunity_type,
            ':',
            c.opportunity_id
        )
        ELSE NULL
    END
) AS external_groups,

            SUM(
                CASE
                    WHEN c.compatibility_id IS NOT NULL
                    THEN 1
                    ELSE 0
                END
            ) AS match_conversations

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        WHERE
            cp.user_id = :user_id

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND {$archiveCondition}
    ");

        $tabsStmt->execute([
            ':user_id' => $userId,
        ]);

        $tabRow =
            $tabsStmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        /*
     * No leídos por pestaña.
     *
     * Los calculamos sobre mensajes reales,
     * no sobre las páginas cargadas.
     */
        $unreadStmt = $pdo->prepare("
        SELECT
            SUM(
                CASE
                    WHEN c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'owner'
                    THEN (
                        SELECT COUNT(*)

                        FROM messages m

                        WHERE
                            m.conversation_id = c.id

                            AND m.deleted_at IS NULL

                            AND m.sender_user_id
                                <> :user_id_unread_own

                            AND (
                                cp.last_read_message_id IS NULL

                                OR m.id
                                    > cp.last_read_message_id
                            )
                    )
                    ELSE 0
                END
            ) AS own_unread,

            SUM(
                CASE
                    WHEN c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'initiator'
                    THEN (
                        SELECT COUNT(*)

                        FROM messages m

                        WHERE
                            m.conversation_id = c.id

                            AND m.deleted_at IS NULL

                            AND m.sender_user_id
                                <> :user_id_unread_external

                            AND (
                                cp.last_read_message_id IS NULL

                                OR m.id
                                    > cp.last_read_message_id
                            )
                    )
                    ELSE 0
                END
            ) AS external_unread,

            SUM(
                CASE
                    WHEN c.compatibility_id IS NOT NULL
                    THEN (
                        SELECT COUNT(*)

                        FROM messages m

                        WHERE
                            m.conversation_id = c.id

                            AND m.deleted_at IS NULL

                            AND m.sender_user_id
                                <> :user_id_unread_matches

                            AND (
                                cp.last_read_message_id IS NULL

                                OR m.id
                                    > cp.last_read_message_id
                            )
                    )
                    ELSE 0
                END
            ) AS match_unread

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        WHERE
            cp.user_id = :user_id

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND {$archiveCondition}
    ");

        $unreadStmt->execute([
            ':user_id' => $userId,
            ':user_id_unread_own' => $userId,
            ':user_id_unread_external' => $userId,
            ':user_id_unread_matches' => $userId,
        ]);

        $unreadRow =
            $unreadStmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        /*
     * Contadores de estado para la pestaña activa.
     *
     * Estos SIEMPRE cuentan conversaciones,
     * tal como definimos en el frontend.
     */
        $tabCondition = match ($activeTab) {
            'matches' =>
            'c.compatibility_id IS NOT NULL',

            'external' =>
            "c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'initiator'",

            default =>
            "c.compatibility_id IS NULL
 AND c.opportunity_type IS NOT NULL
 AND c.opportunity_id IS NOT NULL
 AND cp.role = 'owner'",
        };

        $statusStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS all_count,

            SUM(c.status = 'open')
                AS open_count,

            SUM(c.status = 'negotiating')
                AS negotiating_count,

            SUM(c.status = 'visit_scheduled')
                AS visit_scheduled_count,

            SUM(c.status = 'closed')
                AS closed_count,

            SUM(c.status = 'discarded')
                AS discarded_count

        FROM conversation_participants cp

        INNER JOIN conversations c
            ON c.id = cp.conversation_id

        WHERE
            cp.user_id = :user_id

            AND cp.deleted_at IS NULL

            AND c.deleted_at IS NULL

            AND {$archiveCondition}

            AND {$tabCondition}
    ");

        $statusStmt->execute([
            ':user_id' => $userId,
        ]);

        $statusRow =
            $statusStmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        return [
            'tabs' => [
                'own' => [
                    'groups' =>
                    (int)($tabRow['own_groups'] ?? 0),

                    'conversations' =>
                    (int)($tabRow['own_conversations'] ?? 0),

                    'unread' =>
                    (int)($unreadRow['own_unread'] ?? 0),
                ],

                'external' => [
                    'groups' =>
                    (int)($tabRow['external_groups'] ?? 0),

                    'conversations' =>
                    (int)($tabRow['external_conversations'] ?? 0),

                    'unread' =>
                    (int)($unreadRow['external_unread'] ?? 0),
                ],

                'matches' => [
                    'conversations' =>
                    (int)($tabRow['match_conversations'] ?? 0),

                    'unread' =>
                    (int)($unreadRow['match_unread'] ?? 0),
                ],
            ],

            'statuses' => [
                'all' =>
                (int)($statusRow['all_count'] ?? 0),

                'open' =>
                (int)($statusRow['open_count'] ?? 0),

                'negotiating' =>
                (int)($statusRow['negotiating_count'] ?? 0),

                'visit_scheduled' =>
                (int)($statusRow['visit_scheduled_count'] ?? 0),

                'closed' =>
                (int)($statusRow['closed_count'] ?? 0),

                'discarded' =>
                (int)($statusRow['discarded_count'] ?? 0),
            ],
        ];
    }

    public static function getConversationDetail(int $userId, int $conversationId): array
    {
        self::assertParticipant($userId, $conversationId);

        $pdo = self::db();

        $stmt = $pdo->prepare("
    SELECT
        c.*,
        cp.archived_at,
        cp.last_read_message_id,
        cp.last_read_at
    FROM conversations c
    INNER JOIN conversation_participants cp
        ON cp.conversation_id = c.id
       AND cp.user_id = :user_id
       AND cp.deleted_at IS NULL
    WHERE c.id = :id
      AND c.deleted_at IS NULL
    LIMIT 1
");

        $stmt->execute([
            ':id' => $conversationId,
            ':user_id' => $userId,
        ]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conversation) {
            throw new Exception('Conversación no encontrada.', 404);
        }

        $conversation = self::attachOpportunityData($conversation);

        $participants = self::getParticipants($conversationId);
        $messages = self::getMessages($conversationId);
        $shareRequest = self::getActiveContactShareRequest($conversationId);
        $contactData = self::getUnlockedContactData($userId, $conversation);
        $readState = self::getConversationReadState($conversationId);

        self::markAsRead($userId, $conversationId);

        return [
            'conversation' => $conversation,
            'participants' => $participants,
            'messages' => $messages,
            'contact_share_request' => $shareRequest,
            'contact_data' => $contactData,
            'read_state' => $readState,
        ];
    }

    public static function sendMessage(
        int $userId,
        int $conversationId,
        array $input
    ): array {
        self::assertParticipant($userId, $conversationId);

        $body = trim((string)($input['body'] ?? ''));

        if ($body === '') {
            throw new Exception('El mensaje no puede estar vacío.', 422);
        }

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $messageResult = self::createMessage($conversationId, $userId, $body);
            self::touchConversation($conversationId, $messageResult['message']['id']);

            $receiverIds = self::getOtherParticipantIds($conversationId, $userId);

            foreach ($receiverIds as $receiverId) {
                NotificationService::notifyNewMessage(
                    (int)$receiverId,
                    $conversationId,
                    self::getConversationById($conversationId),
                    $pdo
                );
            }

            $pdo->commit();

            return $messageResult;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateStatus(
        int $userId,
        int $conversationId,
        array $input
    ): array {
        self::assertParticipant(
            $userId,
            $conversationId
        );

        $status = trim(
            (string)($input['status'] ?? '')
        );

        $allowedStatuses = [
            'open',
            'negotiating',
            'visit_scheduled',
            'closed',
            'discarded',
        ];

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            throw new Exception(
                'Estado de conversación inválido.',
                422
            );
        }

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            /*
         * 1. Actualizamos el estado.
         */
            $stmt = $pdo->prepare("
            UPDATE conversations
            SET
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :conversation_id
              AND deleted_at IS NULL
        ");

            $stmt->execute([
                ':status' => $status,
                ':conversation_id' =>
                $conversationId,
            ]);

            /*
         * 2. Creamos mensaje automático
         *    dentro de la misma transacción.
         */
            $systemMessage =
                self::createSystemMessage(
                    $conversationId,
                    'El estado de la conversación cambió a: '
                        . self::conversationStatusLabel(
                            $status
                        )
                        . '.'
                );

            /*
         * 3. Actualizamos la actividad
         *    de la conversación.
         */
            self::touchConversation(
                $conversationId,
                (int)$systemMessage['id']
            );

            /*
         * 4. Obtenemos los demás participantes.
         */
            $receiverIds =
                self::getOtherParticipantIds(
                    $conversationId,
                    $userId
                );

            /*
         * 5. Creamos las notificaciones
         *    usando explícitamente el mismo PDO.
         */
            foreach (
                $receiverIds as $receiverId
            ) {
                NotificationService::notifyStatusChanged(
                    (int)$receiverId,
                    $conversationId,
                    self::getConversationById(
                        $conversationId
                    ),
                    $pdo
                );
            }

            /*
         * 6. Todo salió bien.
         */
            $pdo->commit();

            return [
                'conversation' =>
                self::getConversationById(
                    $conversationId
                ),

                'status' =>
                $status,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function archiveConversation(int $userId, int $conversationId): array
    {
        self::assertParticipant($userId, $conversationId);

        $pdo = self::db();

        $stmt = $pdo->prepare("
        UPDATE conversation_participants
        SET archived_at = NOW()
        WHERE conversation_id = :conversation_id
          AND user_id = :user_id
          AND deleted_at IS NULL
    ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);

        return [
            'conversation_id' => $conversationId,
            'archived' => true,
        ];
    }

    public static function unarchiveConversation(int $userId, int $conversationId): array
    {
        self::assertParticipant($userId, $conversationId);

        $pdo = self::db();

        $stmt = $pdo->prepare("
        UPDATE conversation_participants
        SET archived_at = NULL
        WHERE conversation_id = :conversation_id
          AND user_id = :user_id
          AND deleted_at IS NULL
    ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);

        return [
            'conversation_id' => $conversationId,
            'archived' => false,
        ];
    }

    private static function conversationStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Abierta',
            'negotiating' => 'En negociación',
            'visit_scheduled' => 'Visita coordinada',
            'closed' => 'Cerrada',
            'discarded' => 'Descartada',
            default => $status,
        };
    }

    public static function requestContactShare(
        int $userId,
        int $conversationId
    ): array {
        self::assertParticipant(
            $userId,
            $conversationId
        );

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            /*
         * Bloqueamos la conversación.
         *
         * Esto serializa cualquier intento simultáneo
         * de solicitar compartir contacto sobre
         * esta misma conversación.
         */
            $stmt = $pdo->prepare("
            SELECT *
            FROM conversations
            WHERE id = :conversation_id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $stmt->execute([
                ':conversation_id' => $conversationId,
            ]);

            $conversation =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversation) {
                throw new Exception(
                    'Conversación no encontrada.',
                    404
                );
            }

            if (
                (int)$conversation['contact_shared'] === 1
            ) {
                throw new Exception(
                    'Los datos de contacto ya fueron compartidos.',
                    422
                );
            }

            $receiverIds =
                self::getOtherParticipantIds(
                    $conversationId,
                    $userId
                );

            if (!$receiverIds) {
                throw new Exception(
                    'No se encontró destinatario para la solicitud.',
                    404
                );
            }

            $requestedToUserId =
                (int)$receiverIds[0];

            /*
         * Buscamos cualquier solicitud pendiente
         * de esta conversación.
         *
         * No solamente la misma dirección.
         * Así evitamos que ambas partes creen
         * solicitudes cruzadas simultáneamente.
         */
            $stmt = $pdo->prepare("
            SELECT *
            FROM contact_share_requests
            WHERE conversation_id = :conversation_id
              AND status = 'pending'
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

            $stmt->execute([
                ':conversation_id' => $conversationId,
            ]);

            $existing =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $pdo->commit();

                return [
                    'request' => $existing,
                    'already_exists' => true,
                ];
            }

            $stmt = $pdo->prepare("
            INSERT INTO contact_share_requests (
                conversation_id,
                requested_by_user_id,
                requested_to_user_id,
                status,
                requester_accepted,
                receiver_accepted,
                expires_at
            )
            VALUES (
                :conversation_id,
                :requested_by_user_id,
                :requested_to_user_id,
                'pending',
                1,
                0,
                DATE_ADD(NOW(), INTERVAL 7 DAY)
            )
        ");

            $stmt->execute([
                ':conversation_id' =>
                $conversationId,

                ':requested_by_user_id' =>
                $userId,

                ':requested_to_user_id' =>
                $requestedToUserId,
            ]);

            $requestId =
                (int)$pdo->lastInsertId();

            $systemMessage =
                self::createSystemMessage(
                    $conversationId,
                    'Solicitud para compartir datos de contacto.'
                );

            self::touchConversation(
                $conversationId,
                (int)$systemMessage['id']
            );

            NotificationService::notifyContactShareRequested(
                $requestedToUserId,
                $conversationId,
                $conversation,
                $pdo
            );

            $pdo->commit();

            return [
                'request' =>
                self::getContactShareRequestById(
                    $requestId
                ),

                'already_exists' => false,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function respondContactShare(
        int $userId,
        int $conversationId,
        array $input
    ): array {
        self::assertParticipant($userId, $conversationId);

        $decision = trim((string)($input['decision'] ?? ''));

        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            throw new Exception('La respuesta debe ser accepted o rejected.', 422);
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM contact_share_requests
            WHERE conversation_id = :conversation_id
              AND requested_to_user_id = :user_id
              AND status = 'pending'
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('No hay una solicitud pendiente para responder.', 404);
        }

        $pdo->beginTransaction();

        try {
            if ($decision === 'accepted') {
                $stmt = $pdo->prepare("
                    UPDATE contact_share_requests
                    SET
                        status = 'accepted',
                        receiver_accepted = 1,
                        responded_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([':id' => $request['id']]);

                $stmt = $pdo->prepare("
                    UPDATE conversations
                    SET contact_shared = 1
                    WHERE id = :conversation_id
                ");

                $stmt->execute([':conversation_id' => $conversationId]);

                $systemMessage = self::createSystemMessage(
                    $conversationId,
                    'Ambas partes aceptaron compartir datos de contacto.'
                );
                NotificationService::notifyContactShareAccepted(
                    (int)$request['requested_by_user_id'],
                    $conversationId,
                    self::getConversationById($conversationId),
                    $pdo
                );
            } else {
                $stmt = $pdo->prepare("
                    UPDATE contact_share_requests
                    SET
                        status = 'rejected',
                        rejected_reason = :reason,
                        responded_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $request['id'],
                    ':reason' => trim((string)($input['reason'] ?? '')),
                ]);

                $systemMessage = self::createSystemMessage(
                    $conversationId,
                    'La solicitud para compartir datos fue rechazada.'
                );

                NotificationService::notifyContactShareRejected(
                    (int)$request['requested_by_user_id'],
                    $conversationId,
                    self::getConversationById($conversationId),
                    $pdo
                );
            }

            self::touchConversation($conversationId, $systemMessage['id']);

            $pdo->commit();

            return [
                'request' => self::getContactShareRequestById((int)$request['id']),
                'conversation' => self::getConversationById($conversationId),
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function createMessage(
        int $conversationId,
        int $senderUserId,
        string $body
    ): array {
        $sensitive = self::detectSensitiveData($body);

        if ($sensitive['has_sensitive_data']) {
            throw new Exception(
                'No se pueden compartir datos de contacto todavía. Usá la opción para solicitar compartir datos.',
                422
            );
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_user_id,
                body,
                sanitized_body,
                message_type,
                contains_sensitive_data,
                is_blocked,
                blocked_reason
            )
            VALUES (
                :conversation_id,
                :sender_user_id,
                :body,
                :sanitized_body,
                'text',
                0,
                0,
                NULL
            )
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':sender_user_id' => $senderUserId,
            ':body' => $body,
            ':sanitized_body' => $body,
        ]);

        $messageId = (int)$pdo->lastInsertId();

        return [
            'message' => self::getMessageById($messageId),
        ];
    }

    private static function createSystemMessage(
        int $conversationId,
        string $body
    ): array {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_user_id,
                body,
                sanitized_body,
                is_system,
                message_type
            )
            VALUES (
                :conversation_id,
                0,
                :body,
                :sanitized_body,
                1,
                'system'
            )
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':body' => $body,
            ':sanitized_body' => $body,
        ]);

        return self::getMessageById((int)$pdo->lastInsertId());
    }

    private static function detectSensitiveData(string $text): array
    {
        $patterns = [
            'email' => '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i',
            'url' => '/\bhttps?:\/\/[^\s]+|\bwww\.[^\s]+/i',
            'phone' => '/(?:\+?\d[\d\s().-]{7,}\d)/',
            'whatsapp' => '/\b(wsp|whatsapp|wa\.me)\b/i',
            'instagram' => '/\b(instagram|ig|@[\w.]{3,})\b/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                return [
                    'has_sensitive_data' => true,
                    'type' => $type,
                ];
            }
        }

        return [
            'has_sensitive_data' => false,
            'type' => null,
        ];
    }

    private static function validateOpportunityType(string $type): void
    {
        if (!in_array($type, ['property', 'search_request', 'development'], true)) {
            throw new Exception('Tipo de oportunidad inválido.', 422);
        }
    }

    private static function findOpportunityOwner(string $type, int $id): ?int
    {
        $pdo = self::db();

        $table = match ($type) {
            'property' => 'properties',
            'search_request' => 'search_requests',
            'development' => 'developments',
        };

        $stmt = $pdo->prepare("
            SELECT created_by_user_id
            FROM {$table}
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $ownerId = $stmt->fetchColumn();

        return $ownerId ? (int)$ownerId : null;
    }

    private static function buildConversationSubject(string $type, int $id): string
    {
        $pdo = self::db();

        $table = match ($type) {
            'property' => 'properties',
            'search_request' => 'search_requests',
            'development' => 'developments',
        };

        $stmt = $pdo->prepare("
            SELECT title
            FROM {$table}
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        return (string)($stmt->fetchColumn() ?: 'Consulta');
    }

    private static function findExistingConversation(
        string $type,
        int $opportunityId,
        int $userId,
        int $ownerUserId
    ): ?array {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT c.*
            FROM conversations c
            INNER JOIN conversation_participants cp1
                ON cp1.conversation_id = c.id
               AND cp1.user_id = :user_id
               AND cp1.deleted_at IS NULL
            INNER JOIN conversation_participants cp2
                ON cp2.conversation_id = c.id
               AND cp2.user_id = :owner_user_id
               AND cp2.deleted_at IS NULL
            WHERE c.opportunity_type = :type
              AND c.opportunity_id = :opportunity_id
              AND c.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':type' => $type,
            ':opportunity_id' => $opportunityId,
            ':user_id' => $userId,
            ':owner_user_id' => $ownerUserId,
        ]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    private static function addParticipant(
        int $conversationId,
        int $userId,
        string $role
    ): void {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO conversation_participants (
                conversation_id,
                user_id,
                role
            )
            VALUES (
                :conversation_id,
                :user_id,
                :role
            )
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);
    }

    private static function assertParticipant(int $userId, int $conversationId): void
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT id
            FROM conversation_participants
            WHERE conversation_id = :conversation_id
              AND user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new Exception('No tenés acceso a esta conversación.', 403);
        }
    }

    private static function touchConversation(
        int $conversationId,
        int $messageId
    ): void {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            UPDATE conversations
            SET
                last_message_id = :message_id,
                last_message_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :conversation_id
        ");

        $stmt->execute([
            ':message_id' => $messageId,
            ':conversation_id' => $conversationId,
        ]);
    }

    private static function markAsRead(int $userId, int $conversationId): void
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT id
            FROM messages
            WHERE conversation_id = :conversation_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([':conversation_id' => $conversationId]);
        $lastMessageId = $stmt->fetchColumn();

        if (!$lastMessageId) return;

        $stmt = $pdo->prepare("
            UPDATE conversation_participants
            SET
                last_read_message_id = :message_id,
                last_read_at = NOW()
            WHERE conversation_id = :conversation_id
              AND user_id = :user_id
        ");

        $stmt->execute([
            ':message_id' => $lastMessageId,
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);
    }

    private static function getParticipants(int $conversationId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            cp.*,
            u.email
        FROM conversation_participants cp
        INNER JOIN users u ON u.id = cp.user_id
        WHERE cp.conversation_id = :conversation_id
          AND cp.deleted_at IS NULL
        ORDER BY cp.id ASC
    ");

        $stmt->execute([':conversation_id' => $conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    private static function getOtherParticipantIds(
        int $conversationId,
        int $userId
    ): array {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT user_id
            FROM conversation_participants
            WHERE conversation_id = :conversation_id
              AND user_id <> :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function getMessages(int $conversationId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            m.*,
            NULL AS sender_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.sender_user_id
        WHERE m.conversation_id = :conversation_id
          AND m.deleted_at IS NULL
        ORDER BY m.created_at ASC, m.id ASC
    ");

        $stmt->execute([':conversation_id' => $conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function getMessageById(int $messageId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            m.*,
            NULL AS sender_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.sender_user_id
        WHERE m.id = :id
        LIMIT 1
    ");

        $stmt->execute([':id' => $messageId]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new Exception('Mensaje no encontrado.', 404);
        }

        return $message;
    }

    private static function getConversationById(int $conversationId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM conversations
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':id' => $conversationId]);

        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conversation) {
            throw new Exception('Conversación no encontrada.', 404);
        }

        return $conversation;
    }

    private static function getPendingContactShareRequest(
        int $conversationId,
        int $requestedByUserId,
        int $requestedToUserId
    ): ?array {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM contact_share_requests
            WHERE conversation_id = :conversation_id
              AND requested_by_user_id = :requested_by_user_id
              AND requested_to_user_id = :requested_to_user_id
              AND status = 'pending'
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':requested_by_user_id' => $requestedByUserId,
            ':requested_to_user_id' => $requestedToUserId,
        ]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    private static function getActiveContactShareRequest(int $conversationId): ?array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM contact_share_requests
            WHERE conversation_id = :conversation_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([':conversation_id' => $conversationId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    private static function getContactShareRequestById(int $id): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM contact_share_requests
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception('Solicitud no encontrada.', 404);
        }

        return $item;
    }

    private static function getTableColumns(string $table): array
    {
        $pdo = self::db();

        $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            fn($row) => $row['Field'],
            $rows
        );
    }

    private static function joinClean(array $parts): ?string
    {
        $clean = array_values(array_filter(array_map(
            fn($part) => trim((string)$part),
            $parts
        )));

        return $clean ? implode(', ', $clean) : null;
    }

    private static function attachOpportunityData(array $conversation): array
    {
        $type = $conversation['opportunity_type'] ?? null;
        $id = (int)($conversation['opportunity_id'] ?? 0);

        if (!$type || $id <= 0) {
            return $conversation;
        }

        $pdo = self::db();

        $table = match ($type) {
            'property' => 'properties',
            'search_request' => 'search_requests',
            'development' => 'developments',
            default => null,
        };

        if (!$table) {
            return $conversation;
        }

        $columns = self::getTableColumns($table);

        $select = ['id'];

        foreach (
            [
                'title',
                'status',
                'price',
                'currency',
                'budget_min',
                'budget_max',
                'value_min',
                'value_max',
                'price_from',
                'price_to',
                'country',
                'province',
                'city',
                'zone',
                'address',
                'formatted_address',
            ] as $column
        ) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $stmt = $pdo->prepare("
        SELECT " . implode(', ', $select) . "
        FROM {$table}
        WHERE id = :id
        LIMIT 1
    ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return $conversation;
        }

        $conversation['opportunity_title'] =
            $item['title'] ?? $conversation['subject'] ?? 'Oportunidad';

        $conversation['opportunity_status'] =
            $item['status'] ?? null;

        $conversation['opportunity_location'] = self::joinClean([
            $item['city'] ?? null,
            $item['zone'] ?? null,
            $item['province'] ?? null,
            $item['country'] ?? null,
        ]);

        $conversation['opportunity_address'] =
            $item['formatted_address'] ??
            $item['address'] ??
            $conversation['opportunity_location'];

        $conversation['opportunity_currency'] =
            $item['currency'] ?? null;

        $conversation['opportunity_price'] =
            $item['price'] ??
            $item['price_from'] ??
            $item['budget_min'] ??
            $item['value_min'] ??
            null;

        $conversation['opportunity_price_to'] =
            $item['price_to'] ??
            $item['budget_max'] ??
            $item['value_max'] ??
            null;

        $conversation['opportunity_url'] = match ($type) {
            'property' => '/explore/properties/' . $id,
            'search_request' => '/explore/search-requests/' . $id,
            'development' => '/explore/developments/' . $id,
            default => null,
        };

        $conversation['opportunity_image_url'] =
            self::getOpportunityImageUrl($type, $id);

        return $conversation;
    }

    private static function getOpportunityImageUrl(string $type, int $id): ?string
    {
        $pdo = self::db();

        if ($type === 'property') {
            $stmt = $pdo->prepare("
            SELECT id
            FROM property_images
            WHERE property_id = :id
              AND deleted_at IS NULL
            ORDER BY is_cover DESC, sort_order ASC, id ASC
            LIMIT 1
        ");

            $stmt->execute([':id' => $id]);

            $imageId = $stmt->fetchColumn();

            return $imageId ? '/property-images/' . $imageId . '/view' : null;
        }

        if ($type === 'development') {
            $stmt = $pdo->prepare("
            SELECT id
            FROM development_images
            WHERE development_id = :id
              AND deleted_at IS NULL
            ORDER BY is_cover DESC, sort_order ASC, id ASC
            LIMIT 1
        ");

            $stmt->execute([':id' => $id]);

            $imageId = $stmt->fetchColumn();

            return $imageId ? '/development-images/' . $imageId . '/view' : null;
        }

        return null;
    }

    private static function getConversationReadState(int $conversationId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            user_id,
            role,
            last_read_message_id,
            last_read_at
        FROM conversation_participants
        WHERE conversation_id = :conversation_id
          AND deleted_at IS NULL
    ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
        ]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($item) {
            return [
                'user_id' => (int)$item['user_id'],
                'role' => $item['role'],
                'last_read_message_id' => $item['last_read_message_id']
                    ? (int)$item['last_read_message_id']
                    : null,
                'last_read_at' => $item['last_read_at'],
            ];
        }, $items);
    }

    private static function getUnlockedContactData(
        int $userId,
        array $conversation
    ): ?array {
        if ((int)($conversation['contact_shared'] ?? 0) !== 1) {
            return null;
        }

        $otherUserId = null;

        if ((int)$conversation['created_by_user_id'] === $userId) {
            $otherUserId = (int)$conversation['owner_user_id'];
        } elseif ((int)$conversation['owner_user_id'] === $userId) {
            $otherUserId = (int)$conversation['created_by_user_id'];
        }

        if (!$otherUserId) {
            return null;
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.real_estate_id,

            re.name AS real_estate_name,
            re.legal_name AS real_estate_legal_name,
            re.cuit AS real_estate_cuit,
            re.phone AS real_estate_phone,
            re.email AS real_estate_email,
            re.website AS real_estate_website,
            re.instagram AS real_estate_instagram,
            re.facebook AS real_estate_facebook,
            re.address AS real_estate_address,
            re.address_locality AS real_estate_locality,
            re.address_province AS real_estate_province,
            re.profile_status AS real_estate_profile_status,
            re.validation_status AS real_estate_validation_status
        FROM users u
        LEFT JOIN real_estates re ON re.id = u.real_estate_id
        WHERE u.id = :id
        LIMIT 1
    ");

        $stmt->execute([
            ':id' => $otherUserId,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        return [
            'user_id' => (int)$user['id'],

            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,

            'real_estate_id' => isset($user['real_estate_id'])
                ? (int)$user['real_estate_id']
                : null,

            'real_estate_name' => $user['real_estate_name'] ?? null,
            'real_estate_legal_name' => $user['real_estate_legal_name'] ?? null,
            'real_estate_cuit' => $user['real_estate_cuit'] ?? null,
            'real_estate_phone' => $user['real_estate_phone'] ?? null,
            'real_estate_email' => $user['real_estate_email'] ?? null,
            'real_estate_website' => $user['real_estate_website'] ?? null,
            'real_estate_instagram' => $user['real_estate_instagram'] ?? null,
            'real_estate_facebook' => $user['real_estate_facebook'] ?? null,
            'real_estate_address' => $user['real_estate_address'] ?? null,
            'real_estate_locality' => $user['real_estate_locality'] ?? null,
            'real_estate_province' => $user['real_estate_province'] ?? null,
            'real_estate_profile_status' => isset($user['real_estate_profile_status'])
                ? (int)$user['real_estate_profile_status']
                : null,
            'real_estate_validation_status' => isset($user['real_estate_validation_status'])
                ? (int)$user['real_estate_validation_status']
                : null,
        ];
    }

    public static function unreadCount(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages m
        INNER JOIN conversations c
            ON c.id = m.conversation_id
           AND c.deleted_at IS NULL
        INNER JOIN conversation_participants cp
            ON cp.conversation_id = c.id
           AND cp.user_id = :user_id
           AND cp.deleted_at IS NULL
           AND cp.archived_at IS NULL
        WHERE m.deleted_at IS NULL
          AND m.sender_user_id <> :user_id_sender
          AND (
            cp.last_read_message_id IS NULL
            OR m.id > cp.last_read_message_id
          )
    ");

        $stmt->execute([
            ':user_id' => $userId,
            ':user_id_sender' => $userId,
        ]);

        return [
            'count' => (int)$stmt->fetchColumn(),
        ];
    }
}
