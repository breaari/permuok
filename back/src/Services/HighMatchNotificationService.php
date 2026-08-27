<?php

namespace App\Services;

use PDO;
use Throwable;

class HighMatchNotificationService
{
    private const MIN_SCORE_TO_ALERT = 90.0;
    private const BATCH_SIZE = 100;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function process(): array
    {
        $pdo = self::db();

        $eventsProcessed = 0;
        $alertsCreated = 0;
        $emailsQueued = 0;

        while (true) {
            $pdo->beginTransaction();

            try {
                $st = $pdo->prepare("
                    SELECT
                        id,
                        entity_id,
                        payload_json
                    FROM match_events
                    WHERE event_type = 'direct_new'
                      AND entity_type = 'compatibility'
                      AND immediate_processed_at IS NULL
                    ORDER BY occurred_at ASC, id ASC
                    LIMIT " . self::BATCH_SIZE . "
                    FOR UPDATE
                ");

                $st->execute();

                $events = $st->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

                if ($events === []) {
                    $pdo->commit();
                    break;
                }

                foreach ($events as $event) {
                    $eventId =
                        (int)$event['id'];

                    $compatibilityId =
                        (int)$event['entity_id'];

                    $payload = [];

                    if (!empty($event['payload_json'])) {
                        try {
                            $payload =
                                json_decode(
                                    (string)$event['payload_json'],
                                    true,
                                    512,
                                    JSON_THROW_ON_ERROR
                                );
                        } catch (Throwable $e) {
                            $payload = [];
                        }
                    }

                    $score =
                        (float)($payload['score'] ?? 0);

                    /*
                     * Todos los eventos quedan marcados como
                     * revisados, incluso si no llegan a 90.
                     */
                    if (
                        $score >=
                        self::MIN_SCORE_TO_ALERT
                    ) {
                        $result =
                            self::createAlerts(
                                $pdo,
                                $compatibilityId,
                                $score
                            );

                        $alertsCreated +=
                            $result['notifications_created'];

                        $emailsQueued +=
                            $result['emails_queued'];
                    }

                    $stDone = $pdo->prepare("
                        UPDATE match_events
                        SET immediate_processed_at = NOW()
                        WHERE id = :id
                          AND immediate_processed_at IS NULL
                        LIMIT 1
                    ");

                    $stDone->execute([
                        'id' => $eventId,
                    ]);

                    $eventsProcessed++;
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }

            if (
                count($events) <
                self::BATCH_SIZE
            ) {
                break;
            }
        }

        return [
            'events_processed' =>
                $eventsProcessed,

            'notifications_created' =>
                $alertsCreated,

            'emails_queued' =>
                $emailsQueued,
        ];
    }

    private static function createAlerts(
        PDO $pdo,
        int $compatibilityId,
        float $score
    ): array {
        $st = $pdo->prepare("
            SELECT
                c.id,
                c.source_real_estate_id,
                c.target_real_estate_id,
                p.title AS property_title,
                sr.title AS search_title
            FROM compatibilities c

            LEFT JOIN properties p
                ON p.id = c.property_id

            LEFT JOIN search_requests sr
                ON sr.id = c.search_request_id

            WHERE c.id = :id
              AND c.deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $compatibilityId,
        ]);

        $compatibility =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$compatibility) {
            return [
                'notifications_created' => 0,
                'emails_queued' => 0,
            ];
        }

        $realEstateIds = array_values(
            array_unique(
                array_filter([
                    (int)(
                        $compatibility[
                            'source_real_estate_id'
                        ] ?? 0
                    ),
                    (int)(
                        $compatibility[
                            'target_real_estate_id'
                        ] ?? 0
                    ),
                ])
            )
        );

        if ($realEstateIds === []) {
            return [
                'notifications_created' => 0,
                'emails_queued' => 0,
            ];
        }

        $placeholders = [];
        $params = [];

        foreach (
            $realEstateIds
            as $index => $realEstateId
        ) {
            $key = 'real_estate_' . $index;

            $placeholders[] = ':' . $key;
            $params[$key] = $realEstateId;
        }

        $stUsers = $pdo->prepare("
            SELECT
                id,
                first_name,
                last_name,
                email
            FROM users
            WHERE real_estate_id IN (
                " .
                implode(
                    ', ',
                    $placeholders
                ) .
                "
            )
              AND role IN (2, 3)
              AND is_active = 1
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");

        $stUsers->execute($params);

        $users =
            $stUsers->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $propertyTitle = trim(
            (string)(
                $compatibility[
                    'property_title'
                ] ?? ''
            )
        );

        $searchTitle = trim(
            (string)(
                $compatibility[
                    'search_title'
                ] ?? ''
            )
        );

        $title =
            'Encontramos un match muy alto';

        $body =
            'Se detectó una compatibilidad de ' .
            number_format(
                $score,
                0,
                ',',
                '.'
            ) .
            '%';

        if ($propertyTitle !== '') {
            $body .=
                ' para “' .
                $propertyTitle .
                '”';
        }

        if ($searchTitle !== '') {
            $body .=
                ' con la búsqueda “' .
                $searchTitle .
                '”';
        }

        $body .= '.';

        $appUrl = rtrim(
            (string)(
                $_ENV['APP_URL']
                ?? 'https://permuok.com'
            ),
            '/'
        );

        $htmlBody =
            self::buildEmail(
                $title,
                $body,
                $score,
                $appUrl
            );

        $textBody =
            $title .
            "\n\n" .
            $body .
            "\n\n" .
            $appUrl;

        $notificationsCreated = 0;
        $emailsQueued = 0;

        foreach ($users as $user) {
            $userId =
                (int)$user['id'];

            $email = trim(
                (string)(
                    $user['email'] ?? ''
                )
            );

            $name = trim(
                (string)(
                    $user['first_name'] ?? ''
                ) .
                ' ' .
                (string)(
                    $user['last_name'] ?? ''
                )
            );

            $stNotification =
                $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        body,
                        related_type,
                        related_id
                    ) VALUES (
                        :user_id,
                        'high_match',
                        :title,
                        :body,
                        'compatibility',
                        :related_id
                    )
                ");

            $stNotification->execute([
                'user_id' =>
                    $userId,

                'title' =>
                    $title,

                'body' =>
                    $body,

                'related_id' =>
                    $compatibilityId,
            ]);

            $notificationsCreated++;

            if (
                $email !== '' &&
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                EmailJobService::enqueue(
                    $email,
                    'high_match',
                    $title,
                    $htmlBody,
                    $textBody,
                    $userId,
                    $name,
                    'compatibility',
                    $compatibilityId,
                    10
                );

                $emailsQueued++;
            }
        }

        return [
            'notifications_created' =>
                $notificationsCreated,

            'emails_queued' =>
                $emailsQueued,
        ];
    }

    private static function buildEmail(
        string $title,
        string $body,
        float $score,
        string $url
    ): string {
        $safeTitle =
            htmlspecialchars(
                $title,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeBody =
            htmlspecialchars(
                $body,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeUrl =
            htmlspecialchars(
                $url,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeScore =
            number_format(
                $score,
                0,
                ',',
                '.'
            );

        return <<<HTML
<!doctype html>
<html lang="es">
<body style="
    margin:0;
    padding:0;
    background:#f8fafc;
    font-family:Arial,Helvetica,sans-serif;
    color:#0f172a;
">
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center" style="padding:32px 16px;">

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        max-width:600px;
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:18px;
        overflow:hidden;
    "
>
<tr>
<td style="
    background:#0f172a;
    padding:24px 28px;
    color:#ffffff;
    font-size:22px;
    font-weight:700;
">
    Permuok
</td>
</tr>

<tr>
<td style="padding:32px 28px;">

<h1 style="
    margin:0 0 14px;
    font-size:25px;
">
    {$safeTitle}
</h1>

<div style="
    display:inline-block;
    margin-bottom:20px;
    padding:8px 14px;
    background:#dcfce7;
    color:#166534;
    border-radius:999px;
    font-weight:700;
">
    {$safeScore}% de compatibilidad
</div>

<p style="
    margin:0 0 26px;
    color:#475569;
    font-size:16px;
    line-height:1.6;
">
    {$safeBody}
</p>

<a
    href="{$safeUrl}"
    style="
        display:inline-block;
        background:#0f172a;
        color:#ffffff;
        text-decoration:none;
        padding:13px 20px;
        border-radius:10px;
        font-weight:700;
    "
>
    Ver oportunidad
</a>

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