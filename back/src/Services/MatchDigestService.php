<?php

namespace App\Services;

use PDO;
use Throwable;

class MatchDigestService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function process(): array
    {
        $pdo = self::db();

        $events =
            self::getPendingEvents(
                $pdo
            );

        if ($events === []) {
            return [
                'events_processed' => 0,
                'notifications_created' => 0,
                'emails_queued' => 0,
                'real_estates_notified' => 0,
            ];
        }

        $byRealEstate = [];

        foreach ($events as $event) {
            $realEstateIds =
                self::resolveRealEstates(
                    $pdo,
                    $event
                );

            foreach (
                $realEstateIds
                as $realEstateId
            ) {
                if (
                    !isset(
                        $byRealEstate[$realEstateId]
                    )
                ) {
                    $byRealEstate[$realEstateId] = [
                        'direct_new' => 0,
                        'direct_changed' => 0,
                        'direct_lost' => 0,
                        'multilateral_new' => 0,
                        'multilateral_changed' => 0,
                        'multilateral_lost' => 0,
                    ];
                }

                $type =
                    (string)$event['event_type'];

                if (
                    array_key_exists(
                        $type,
                        $byRealEstate[$realEstateId]
                    )
                ) {
                    $byRealEstate[$realEstateId][$type]++;
                }
            }
        }

        $notificationsCreated = 0;
        $emailsQueued = 0;
        $pdo->beginTransaction();

        try {
            foreach (
                $byRealEstate
                as $realEstateId =>
                $counts
            ) {
                /*
                 * Si no hay novedades positivas,
                 * no generamos una notificación
                 * diaria innecesaria.
                 */
                $newDirect =
                    $counts['direct_new'];

                $newMultilateral =
                    $counts['multilateral_new'];

                $changed =
                    $counts['direct_changed'] +
                    $counts['multilateral_changed'];

                $lost =
                    $counts['direct_lost'] +
                    $counts['multilateral_lost'];

                if (
                    $newDirect === 0 &&
                    $newMultilateral === 0 &&
                    $changed === 0
                ) {
                    continue;
                }

                $users =
                    self::getRecipientUsers(
                        $pdo,
                        (int)$realEstateId
                    );

                if ($users === []) {
                    continue;
                }

                $totalNew =
                    $newDirect +
                    $newMultilateral;

                $title =
                    $totalNew > 0
                    ? (
                        $totalNew === 1
                        ? 'Tenés una nueva oportunidad'
                        : "Tenés {$totalNew} nuevas oportunidades"
                    )
                    : 'Tus oportunidades se actualizaron';

                $parts = [];

                if ($newDirect > 0) {
                    $parts[] =
                        "{$newDirect} " .
                        (
                            $newDirect === 1
                            ? 'match directo'
                            : 'matches directos'
                        );
                }

                if ($newMultilateral > 0) {
                    $parts[] =
                        "{$newMultilateral} " .
                        (
                            $newMultilateral === 1
                            ? 'oportunidad multilateral'
                            : 'oportunidades multilaterales'
                        );
                }

                if ($changed > 0) {
                    $parts[] =
                        "{$changed} actualizadas";
                }

                if ($lost > 0) {
                    $parts[] =
                        "{$lost} dejaron de estar disponibles";
                }

                $body =
                    implode(
                        ' · ',
                        $parts
                    );

                foreach ($users as $user) {
                    $userId =
                        (int)$user['id'];

                    self::insertNotification(
                        $pdo,
                        $userId,
                        $title,
                        $body
                    );

                    $notificationsCreated++;

                    $email = trim(
                        (string)(
                            $user['email']
                            ?? ''
                        )
                    );

                    if (
                        $email === '' ||
                        !filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                    ) {
                        continue;
                    }

                    $name = trim(
                        (string)(
                            $user['first_name']
                            ?? ''
                        ) .
                            ' ' .
                            (string)(
                                $user['last_name']
                                ?? ''
                            )
                    );

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
                            $appUrl
                        );

                    $textBody =
                        $title .
                        "\n\n" .
                        $body .
                        "\n\n" .
                        $appUrl;

                    EmailJobService::enqueue(
                        $email,
                        'match_daily_digest',
                        $title,
                        $htmlBody,
                        $textBody,
                        $userId,
                        $name,
                        null,
                        null,
                        5
                    );

                    $emailsQueued++;
                }
            }

            $eventIds =
                array_map(
                    static fn(array $event): int =>
                    (int)$event['id'],
                    $events
                );

            self::markProcessed(
                $pdo,
                $eventIds
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'events_processed' =>
            count($events),

            'notifications_created' =>
            $notificationsCreated,

            'emails_queued' =>
            $emailsQueued,

            'real_estates_notified' =>
            count($byRealEstate),
        ];
    }

    private static function getPendingEvents(
        PDO $pdo
    ): array {
        $st = $pdo->query("
            SELECT
                id,
                event_type,
                entity_type,
                entity_id

            FROM match_events

            WHERE digest_processed_at
                IS NULL

            ORDER BY
                occurred_at ASC,
                id ASC
        ");

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function resolveRealEstates(
        PDO $pdo,
        array $event
    ): array {
        $entityType =
            (string)$event['entity_type'];

        $entityId =
            (int)$event['entity_id'];

        if ($entityType === 'compatibility') {
            $st = $pdo->prepare("
                SELECT
                    source_real_estate_id,
                    target_real_estate_id

                FROM compatibilities

                WHERE id = :id

                LIMIT 1
            ");

            $st->execute([
                'id' => $entityId,
            ]);

            $row =
                $st->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$row) {
                return [];
            }

            return self::cleanIds([
                $row['source_real_estate_id'] ?? null,

                $row['target_real_estate_id'] ?? null,
            ]);
        }

        if (
            $entityType ===
            'multilateral_operation'
        ) {
            $st = $pdo->prepare("
                SELECT DISTINCT
                    source_real_estate_id

                FROM multilateral_operation_legs

                WHERE operation_id =
                    :operation_id
            ");

            $st->execute([
                'operation_id' =>
                $entityId,
            ]);

            return self::cleanIds(
                $st->fetchAll(
                    PDO::FETCH_COLUMN
                ) ?: []
            );
        }

        return [];
    }

    private static function getRecipientUsers(
        PDO $pdo,
        int $realEstateId
    ): array {
        $st = $pdo->prepare("
        SELECT
            id,
            first_name,
            last_name,
            email

        FROM users

        WHERE real_estate_id =
            :real_estate_id

          AND is_active = 1

          AND deleted_at IS NULL

          AND role IN (2, 3)

        ORDER BY id ASC
    ");

        $st->execute([
            'real_estate_id' =>
            $realEstateId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function insertNotification(
        PDO $pdo,
        int $userId,
        string $title,
        string $body
    ): void {
        $st = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                body,
                related_type,
                related_id
            ) VALUES (
                :user_id,
                'match_daily_digest',
                :title,
                :body,
                NULL,
                NULL
            )
        ");

        $st->execute([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
        ]);
    }

    private static function markProcessed(
        PDO $pdo,
        array $ids
    ): void {
        if ($ids === []) {
            return;
        }

        $placeholders = [];
        $params = [];

        foreach (
            array_values($ids)
            as $index => $id
        ) {
            $key =
                'event_' . $index;

            $placeholders[] =
                ':' . $key;

            $params[$key] =
                (int)$id;
        }

        $st = $pdo->prepare("
            UPDATE match_events

            SET digest_processed_at =
                NOW()

            WHERE id IN (
                " .
            implode(
                ', ',
                $placeholders
            ) .
            "
            )

              AND digest_processed_at
                  IS NULL
        ");

        $st->execute($params);
    }

    private static function buildEmail(
        string $title,
        string $body,
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

<p style="
    margin:0 0 8px;
    color:#64748b;
    font-size:13px;
    font-weight:700;
    text-transform:uppercase;
">
    Resumen de oportunidades
</p>

<h1 style="
    margin:0 0 18px;
    font-size:25px;
">
    {$safeTitle}
</h1>

<p style="
    margin:0 0 26px;
    color:#475569;
    font-size:16px;
    line-height:1.7;
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
    Ver oportunidades
</a>

</td>
</tr>

<tr>
<td style="
    border-top:1px solid #e2e8f0;
    padding:20px 28px;
    color:#94a3b8;
    font-size:12px;
">
    Resumen diario de actividad de Permuok.
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

    private static function cleanIds(
        array $ids
    ): array {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $ids
                    ),
                    static fn(int $id): bool =>
                    $id > 0
                )
            )
        );
    }
}
