<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\ConversationService;
use App\Services\MembershipGuard;
use Throwable;

class ConversationController
{
    public static function start(): void
    {
        try {
            $user = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            $result = ConversationService::startConversation(
                (int) $user['id'],
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function index(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = ConversationService::listConversations(
                (int) $user['id'],
                $_GET
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function inbox(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = ConversationService::getInbox(
                (int)$user['id'],
                $_GET
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function show(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::getConversationDetail(
                (int) $user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function sendMessage(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::sendMessage(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function updateStatus(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::updateStatus(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function requestContactShare(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);


            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::requestContactShare(
                (int) $user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function respondContactShare(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::respondContactShare(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        return is_array($data) ? $data : [];
    }

    private static function success(array $data = [], int $status = 200): void
    {
        ResponseHelper::ok($data, $status);
    }

    private static function error(Throwable $e): void
    {
        $status = (int) ($e->getCode() ?: 400);

        if ($status < 100 || $status > 599) {
            $status = 400;
        }

        ResponseHelper::fail($e->getMessage(), $status);
    }

    public static function archive(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::archiveConversation(
                (int)$user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unarchive(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::unarchiveConversation(
                (int)$user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unreadCount(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = ConversationService::unreadCount(
                (int)$user['id']
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
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

     public static function inboxGroup(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = ConversationService::getInboxGroup(
                (int)$user['id'],
                $_GET
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }
}
