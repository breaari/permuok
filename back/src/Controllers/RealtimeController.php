<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Services\ConversationService;
use App\Services\NotificationService;
use PDO;
use Throwable;

class RealtimeController
{
    public static function stream(): void
    {
        try {
            $user = AuthHelper::requireUser();
            $userId = (int)$user['id'];

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            @set_time_limit(0);

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $lastNotificationId = self::getLastVisibleNotificationId($userId);
            $lastUnreadCount = null;
            $lastMessageId = self::getLastVisibleMessageId($userId);

            $startedAt = time();

            while (!connection_aborted()) {
                $notificationPayload = NotificationService::list($userId, [
                    'page' => 1,
                    'limit' => 5,
                    'unread' => 1,
                ]);

                $notifications = $notificationPayload['items'] ?? [];

                foreach (array_reverse($notifications) as $notification) {
                    $notificationId = (int)($notification['id'] ?? 0);

                    if ($notificationId > $lastNotificationId) {
                        self::sendEvent('notification.created', $notification);
                        $lastNotificationId = $notificationId;
                    }
                }

                $newMessages = self::getNewMessages($userId, $lastMessageId);

                foreach ($newMessages as $message) {
                    self::sendEvent('message.created', $message);
                    $lastMessageId = max($lastMessageId, (int)$message['id']);
                }

                $unreadPayload = ConversationService::unreadCount($userId);
                $unreadCount = (int)($unreadPayload['count'] ?? 0);

                if ($lastUnreadCount === null || $unreadCount !== $lastUnreadCount) {
                    self::sendEvent('conversation.unread_count', [
                        'count' => $unreadCount,
                    ]);

                    $lastUnreadCount = $unreadCount;
                }

                self::sendEvent('ping', [
                    'time' => time(),
                ]);

                @ob_flush();
                @flush();

                if (time() - $startedAt > 55) {
                    break;
                }

                sleep(3);
            }
        } catch (Throwable $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getLastVisibleNotificationId(int $userId): int
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(id), 0)
        FROM notifications
        WHERE user_id = :user_id
    ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn();
    }
    private static function getLastVisibleMessageId(int $userId): int
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(m.id), 0)
            FROM messages m
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id
               AND cp.user_id = :user_id
               AND cp.deleted_at IS NULL
            INNER JOIN conversations c
                ON c.id = m.conversation_id
               AND c.deleted_at IS NULL
            WHERE m.deleted_at IS NULL
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    private static function getNewMessages(int $userId, int $lastMessageId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                m.*,
                c.opportunity_type,
                c.opportunity_id,
                c.subject
            FROM messages m
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id
               AND cp.user_id = :user_id
               AND cp.deleted_at IS NULL
            INNER JOIN conversations c
                ON c.id = m.conversation_id
               AND c.deleted_at IS NULL
            WHERE m.deleted_at IS NULL
              AND m.id > :last_message_id
            ORDER BY m.id ASC
            LIMIT 50
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':last_message_id' => $lastMessageId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

        @ob_flush();
        @flush();
    }
}
