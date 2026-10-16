<?php

namespace App\Services;

use Exception;
use PDO;
use Throwable;

class HighMatchNotificationService
{
    private const MIN_SCORE_TO_ALERT = 90.0;
    private const BATCH_SIZE = 100;

    private const LOGO_URL =
    'https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/1c019e1200d10d5a9c00bf196125b1c2.png';

    private const FOOTER_URL =
    'https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/6f5235e46c309adb2404cb6f87578e95.png';

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

                $events =
                    $st->fetchAll(
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

                    if (
                        !empty($event['payload_json'])
                    ) {
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
                        (float)(
                            $payload['score']
                            ?? 0
                        );

                    /*
                     * Todos los eventos quedan marcados
                     * como procesados aunque no alcancen
                     * el umbral de alerta inmediata.
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

                    $stDone =
                        $pdo->prepare("
                            UPDATE match_events
                            SET
                                immediate_processed_at = NOW()
                            WHERE id = :id
                              AND immediate_processed_at IS NULL
                            LIMIT 1
                        ");

                    $stDone->execute([
                        'id' =>
                        $eventId,
                    ]);

                    $eventsProcessed++;
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if (
                    $pdo->inTransaction()
                ) {
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
        $st =
            $pdo->prepare("
                SELECT
                    c.id,
                    c.property_id,
                    c.search_request_id,
                    c.source_real_estate_id,
                    c.target_real_estate_id,

                    p.title
                        AS property_title,

                    p.city
                        AS property_city,

                    p.zone
                        AS property_zone,

                    p.province
                        AS property_province,

                    p.country
                        AS property_country,

                    sr.title
                        AS search_title

                FROM compatibilities c

                LEFT JOIN properties p
                    ON p.id = c.property_id
                   AND p.deleted_at IS NULL

                LEFT JOIN search_requests sr
                    ON sr.id = c.search_request_id
                   AND sr.deleted_at IS NULL

                WHERE c.id = :id
                  AND c.deleted_at IS NULL

                LIMIT 1
            ");

        $st->execute([
            'id' =>
            $compatibilityId,
        ]);

        $compatibility =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$compatibility) {
            return [
                'notifications_created' =>
                0,

                'emails_queued' =>
                0,
            ];
        }

        $sourceRealEstateId =
            (int)(
                $compatibility['source_real_estate_id'] ?? 0
            );

        $targetRealEstateId =
            (int)(
                $compatibility['target_real_estate_id'] ?? 0
            );

        $realEstateIds =
            array_values(
                array_unique(
                    array_filter([
                        $sourceRealEstateId,
                        $targetRealEstateId,
                    ])
                )
            );

        if ($realEstateIds === []) {
            return [
                'notifications_created' =>
                0,

                'emails_queued' =>
                0,
            ];
        }

        $placeholders = [];
        $params = [];

        foreach (
            $realEstateIds
            as $index => $realEstateId
        ) {
            $key =
                'real_estate_' .
                $index;

            $placeholders[] =
                ':' . $key;

            $params[$key] =
                $realEstateId;
        }

        $stUsers =
            $pdo->prepare("
                SELECT
                    id,
                    real_estate_id,
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

        $stUsers->execute(
            $params
        );

        $users =
            $stUsers->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        if ($users === []) {
            return [
                'notifications_created' =>
                0,

                'emails_queued' =>
                0,
            ];
        }

        $propertyTitle =
            trim(
                (string)(
                    $compatibility['property_title'] ?? ''
                )
            );

        if ($propertyTitle === '') {
            $propertyTitle =
                'Oportunidad inmobiliaria';
        }

        $searchTitle =
            trim(
                (string)(
                    $compatibility['search_title'] ?? ''
                )
            );

        $location =
            self::joinLocation([
                $compatibility['property_zone'] ?? null,

                $compatibility['property_city'] ?? null,

                $compatibility['property_province'] ?? null,

                $compatibility['property_country'] ?? null,
            ]);

        $propertyId =
            (int)(
                $compatibility['property_id'] ?? 0
            );

        $imageUrl =
            self::getPropertyImageUrl(
                $pdo,
                $propertyId
            );

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
            '/compatibilities/' .
            $compatibilityId;

        $heading =
            'Encontramos una oportunidad con alto potencial';

        $emailSubject =
            'Encontramos una oportunidad con alta compatibilidad';

        $scoreText =
            number_format(
                $score,
                0,
                ',',
                '.'
            );

        $notificationsCreated = 0;
        $emailsQueued = 0;

        foreach ($users as $user) {
            $userId =
                (int)(
                    $user['id']
                    ?? 0
                );

            $userRealEstateId =
                (int)(
                    $user['real_estate_id']
                    ?? 0
                );

            if ($userId <= 0) {
                continue;
            }

            /*
             * source_real_estate_id:
             * inmobiliaria dueña de la búsqueda.
             *
             * target_real_estate_id:
             * inmobiliaria dueña de la propiedad.
             */
            $isSearchOwner =
                $userRealEstateId > 0 &&
                $userRealEstateId ===
                $sourceRealEstateId;

            $isPropertyOwner =
                $userRealEstateId > 0 &&
                $userRealEstateId ===
                $targetRealEstateId;

            if ($isSearchOwner) {
                $description =
                    'Detectamos una alta compatibilidad entre esta propiedad y tu búsqueda en la red.';

                $notificationBody =
                    "Encontramos una propiedad con {$scoreText}% de compatibilidad con tu búsqueda.";

                $propertyLabel =
                    'Propiedad compatible';

                $searchLabel =
                    'Tu búsqueda';

                $descriptionMode =
                    'search';
            } elseif ($isPropertyOwner) {
                $description =
                    'Detectamos una alta compatibilidad entre tu propiedad y una búsqueda de la red.';

                $notificationBody =
                    "Encontramos una búsqueda con {$scoreText}% de compatibilidad con tu propiedad.";

                $propertyLabel =
                    'Tu propiedad';

                $searchLabel =
                    'Búsqueda compatible';

                $descriptionMode =
                    'property';
            } else {
                /*
                 * Fallback defensivo.
                 */
                $description =
                    'Detectamos una alta compatibilidad entre esta propiedad y una búsqueda de la red.';

                $notificationBody =
                    "Detectamos una compatibilidad de {$scoreText}% en una oportunidad de la red.";

                $propertyLabel =
                    'Propiedad';

                $searchLabel =
                    'Búsqueda compatible';

                $descriptionMode =
                    'generic';
            }

            /*
             * Notificación interna personalizada.
             */
            $stNotification =
                $pdo->prepare("
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
                $heading,

                'body' =>
                $notificationBody,

                'related_id' =>
                $compatibilityId,
            ]);

            $notificationsCreated++;

            $email =
                trim(
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

            $name =
                trim(
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

            /*
             * El HTML se genera por usuario porque
             * el mensaje cambia según de qué lado
             * de la compatibilidad se encuentre.
             */
            $htmlBody =
                self::buildEmail(
                    $heading,
                    $propertyTitle,
                    $location,
                    $searchTitle,
                    $score,
                    $imageUrl,
                    $buttonUrl,
                    $description,
                    $propertyLabel,
                    $searchLabel,
                    $descriptionMode
                );

            $textBody =
                $heading .
                "\n\n" .
                $scoreText .
                "% de compatibilidad\n\n" .
                $description .
                "\n\n" .
                $propertyLabel .
                ":\n" .
                $propertyTitle;

            if ($location !== '') {
                $textBody .=
                    "\n" .
                    $location;
            }

            if ($searchTitle !== '') {
                $textBody .=
                    "\n\n" .
                    $searchLabel .
                    ":\n" .
                    $searchTitle;
            }

            $textBody .=
                "\n\nVer compatibilidad:\n" .
                $buttonUrl;

            EmailJobService::enqueue(
                $email,
                'high_match',
                $emailSubject,
                $htmlBody,
                $textBody,
                $userId,
                $name,
                'compatibility',
                $compatibilityId,
                10,
                $pdo
            );

            $emailsQueued++;
        }

        return [
            'notifications_created' =>
            $notificationsCreated,

            'emails_queued' =>
            $emailsQueued,
        ];
    }

    private static function getPropertyImageUrl(
        PDO $pdo,
        int $propertyId
    ): string {
        if ($propertyId <= 0) {
            return '';
        }

        $st =
            $pdo->prepare("
                SELECT id

                FROM property_images

                WHERE property_id = :property_id

                  AND deleted_at IS NULL

                ORDER BY
                    is_cover DESC,
                    sort_order ASC,
                    id ASC

                LIMIT 1
            ");

        $st->execute([
            'property_id' =>
            $propertyId,
        ]);

        $imageId =
            (int)(
                $st->fetchColumn()
                ?: 0
            );

        if ($imageId <= 0) {
            return '';
        }

        /*
         * La firma puede durar como máximo
         * 24 horas según ImageUrlSignatureService.
         */
        $path =
            ImageUrlSignatureService::generate(
                'property',
                $imageId,
                24 * 60 * 60
            );

        return self::absoluteImageUrl(
            $path
        );
    }

    private static function absoluteImageUrl(
        string $url
    ): string {
        $url =
            trim(
                $url
            );

        if ($url === '') {
            return '';
        }

        if (
            preg_match(
                '#^https?://#i',
                $url
            )
        ) {
            return $url;
        }

        $backendUrl =
            rtrim(
                (string)(
                    $_ENV['BACKEND_URL']
                    ?? $_ENV['APP_URL']
                    ?? ''
                ),
                '/'
            );

        if ($backendUrl === '') {
            return $url;
        }

        return
            $backendUrl .
            (
                str_starts_with(
                    $url,
                    '/'
                )
                ? $url
                : '/' . $url
            );
    }

    private static function joinLocation(
        array $parts
    ): string {
        $clean = [];

        foreach ($parts as $part) {
            $value =
                trim(
                    (string)$part
                );

            if (
                $value !== '' &&
                !in_array(
                    $value,
                    $clean,
                    true
                )
            ) {
                $clean[] =
                    $value;
            }
        }

        return implode(
            ' · ',
            $clean
        );
    }

    private static function buildEmail(
        string $heading,
        string $propertyTitle,
        string $location,
        string $searchTitle,
        float $score,
        string $imageUrl,
        string $buttonUrl,
        string $description,
        string $propertyLabel,
        string $searchLabel,
        string $descriptionMode
    ): string {
        $safeHeading =
            htmlspecialchars(
                $heading,
                ENT_QUOTES,
                'UTF-8'
            );

        $safePropertyTitle =
            htmlspecialchars(
                $propertyTitle,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeLocation =
            htmlspecialchars(
                $location,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeSearchTitle =
            htmlspecialchars(
                $searchTitle,
                ENT_QUOTES,
                'UTF-8'
            );

        $safePropertyLabel =
            htmlspecialchars(
                $propertyLabel,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeSearchLabel =
            htmlspecialchars(
                $searchLabel,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeImageUrl =
            htmlspecialchars(
                $imageUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeButtonUrl =
            htmlspecialchars(
                $buttonUrl,
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

        /*
         * Personalizamos la frase y resaltamos
         * visualmente el contenido propio.
         */
        if ($descriptionMode === 'search') {
            $descriptionHtml =
                'Detectamos una alta compatibilidad entre esta propiedad y <strong>tu búsqueda</strong> en la red.';
        } elseif ($descriptionMode === 'property') {
            $descriptionHtml =
                'Detectamos una alta compatibilidad entre <strong>tu propiedad</strong> y una búsqueda de la red.';
        } else {
            $descriptionHtml =
                htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    'UTF-8'
                );
        }

        $logoUrl =
            self::LOGO_URL;

        $footerUrl =
            self::FOOTER_URL;

        if ($safeLocation !== '') {
            $locationBlock = <<<HTML
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
HTML;
        } else {
            $locationBlock = '';
        }

        if ($safeImageUrl !== '') {
            $propertyCard = <<<HTML
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
        alt="{$safePropertyTitle}"
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
            color:#3974ba;
            font-size:11px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
        "
    >
        {$safePropertyLabel}
    </div>

    <div
        style="
            margin-top:9px;
            color:#0e1b10;
            font-size:16px;
            font-weight:700;
            line-height:1;
        "
    >
        {$safePropertyTitle}
    </div>

    {$locationBlock}
</td>
</tr>
</tbody>
</table>
HTML;
        } else {
            $propertyCard = <<<HTML
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
            color:#3974ba;
            font-size:11px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
        "
    >
        {$safePropertyLabel}
    </div>

    <div
        style="
            margin-top:9px;
            color:#0e1b10;
            font-size:16px;
            font-weight:700;
            line-height:1;
        "
    >
        {$safePropertyTitle}
    </div>

    {$locationBlock}
</td>
</tr>
</tbody>
</table>
HTML;
        }

        if ($safeSearchTitle !== '') {
            $searchBlock = <<<HTML
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 30px;
    "
>
    <table
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        role="presentation"
        style="
            background:#f3f4f6;
            border-radius:10px;
        "
    >
    <tbody>
    <tr>
    <td
        style="
            padding:18px 20px;
            font-family:Arial, Helvetica, sans-serif;
        "
    >
        <div
            style="
                color:#64748b;
                font-size:12px;
                font-weight:700;
                line-height:1;
            "
        >
            {$safeSearchLabel}
        </div>

        <div
            style="
                margin-top:8px;
                color:#30363d;
                font-size:15px;
                font-weight:700;
                line-height:1;
            "
        >
            {$safeSearchTitle}
        </div>
    </td>
    </tr>
    </tbody>
    </table>
</td>
</tr>
HTML;
        } else {
            $searchBlock = '';
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

            .match-score {
                font-size:46px !important;
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
        src="{$logoUrl}"
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
        padding:0 56px 22px;
        color:#0e1b10;
        font-family:Arial, Helvetica, sans-serif;
        font-size:20px;
        font-weight:700;
        line-height:1;
        text-align:left;
    "
>
    {$safeHeading}
</td>
</tr>

<!-- SCORE -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 28px;
    "
>
    <table
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        role="presentation"
        style="
            background:#f3f8ed;
            border:1px solid #d9eac8;
            border-radius:12px;
        "
    >
    <tbody>
    <tr>
    <td
        align="center"
        style="
            padding:28px 20px 26px;
            font-family:Arial, Helvetica, sans-serif;
        "
    >
        <div
            class="match-score"
            style="
                color:#61b228;
                font-size:56px;
                font-weight:800;
                line-height:1;
                letter-spacing:-2px;
            "
        >
            {$safeScore}%
        </div>

        <div
            style="
                margin-top:9px;
                color:#30363d;
                font-size:15px;
                font-weight:700;
                line-height:1;
            "
        >
            de compatibilidad
        </div>

        <div
            style="
                margin-top:10px;
                color:#64748b;
                font-size:12px;
                font-weight:400;
                line-height:1;
            "
        >
            Match de alto potencial detectado por Permuok
        </div>
    </td>
    </tr>
    </tbody>
    </table>
</td>
</tr>

<!-- PROPIEDAD -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 26px;
    "
>
    {$propertyCard}
</td>
</tr>

<!-- DESCRIPCIÓN -->
<tr>
<td
    class="email-content"
    style="
        padding:0 56px 26px;
        color:#30363d;
        font-family:Arial, Helvetica, sans-serif;
        font-size:15px;
        font-weight:400;
        line-height:1;
        text-align:left;
    "
>
    {$descriptionHtml}
</td>
</tr>

<!-- BÚSQUEDA -->
{$searchBlock}

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
        Ver compatibilidad
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
        Ver compatibilidad
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
        src="{$footerUrl}"
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

    public static function queueTestEmail(
        string $email,
        string $mode = 'search'
    ): array {
        $email =
            strtolower(
                trim(
                    $email
                )
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

        $mode =
            strtolower(
                trim(
                    $mode
                )
            );

        if (
            !in_array(
                $mode,
                [
                    'search',
                    'property',
                ],
                true
            )
        ) {
            throw new Exception(
                'El modo debe ser search o property.'
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

        $heading =
            'Encontramos una oportunidad con alto potencial';

        $propertyTitle =
            'Departamento 3 ambientes en Güemes';

        $location =
            'Güemes · Mar del Plata';

        $searchTitle =
            'Departamento de 2 o 3 ambientes con posibilidad de permuta';

        $score =
            94.0;

        $imageUrl =
            'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=500&q=80';

        $buttonUrl =
            $frontendUrl .
            '/compatibilities/1';

        if ($mode === 'search') {
            $description =
                'Detectamos una alta compatibilidad entre esta propiedad y tu búsqueda en la red.';

            $propertyLabel =
                'Propiedad compatible';

            $searchLabel =
                'Tu búsqueda';
        } else {
            $description =
                'Detectamos una alta compatibilidad entre tu propiedad y una búsqueda de la red.';

            $propertyLabel =
                'Tu propiedad';

            $searchLabel =
                'Búsqueda compatible';
        }

        $htmlBody =
            self::buildEmail(
                $heading,
                $propertyTitle,
                $location,
                $searchTitle,
                $score,
                $imageUrl,
                $buttonUrl,
                $description,
                $propertyLabel,
                $searchLabel,
                $mode
            );

        $textBody =
            $heading .
            "\n\n" .
            "94% de compatibilidad\n\n" .
            $description .
            "\n\n" .
            $propertyLabel .
            ":\n" .
            $propertyTitle .
            "\n" .
            $location .
            "\n\n" .
            $searchLabel .
            ":\n" .
            $searchTitle .
            "\n\n" .
            "Ver compatibilidad:\n" .
            $buttonUrl;

        return EmailJobService::enqueue(
            $email,
            'high_match_test',
            'Encontramos una oportunidad con alta compatibilidad',
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
