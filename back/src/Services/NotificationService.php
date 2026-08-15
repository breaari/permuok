<?php

namespace App\Services;

use Exception;
use PDO;

class NotificationService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function list(int $userId, array $query = []): array
    {
        $pdo = self::db();

        $limit = max(1, min(50, (int)($query['limit'] ?? 20)));
        $page = max(1, (int)($query['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $onlyUnread = isset($query['unread'])
            && in_array((string)$query['unread'], ['1', 'true'], true);

        $where = "
            user_id = :user_id
            AND deleted_at IS NULL
        ";

        if ($onlyUnread) {
            $where .= " AND is_read = 0";
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM notifications
            WHERE {$where}
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'page' => $page,
            'limit' => $limit,
            'unread_count' => self::unreadCount($userId),
        ];
    }

    public static function unreadCount(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND is_read = 0
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return [
            'count' => (int)$stmt->fetchColumn(),
        ];
    }

    public static function markAsRead(int $userId, int $notificationId): array
    {
        if ($notificationId <= 0) {
            throw new Exception('Notificación inválida.', 422);
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET
                is_read = 1,
                read_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]);

        return [
            'id' => $notificationId,
            'read' => true,
            'unread_count' => self::unreadCount($userId),
        ];
    }

    public static function markAllAsRead(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET
                is_read = 1,
                read_at = NOW()
            WHERE user_id = :user_id
              AND is_read = 0
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return [
            'updated' => $stmt->rowCount(),
            'unread_count' => self::unreadCount($userId),
        ];
    }

    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                body,
                related_type,
                related_id
            )
            VALUES (
                :user_id,
                :type,
                :title,
                :body,
                :related_type,
                :related_id
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':body' => $body,
            ':related_type' => $relatedType,
            ':related_id' => $relatedId,
        ]);
    }

    public static function createConversationNotification(
        int $userId,
        string $type,
        int $conversationId,
        array $conversation = [],
        ?string $customBody = null
    ): void {
        if ($userId <= 0 || $conversationId <= 0) {
            return;
        }

        $title = self::buildConversationTitle($type, $conversation);
        $body = $customBody ?: self::buildConversationBody($type, $conversation);

        self::create(
            $userId,
            $type,
            $title,
            $body,
            'conversation',
            $conversationId
        );
    }

    public static function notifyNewConversation(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'new_conversation',
            $conversationId,
            $conversation
        );
    }

    public static function notifyNewMessage(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'new_message',
            $conversationId,
            $conversation
        );
    }

    public static function notifyContactShareRequested(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_requested',
            $conversationId,
            $conversation
        );
    }

    public static function notifyContactShareAccepted(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_accepted',
            $conversationId,
            $conversation
        );
    }

    public static function notifyContactShareRejected(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_rejected',
            $conversationId,
            $conversation
        );
    }

    public static function notifyStatusChanged(
        int $userId,
        int $conversationId,
        array $conversation = []
    ): void {
        self::createConversationNotification(
            $userId,
            'conversation_status_changed',
            $conversationId,
            $conversation
        );
    }

    private static function buildConversationTitle(string $type, array $conversation): string
    {
        $subject = self::conversationSubject($conversation);

        return match ($type) {
            'new_conversation' => "Nueva consulta sobre {$subject}",
            'new_message' => "Nuevo mensaje en {$subject}",
            'contact_share_requested' => "Solicitud de contacto en {$subject}",
            'contact_share_accepted' => "Datos compartidos en {$subject}",
            'contact_share_rejected' => "Solicitud rechazada en {$subject}",
            'conversation_status_changed' => "Cambio de estado en {$subject}",
            default => "Nueva actividad en {$subject}",
        };
    }

    private static function buildConversationBody(string $type, array $conversation): string
    {
        return match ($type) {
            'new_conversation' => 'Recibiste una nueva consulta sobre una oportunidad publicada.',
            'new_message' => 'Tenés una nueva respuesta en esta conversación.',
            'contact_share_requested' => 'La otra parte solicitó habilitar el intercambio de datos de contacto.',
            'contact_share_accepted' => 'La otra parte aceptó compartir datos de contacto.',
            'contact_share_rejected' => 'La otra parte rechazó compartir datos de contacto.',
            'conversation_status_changed' => 'La conversación cambió de estado.',
            default => 'Tenés nueva actividad pendiente.',
        };
    }

    private static function conversationSubject(array $conversation): string
    {
        $value =
            $conversation['opportunity_title'] ??
            $conversation['subject'] ??
            $conversation['title'] ??
            null;

        $value = trim((string)$value);

        return $value !== '' ? "“{$value}”" : 'esta conversación';
    }
}