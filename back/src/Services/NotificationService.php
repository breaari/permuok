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
        ?int $relatedId = null,
        ?PDO $pdo = null
    ): void {
        $pdo ??= self::db();

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
        ?string $customBody = null,
        ?PDO $pdo = null
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
            $conversationId,
            $pdo
        );
    }

    public static function notifyNewConversation(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        /*
     * 1. Notificación interna.
     */
        self::createConversationNotification(
            $userId,
            'new_conversation',
            $conversationId,
            $conversation,
            null,
            $pdo
        );

        /*
     * 2. Destinatario del email.
     */
        $recipient =
            self::getEmailRecipient(
                $userId
            );

        if (!$recipient) {
            return;
        }

        $subjectName =
            self::conversationSubject(
                $conversation
            );

        $emailSubject =
            "Nueva consulta sobre {$subjectName}";

        $message =
            'Recibiste una nueva consulta sobre una oportunidad publicada en Permuok.';

        $appUrl = rtrim(
            (string)(
                $_ENV['APP_URL']
                ?? 'https://permuok.com'
            ),
            '/'
        );

        $htmlBody =
            self::buildEmailLayout(
                $emailSubject,
                $message,
                'Ver consulta',
                $appUrl
            );

        $textBody =
            $emailSubject .
            "\n\n" .
            $message .
            "\n\n" .
            $appUrl;

        EmailJobService::enqueue(
            $recipient['email'],
            'new_conversation',
            $emailSubject,
            $htmlBody,
            $textBody,
            $userId,
            $recipient['name'],
            'conversation',
            $conversationId,
            9,
            $pdo
        );
    }

    public static function notifyNewMessage(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        /*
     * 1. Conservamos la notificación interna.
     */
        self::createConversationNotification(
            $userId,
            'new_message',
            $conversationId,
            $conversation,
            null,
            $pdo
        );

        /*
     * 2. Resolvemos el usuario destinatario.
     */
        $recipient =
            self::getEmailRecipient(
                $userId
            );

        if (!$recipient) {
            return;
        }

        $subjectName =
            self::conversationSubject(
                $conversation
            );

        $emailSubject =
            "Nuevo mensaje en {$subjectName}";

        $message =
            'Recibiste un nuevo mensaje en una conversación de Permuok.';

        /*
     * De momento usamos APP_URL.
     * Después confirmamos la ruta exacta del frontend
     * para abrir directamente esa conversación.
     */
        $appUrl = rtrim(
            (string)(
                $_ENV['APP_URL']
                ?? 'https://permuok.com'
            ),
            '/'
        );

        $buttonUrl =
            $appUrl;

        $htmlBody =
            self::buildEmailLayout(
                $emailSubject,
                $message,
                'Ir a Permuok',
                $buttonUrl
            );

        $textBody =
            $emailSubject .
            "\n\n" .
            $message .
            "\n\n" .
            $buttonUrl;

        EmailJobService::enqueue(
            $recipient['email'],
            'new_message',
            $emailSubject,
            $htmlBody,
            $textBody,
            $userId,
            $recipient['name'],
            'conversation',
            $conversationId,
            8,
            $pdo
        );
    }

    public static function notifyContactShareRequested(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_requested',
            $conversationId,
            $conversation,
            null,
            $pdo
        );
    }


    public static function notifyContactShareAccepted(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_accepted',
            $conversationId,
            $conversation,
            null,
            $pdo
        );
    }

    public static function notifyContactShareRejected(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        self::createConversationNotification(
            $userId,
            'contact_share_rejected',
            $conversationId,
            $conversation,
            null,
            $pdo
        );
    }

    public static function notifyStatusChanged(
        int $userId,
        int $conversationId,
        array $conversation = [],
        ?PDO $pdo = null
    ): void {
        self::createConversationNotification(
            $userId,
            'conversation_status_changed',
            $conversationId,
            $conversation,
            null,
            $pdo
        );
    }
    public static function notifyCompatibilityInterest(
        int $userId,
        int $compatibilityId
    ): void {
        if ($userId <= 0 || $compatibilityId <= 0) {
            return;
        }

        self::create(
            $userId,
            'compatibility_interest',
            'Hay interés en una compatibilidad',
            'La otra inmobiliaria indicó que le interesa avanzar con una oportunidad compatible.',
            'compatibility',
            $compatibilityId
        );
    }

    public static function notifyCompatibilityInterestForRealEstate(
        int $realEstateId,
        int $compatibilityId,
        ?int $excludeUserId = null
    ): int {
        if (
            $realEstateId <= 0 ||
            $compatibilityId <= 0
        ) {
            return 0;
        }

        $pdo = self::db();

        $sql = "
        SELECT id
        FROM users
        WHERE real_estate_id = :real_estate_id
          AND role IN (2, 3)
          AND is_active = 1
          AND deleted_at IS NULL
    ";

        $params = [
            'real_estate_id' => $realEstateId,
        ];

        if (
            $excludeUserId !== null &&
            $excludeUserId > 0
        ) {
            $sql .= "
            AND id <> :exclude_user_id
        ";

            $params['exclude_user_id'] =
                $excludeUserId;
        }

        $sql .= "
        ORDER BY id ASC
    ";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        $userIds =
            $st->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        $created = 0;

        foreach ($userIds as $recipientUserId) {
            self::notifyCompatibilityInterest(
                (int)$recipientUserId,
                $compatibilityId
            );

            $created++;
        }

        return $created;
    }

    public static function notifyCompatibilityMutualInterest(
        int $userId,
        int $compatibilityId,
        ?int $conversationId = null,
        ?PDO $pdo = null
    ): void {
        if (
            $userId <= 0 ||
            $compatibilityId <= 0
        ) {
            return;
        }

        /*
     * Si ya existe conversación,
     * la notificación apunta directamente
     * al chat.
     */
        if (
            $conversationId &&
            $conversationId > 0
        ) {
            self::create(
                $userId,
                'compatibility_mutual_interest',
                '¡Hay interés mutuo!',
                'Ambas inmobiliarias mostraron interés. Ya pueden comenzar a conversar.',
                'conversation',
                $conversationId,
                $pdo
            );

            $relatedType =
                'conversation';

            $relatedId =
                $conversationId;

            $buttonText =
                'Abrir conversación';
        } else {
            self::create(
                $userId,
                'compatibility_mutual_interest',
                '¡Hay interés mutuo!',
                'Ambas inmobiliarias mostraron interés en esta compatibilidad.',
                'compatibility',
                $compatibilityId,
                $pdo
            );
            $relatedType =
                'compatibility';

            $relatedId =
                $compatibilityId;

            $buttonText =
                'Ver oportunidad';
        }

        /*
     * Email.
     */
        $recipient =
            self::getEmailRecipient(
                $userId
            );

        if (!$recipient) {
            return;
        }

        $emailSubject =
            '¡Hay interés mutuo en una oportunidad!';

        $message =
            $conversationId &&
            $conversationId > 0
            ? 'Ambas inmobiliarias mostraron interés en la oportunidad. El chat ya está habilitado para que puedan comenzar a conversar.'
            : 'Ambas inmobiliarias mostraron interés en una compatibilidad de Permuok.';

        $appUrl = rtrim(
            (string)(
                $_ENV['APP_URL']
                ?? 'https://permuok.com'
            ),
            '/'
        );

        $htmlBody =
            self::buildEmailLayout(
                $emailSubject,
                $message,
                $buttonText,
                $appUrl
            );

        $textBody =
            $emailSubject .
            "\n\n" .
            $message .
            "\n\n" .
            $appUrl;

        EmailJobService::enqueue(
            $recipient['email'],
            'compatibility_mutual_interest',
            $emailSubject,
            $htmlBody,
            $textBody,
            $userId,
            $recipient['name'],
            $relatedType,
            $relatedId,
            9,
            $pdo
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

    private static function getEmailRecipient(
        int $userId
    ): ?array {
        if ($userId <= 0) {
            return null;
        }

        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT
            id,
            first_name,
            last_name,
            email
        FROM users
        WHERE id = :id
          AND is_active = 1
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
            return null;
        }

        $email = trim(
            (string)($user['email'] ?? '')
        );

        if (
            $email === '' ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        $name = trim(
            (string)($user['first_name'] ?? '') .
                ' ' .
                (string)($user['last_name'] ?? '')
        );

        return [
            'email' => $email,
            'name' => $name,
        ];
    }

    private static function buildEmailLayout(
        string $title,
        string $message,
        string $buttonText,
        string $buttonUrl
    ): string {
        $safeTitle = htmlspecialchars(
            $title,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeMessage = htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeButtonText = htmlspecialchars(
            $buttonText,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeButtonUrl = htmlspecialchars(
            $buttonUrl,
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
</head>
<body style="
    margin:0;
    padding:0;
    background:#f8fafc;
    font-family:Arial,Helvetica,sans-serif;
    color:#0f172a;
">
    <table
        width="100%"
        cellpadding="0"
        cellspacing="0"
        style="padding:32px 16px;"
    >
        <tr>
            <td align="center">
                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    style="
                        max-width:600px;
                        background:#ffffff;
                        border-radius:18px;
                        overflow:hidden;
                        border:1px solid #e2e8f0;
                    "
                >
                    <tr>
                        <td style="
                            background:#0f172a;
                            color:#ffffff;
                            padding:24px 28px;
                        ">
                            <div style="
                                font-size:22px;
                                font-weight:700;
                            ">
                                Permuok
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="
                                margin:0 0 16px;
                                font-size:24px;
                                line-height:1.3;
                            ">
                                {$safeTitle}
                            </h1>

                            <p style="
                                margin:0 0 26px;
                                font-size:16px;
                                line-height:1.6;
                                color:#475569;
                            ">
                                {$safeMessage}
                            </p>

                            <a
                                href="{$safeButtonUrl}"
                                style="
                                    display:inline-block;
                                    background:#0f172a;
                                    color:#ffffff;
                                    text-decoration:none;
                                    font-weight:700;
                                    padding:13px 20px;
                                    border-radius:10px;
                                "
                            >
                                {$safeButtonText}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="
                            border-top:1px solid #e2e8f0;
                            padding:20px 28px;
                            font-size:12px;
                            line-height:1.5;
                            color:#94a3b8;
                        ">
                            Recibís este correo porque tenés actividad
                            en tu cuenta de Permuok.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
