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

    public static function markAsRead(
        int $userId,
        int $notificationId
    ): array {
        if (
            $userId <= 0 ||
            $notificationId <= 0
        ) {
            throw new Exception(
                'Notificación inválida.',
                422
            );
        }

        $pdo =
            self::db();

        $stmt = $pdo->prepare("
        UPDATE notifications
        SET
            is_read = 1,
            read_at = CASE
                WHEN is_read = 0
                    THEN NOW()
                ELSE read_at
            END
        WHERE id = :id
          AND user_id = :user_id
          AND deleted_at IS NULL
    ");

        $stmt->execute([
            ':id' =>
            $notificationId,

            ':user_id' =>
            $userId,
        ]);

        /*
     * MySQL puede devolver rowCount 0 cuando
     * la notificación ya estaba leída. En ese
     * caso comprobamos que realmente exista.
     */
        if ($stmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare("
            SELECT id
            FROM notifications
            WHERE id = :id
              AND user_id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $existsStmt->execute([
                ':id' =>
                $notificationId,

                ':user_id' =>
                $userId,
            ]);

            if (!$existsStmt->fetchColumn()) {
                throw new Exception(
                    'Notificación no encontrada.',
                    404
                );
            }
        }

        return [
            'id' =>
            $notificationId,

            'read' =>
            true,

            'unread_count' =>
            self::unreadCount(
                $userId
            ),
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
    |--------------------------------------------------------------------------
    | Notificación interna
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | Destinatario
    |--------------------------------------------------------------------------
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

        /*
    |--------------------------------------------------------------------------
    | URL directa a la conversación
    |--------------------------------------------------------------------------
    */
        $frontendUrl =
            rtrim(
                (string)(
                    $_ENV['FRONTEND_URL']
                    ?? 'https://permuok.com'
                ),
                '/'
            );

        $buttonUrl =
            $frontendUrl .
            '/conversations/' .
            $conversationId;

        /*
    |--------------------------------------------------------------------------
    | Datos dinámicos
    |--------------------------------------------------------------------------
    */
        $opportunityTitle =
            trim(
                (string)(
                    $conversation['opportunity_title']
                    ?? $conversation['subject']
                    ?? 'Oportunidad'
                )
            );

        $opportunityLocation =
            trim(
                (string)(
                    $conversation['opportunity_location']
                    ?? ''
                )
            );

        $opportunityType =
            match ((string)(
                $conversation['opportunity_type']
                ?? ''
            )) {
                'property' =>
                'Propiedad',

                'search_request' =>
                'Búsqueda',

                'development' =>
                'Desarrollo',

                default =>
                'Oportunidad',
            };

        $messagePreview =
            trim(
                (string)(
                    $conversation['message_preview']
                    ?? ''
                )
            );

        if (
            mb_strlen(
                $messagePreview,
                'UTF-8'
            ) > 220
        ) {
            $messagePreview =
                mb_substr(
                    $messagePreview,
                    0,
                    217,
                    'UTF-8'
                ) .
                '...';
        }

        $senderLabel =
            trim(
                (string)(
                    $conversation['sender_label']
                    ?? 'Una inmobiliaria de la red'
                )
            );

        /*
    |--------------------------------------------------------------------------
    | Imagen
    |--------------------------------------------------------------------------
    |
    | ConversationService devuelve una URL relativa firmada.
    | Para email necesitamos convertirla a URL absoluta.
    |
    */
        $imageUrl =
            trim(
                (string)(
                    $conversation['opportunity_image_url']
                    ?? ''
                )
            );

        if (
            $imageUrl !== '' &&
            str_starts_with(
                $imageUrl,
                '/'
            )
        ) {
            $backendUrl =
                rtrim(
                    (string)(
                        $_ENV['BACKEND_URL']
                        ?? $_ENV['APP_URL']
                        ?? ''
                    ),
                    '/'
                );

            if ($backendUrl !== '') {
                $imageUrl =
                    $backendUrl .
                    $imageUrl;
            }
        }

        $htmlBody =
            self::buildNewConversationEmail(
                $opportunityTitle,
                $opportunityLocation,
                $opportunityType,
                $imageUrl,
                $senderLabel,
                $messagePreview,
                $buttonUrl
            );

        $textBody =
            "Tenés una nueva consulta\n\n" .
            $opportunityTitle .
            (
                $opportunityLocation !== ''
                ? "\n" .
                $opportunityLocation
                : ''
            ) .
            "\n\n" .
            $senderLabel .
            " escribió:\n\n" .
            $messagePreview .
            "\n\n" .
            "Ver consulta:\n" .
            $buttonUrl;

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
        int $compatibilityId,
        ?PDO $pdo = null
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
            $compatibilityId,
            $pdo
        );
    }

    public static function notifyCompatibilityInterestForRealEstate(
        int $realEstateId,
        int $compatibilityId,
        ?int $excludeUserId = null,
        ?PDO $pdo = null
    ): int {
        if (
            $realEstateId <= 0 ||
            $compatibilityId <= 0
        ) {
            return 0;
        }

        $pdo ??= self::db();

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
                $compatibilityId,
                $pdo
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

    public static function notifyRealEstateProfileApproved(
        int $realEstateId,
        ?PDO $pdo = null
    ): int {
        return self::notifyRealEstateUsers(
            $realEstateId,
            'profile_approved',
            'Tu perfil fue aprobado',
            'Tu inmobiliaria fue validada. Ya podés continuar con la activación de tu membresía.',
            $pdo
        );
    }

    public static function notifyRealEstateProfileChangesApproved(
        int $realEstateId,
        ?PDO $pdo = null
    ): int {
        return self::notifyRealEstateUsers(
            $realEstateId,
            'profile_changes_approved',
            'Los cambios de tu perfil fueron aprobados',
            'La información actualizada de tu inmobiliaria ya quedó validada.',
            $pdo
        );
    }

    public static function notifyRealEstateProfileReviewRequired(
        int $realEstateId,
        ?PDO $pdo = null
    ): int {
        return self::notifyRealEstateUsers(
            $realEstateId,
            'profile_review_required',
            'Tu perfil requiere revisión',
            'Ingresá a tu perfil para consultar las observaciones y realizar los cambios necesarios.',
            $pdo
        );
    }

    private static function notifyRealEstateUsers(
        int $realEstateId,
        string $type,
        string $title,
        string $body,
        ?PDO $pdo = null
    ): int {
        if ($realEstateId <= 0) {
            return 0;
        }

        $pdo ??=
            self::db();

        $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE real_estate_id = :real_estate_id
          AND role = 2
          AND is_active = 1
          AND deleted_at IS NULL
        ORDER BY id ASC
    ");

        $stmt->execute([
            'real_estate_id' =>
            $realEstateId,
        ]);

        $userIds =
            $stmt->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        $created = 0;

        foreach ($userIds as $userId) {
            self::create(
                (int)$userId,
                $type,
                $title,
                $body,
                'real_estate',
                $realEstateId,
                $pdo
            );

            $created++;
        }

        return $created;
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

    private static function buildNewConversationEmail(
        string $opportunityTitle,
        string $opportunityLocation,
        string $opportunityType,
        string $imageUrl,
        string $senderLabel,
        string $messagePreview,
        string $buttonUrl
    ): string {
        $safeTitle =
            htmlspecialchars(
                $opportunityTitle,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeLocation =
            htmlspecialchars(
                $opportunityLocation,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeType =
            htmlspecialchars(
                $opportunityType,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeSender =
            htmlspecialchars(
                $senderLabel,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeMessage =
            nl2br(
                htmlspecialchars(
                    $messagePreview,
                    ENT_QUOTES,
                    'UTF-8'
                )
            );

        $safeButtonUrl =
            htmlspecialchars(
                $buttonUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeImageUrl =
            htmlspecialchars(
                $imageUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        /*
    |--------------------------------------------------------------------------
    | Card de oportunidad
    |--------------------------------------------------------------------------
    */
        if ($safeImageUrl !== '') {
            $opportunityCard = <<<HTML
<table
    width="100%"
    border="0"
    cellpadding="0"
    cellspacing="0"
    role="presentation"
    style="
        border:1px solid #e2e8f0;
        border-radius:12px;
        overflow:hidden;
        border-collapse:separate;
    "
>
    <tbody>
        <tr>
            <td
                width="150"
                valign="middle"
                style="
                    width:150px;
                    padding:0;
                "
            >
                <img
                    src="{$safeImageUrl}"
                    width="150"
                    alt="{$safeTitle}"
                    style="
                        display:block;
                        width:150px;
                        height:105px;
                        object-fit:cover;
                        border:0;
                    "
                />
            </td>

            <td
                valign="middle"
                style="
                    padding:18px 20px;
                    font-family:Arial, Helvetica, sans-serif;
                "
            >
                <div
                    style="
                        color:#0e1b10;
                        font-size:16px;
                        font-weight:700;
                        line-height:1;
                    "
                >
                    {$safeTitle}
                </div>

                <div
                    style="
                        margin-top:8px;
                        color:#64748b;
                        font-size:13px;
                        line-height:1;
                    "
                >
                    {$safeLocation}
                </div>

                <div
                    style="
                        margin-top:8px;
                        color:#3974ba;
                        font-size:12px;
                        font-weight:700;
                        line-height:1;
                    "
                >
                    {$safeType}
                </div>
            </td>
        </tr>
    </tbody>
</table>
HTML;
        } else {
            $opportunityCard = <<<HTML
<table
    width="100%"
    border="0"
    cellpadding="0"
    cellspacing="0"
    role="presentation"
    style="
        border:1px solid #e2e8f0;
        border-radius:12px;
        border-collapse:separate;
    "
>
    <tbody>
        <tr>
            <td
                style="
                    padding:20px;
                    font-family:Arial, Helvetica, sans-serif;
                "
            >
                <div
                    style="
                        color:#0e1b10;
                        font-size:16px;
                        font-weight:700;
                        line-height:1;
                    "
                >
                    {$safeTitle}
                </div>

                <div
                    style="
                        margin-top:8px;
                        color:#64748b;
                        font-size:13px;
                        line-height:1;
                    "
                >
                    {$safeLocation}
                </div>

                <div
                    style="
                        margin-top:8px;
                        color:#3974ba;
                        font-size:12px;
                        font-weight:700;
                        line-height:1;
                    "
                >
                    {$safeType}
                </div>
            </td>
        </tr>
    </tbody>
</table>
HTML;
        }

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office"
>
<head>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    />

    <meta
        http-equiv="Content-Type"
        content="text/html; charset=UTF-8"
    />

    <meta
        name="format-detection"
        content="telephone=no, date=no, address=no, email=no"
    />

    <meta
        name="x-apple-disable-message-reformatting"
    />

    <style>
        body {
            margin:0;
            padding:0;
        }

        table {
            mso-table-lspace:0;
            mso-table-rspace:0;
        }

        p,
        span,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin:0;
            padding:0;
        }

        a[x-apple-data-detectors] {
            color:inherit !important;
            text-decoration:inherit !important;
        }

        #MessageViewBody a {
            color:inherit;
            text-decoration:none;
        }

        img + div {
            display:none;
        }

        @media (max-width:599px) {
            .email-container {
                width:100% !important;
                min-width:0 !important;
            }

            .email-content {
                padding-left:24px !important;
                padding-right:24px !important;
            }
        }
    </style>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>

<body
    style="
        width:100%;
        margin:0;
        padding:0;
        background-color:#f0f1f5;
        -webkit-text-size-adjust:100%;
        text-size-adjust:100%;
    "
>
<table
    width="100%"
    border="0"
    cellpadding="0"
    cellspacing="0"
    bgcolor="#f0f1f5"
    role="presentation"
>
<tbody>
<tr>
<td
    align="center"
    style="
        padding:24px 12px;
        background-color:#f0f1f5;
    "
>

<table
    align="center"
    width="600"
    border="0"
    cellpadding="0"
    cellspacing="0"
    role="presentation"
    class="email-container"
    style="
        width:600px;
        min-width:600px;
        max-width:600px;
        margin:0 auto;
        background:#ffffff;
        border-radius:15px;
        overflow:hidden;
    "
>
<tbody>

<!-- LOGO -->
<tr>
<td
    align="center"
    style="
        padding:41px 24px 34px;
    "
>
    <img
        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/1c019e1200d10d5a9c00bf196125b1c2.png"
        width="234"
        alt="Permuok"
        style="
            display:block;
            width:234px;
            height:auto;
            max-width:100%;
            border:0;
        "
    />
</td>
</tr>

<!-- TÍTULO -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 24px;
        color:#0e1b10;
        font-family:Arial, Helvetica, sans-serif;
        font-size:20px;
        font-weight:700;
        line-height:1;
        text-align:left;
    "
>
    Tenés una nueva consulta
</td>
</tr>

<!-- CARD -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 28px;
    "
>
    {$opportunityCard}
</td>
</tr>

<!-- SENDER -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 12px;
        color:#0e1b10;
        font-family:Arial, Helvetica, sans-serif;
        font-size:15px;
        font-weight:700;
        line-height:1;
        text-align:left;
    "
>
    {$safeSender} escribió:
</td>
</tr>

<!-- MENSAJE -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 34px;
    "
>
    <div
        style="
            padding:18px 20px;
            background:#f3f4f6;
            border-radius:10px;
            color:#30363d;
            font-family:Arial, Helvetica, sans-serif;
            font-size:15px;
            font-weight:400;
            line-height:1;
        "
    >
        {$safeMessage}
    </div>
</td>
</tr>

<!-- BOTÓN -->
<tr>
<td
    align="center"
    style="
        padding:0 24px 40px;
    "
>

<!--[if mso]>
<v:roundrect
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:w="urn:schemas-microsoft-com:office:word"
    href="{$safeButtonUrl}"
    style="
        height:64px;
        width:336px;
        v-text-anchor:middle;
    "
    arcsize="23%"
    fillcolor="#61b228"
    strokecolor="#61b228"
>
    <w:anchorlock/>

    <center
        style="
            color:#ffffff;
            font-family:Arial,sans-serif;
            font-size:18px;
            font-weight:700;
        "
    >
        Ver consulta
    </center>
</v:roundrect>
<![endif]-->

<!--[if !mso]><!-->
<table
    role="presentation"
    cellpadding="0"
    cellspacing="0"
    border="0"
    width="336"
    style="
        width:336px;
        max-width:100%;
        border-collapse:separate;
        border-spacing:0;
    "
>
<tbody>
<tr>
<td
    bgcolor="#61b228"
    style="
        background-color:#61b228;
        border-radius:15px;
    "
>
    <a
        href="{$safeButtonUrl}"
        target="_blank"
        rel="noopener"
        style="
            color:#ffffff;
            text-decoration:none;
            display:block;
            padding:19px 8px;
            text-align:center;
            font-family:Arial, Helvetica, sans-serif;
            font-size:18px;
            font-weight:700;
            line-height:1;
        "
    >
        Ver consulta
    </a>
</td>
</tr>
</tbody>
</table>
<!--<![endif]-->

</td>
</tr>

<!-- FOOTER -->
<tr>
<td
    align="center"
    style="
        padding:0;
        border-radius:0 0 15px 15px;
        overflow:hidden;
    "
>
    <img
        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/6f5235e46c309adb2404cb6f87578e95.png"
        width="600"
        alt="Estamos para acompañarte"
        style="
            display:block;
            width:600px;
            height:auto;
            max-width:100%;
            border:0;
            border-radius:0 0 15px 15px;
        "
    />
</td>
</tr>

</tbody>
</table>

</td>
</tr>
</tbody>
</table>

</body>
</html>
HTML;
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
    border-radius:15px;
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
                                line-height:1;
                            ">
                                {$safeTitle}
                            </h1>

                            <p style="
                                margin:0 0 26px;
                                font-size:16px;
                                line-height:1;
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
                            line-height:1;
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



    public static function queueNewConversationTestEmail(
        string $email
    ): array {
        $email =
            strtolower(
                trim($email)
            );

        if (
            $email === '' ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new Exception(
                'Email de prueba inválido.'
            );
        }

        $frontendUrl =
            rtrim(
                (string)(
                    $_ENV['FRONTEND_URL']
                    ?? 'https://permuok.com'
                ),
                '/'
            );

        $htmlBody =
            self::buildNewConversationEmail(
                'Departamento 3 ambientes en Güemes',
                'Güemes · Mar del Plata',
                'Propiedad',
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=500&q=80',
                'Una inmobiliaria de la red',
                'Hola, quería consultar si considerarían una propiedad de menor valor más una diferencia en efectivo para completar la operación.',
                $frontendUrl . '/conversations/1'
            );

        $textBody =
            "Tenés una nueva consulta\n\n" .
            "Departamento 3 ambientes en Güemes\n" .
            "Güemes · Mar del Plata\n\n" .
            "Una inmobiliaria de la red escribió:\n\n" .
            "Hola, quería consultar si considerarían una propiedad de menor valor más una diferencia en efectivo para completar la operación.\n\n" .
            $frontendUrl .
            '/conversations/1';

        return EmailJobService::enqueue(
            $email,
            'new_conversation_test',
            'Nueva consulta en Permuok',
            $htmlBody,
            $textBody,
            null,
            null,
            'test',
            null,
            10
        );
    }
}
