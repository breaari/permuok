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

    /*
     * Banner creado en Canva para este email.
     */
    private const HERO_URL =
    'https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/09be0302d85b5e0ca0fe6067169c4a8a.png';

    private const FOOTER_URL =
    'https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/6f5235e46c309adb2404cb6f87578e95.png';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    private static function emailIconUrl(
        string $name
    ): string {
        $frontendUrl =
            rtrim(
                (string)(
                    $_ENV['FRONTEND_URL']
                    ?? 'https://permuok.com'
                ),
                '/'
            );

        return
            $frontendUrl .
            '/email-icons/' .
            $name .
            '.svg';
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

                    ORDER BY
                        occurred_at ASC,
                        id ASC

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
                                immediate_processed_at =
                                    NOW()

                            WHERE id = :id

                              AND immediate_processed_at
                                  IS NULL

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

                    p.property_type
                        AS property_type,

                    p.city
                        AS property_city,

                    p.zone
                        AS property_zone,

                    p.province
                        AS property_province,

                    p.country
                        AS property_country,

                    p.price
                        AS property_price,

                    p.currency
                        AS property_currency,

                    p.total_area
                        AS property_total_area,

                    p.covered_area
                        AS property_covered_area,

                    p.bedrooms
                        AS property_bedrooms,

                    p.bathrooms
                        AS property_bathrooms,

                    p.garages
                        AS property_garages,

                    pr.criteria_mode
                        AS requirement_criteria_mode,

                    pr.accepts_total_swap
                        AS requirement_accepts_total_swap,

                    pr.accepts_swap_plus_cash
                        AS requirement_accepts_swap_plus_cash,

                    pr.accepts_cash_only
                        AS requirement_accepts_cash_only,

                    pr.accepts_open_proposals
                        AS requirement_accepts_open_proposals,

                    pr.notes
                        AS requirement_notes,

                    sr.title
                        AS search_title,

                    sr.description
                        AS search_description,

                    sr.city
                        AS search_city,

                    sr.zone
                        AS search_zone,

                    sr.province
                        AS search_province,

                    sr.currency
                        AS search_currency,

                    sr.min_value
                        AS search_min_value,

                    sr.max_value
                        AS search_max_value,

                    sr.payment_mode_cash
                        AS search_payment_mode_cash,

                    sr.payment_mode_swap
                        AS search_payment_mode_swap,

                    (
                        SELECT
                            GROUP_CONCAT(
                                srpt.property_type
                                ORDER BY srpt.property_type
                                SEPARATOR ','
                            )

                        FROM search_request_property_types srpt

                        WHERE srpt.search_request_id =
                            sr.id
                    )
                        AS search_property_types

                FROM compatibilities c

                LEFT JOIN properties p
                    ON p.id = c.property_id

                   AND p.deleted_at IS NULL

                LEFT JOIN property_requirements pr
                    ON pr.property_id = p.id

                   AND pr.deleted_at IS NULL

                LEFT JOIN search_requests sr
                    ON sr.id =
                        c.search_request_id

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

            $isSearchOwner =
                $userRealEstateId > 0 &&
                $userRealEstateId ===
                $sourceRealEstateId;

            $isPropertyOwner =
                $userRealEstateId > 0 &&
                $userRealEstateId ===
                $targetRealEstateId;

            if ($isSearchOwner) {
                $descriptionMode =
                    'search';

                $notificationBody =
                    "Encontramos una propiedad con {$scoreText}% de compatibilidad con tu búsqueda.";
            } elseif ($isPropertyOwner) {
                $descriptionMode =
                    'property';

                $notificationBody =
                    "Encontramos una búsqueda con {$scoreText}% de compatibilidad con tu propiedad.";
            } else {
                $descriptionMode =
                    'generic';

                $notificationBody =
                    "Detectamos una compatibilidad de {$scoreText}% en una oportunidad de la red.";
            }

            /*
             * Notificación interna.
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
                'Encontramos una oportunidad con alto potencial',

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

            $htmlBody =
                self::buildEmail(
                    $compatibility,
                    $score,
                    $imageUrl,
                    $buttonUrl,
                    $descriptionMode
                );

            $textBody =
                self::buildTextBody(
                    $compatibility,
                    $score,
                    $buttonUrl,
                    $descriptionMode
                );

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

                WHERE property_id =
                    :property_id

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

    private static function propertyTypeLabel(
        ?string $value
    ): string {
        return match (trim(
            (string)$value
        )) {
            'house' =>
            'Casa',

            'apartment' =>
            'Departamento',

            'land' =>
            'Lote',

            'commercial' =>
            'Local',

            'office' =>
            'Oficina',

            'warehouse' =>
            'Depósito',

            'country_house' =>
            'Casa quinta',

            'ph' =>
            'PH',

            'garage' =>
            'Cochera',

            'hotel' =>
            'Hotel',

            'development' =>
            'Desarrollo',

            default =>
            trim(
                (string)$value
            ) !== ''
                ? ucfirst(
                    trim(
                        (string)$value
                    )
                )
                : 'Propiedad',
        };
    }

    private static function propertyTypesLabel(
        ?string $value
    ): string {
        $types =
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        (string)$value
                    )
                )
            );

        if ($types === []) {
            return 'Sin tipo definido';
        }

        return implode(
            ', ',
            array_map(
                static fn(string $type): string =>
                self::propertyTypeLabel(
                    $type
                ),
                $types
            )
        );
    }

    private static function formatMoney(
        mixed $value,
        ?string $currency = 'USD'
    ): string {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value) ||
            (float)$value <= 0
        ) {
            return '—';
        }

        $currency =
            strtoupper(
                trim(
                    (string)$currency
                )
            );

        if ($currency === '') {
            $currency = 'USD';
        }

        $amount =
            number_format(
                (float)$value,
                0,
                ',',
                '.'
            );

        return
            $currency .
            ' ' .
            $amount;
    }

    private static function formatMoneyRange(
        array $item
    ): string {
        $currency =
            trim(
                (string)(
                    $item['search_currency'] ?? 'USD'
                )
            );

        $min =
            $item['search_min_value'] ?? null;

        $max =
            $item['search_max_value'] ?? null;

        $hasMin =
            is_numeric($min) &&
            (float)$min > 0;

        $hasMax =
            is_numeric($max) &&
            (float)$max > 0;

        if ($hasMin && $hasMax) {
            return
                self::formatMoney(
                    $min,
                    $currency
                ) .
                ' - ' .
                self::formatMoney(
                    $max,
                    $currency
                );
        }

        if ($hasMin) {
            return
                'Desde ' .
                self::formatMoney(
                    $min,
                    $currency
                );
        }

        if ($hasMax) {
            return
                'Hasta ' .
                self::formatMoney(
                    $max,
                    $currency
                );
        }

        return 'Sin referencia';
    }

    private static function paymentLabel(
        array $item
    ): string {
        $cash =
            self::isTrue(
                $item['search_payment_mode_cash'] ?? false
            );

        $swap =
            self::isTrue(
                $item['search_payment_mode_swap'] ?? false
            );

        if ($cash && $swap) {
            return 'Dinero + permuta';
        }

        if ($cash) {
            return 'Solo dinero';
        }

        if ($swap) {
            return 'Solo permuta';
        }

        return 'Sin modalidad definida';
    }

    private static function isTrue(
        mixed $value
    ): bool {
        return
            $value === true ||
            $value === 1 ||
            $value === '1';
    }

    private static function exchangeSummary(
        array $item
    ): array {
        $mode =
            trim(
                (string)(
                    $item['requirement_criteria_mode'] ?? ''
                )
            );

        $acceptsTotalSwap =
            self::isTrue(
                $item['requirement_accepts_total_swap'] ?? false
            );

        $acceptsSwapPlusCash =
            self::isTrue(
                $item['requirement_accepts_swap_plus_cash'] ?? false
            );

        $acceptsCashOnly =
            self::isTrue(
                $item['requirement_accepts_cash_only'] ?? false
            );

        $acceptsOpenProposals =
            self::isTrue(
                $item['requirement_accepts_open_proposals'] ?? false
            );

        $eyebrow =
            'Condiciones de permuta';

        $title =
            'Sin especificar';

        if ($mode === 'criteria') {
            $eyebrow =
                'Busco con criterios';
        } elseif (
            $acceptsOpenProposals
        ) {
            $eyebrow =
                'Escucho propuestas';
        }

        if ($acceptsCashOnly) {
            $title =
                'Solo efectivo';
        } elseif (
            $acceptsSwapPlusCash &&
            $acceptsTotalSwap
        ) {
            $title =
                'Permuta total o parcial';
        } elseif (
            $acceptsSwapPlusCash
        ) {
            $title =
                'Permuta + diferencia';
        } elseif (
            $acceptsTotalSwap
        ) {
            $title =
                'Permuta total';
        } elseif (
            $acceptsOpenProposals
        ) {
            $title =
                'Abierto a propuestas';
        }

        $notes =
            trim(
                (string)(
                    $item['requirement_notes'] ?? ''
                )
            );

        if ($notes === '') {
            $notes =
                'Sin observaciones cargadas.';
        }

        return [
            'eyebrow' =>
            $eyebrow,

            'title' =>
            $title,

            'notes' =>
            $notes,
        ];
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
            ', ',
            $clean
        );
    }

    private static function safe(
        mixed $value
    ): string {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private static function buildPropertyCard(
        array $item,
        string $imageUrl
    ): string {
        $propertyType =
            self::safe(
                self::propertyTypeLabel(
                    $item['property_type'] ?? null
                )
            );

        $title =
            self::safe(
                trim(
                    (string)(
                        $item['property_title'] ?? ''
                    )
                ) ?: 'Propiedad'
            );

        $location =
            self::safe(
                self::joinLocation([
                    $item['property_city'] ?? null,

                    $item['property_zone'] ?? null,

                    $item['property_province'] ?? null,
                ])
            );

        $area =
            $item['property_total_area'] ??
            $item['property_covered_area'] ??
            null;

        $areaText =
            is_numeric($area) &&
            (float)$area > 0
            ? number_format(
                (float)$area,
                0,
                ',',
                '.'
            ) . ' m²'
            : '—';

        $bedrooms =
            self::safe(
                $item['property_bedrooms'] ?? '—'
            );

        $bathrooms =
            self::safe(
                $item['property_bathrooms'] ?? '—'
            );

        $garages =
            self::safe(
                $item['property_garages'] ?? '—'
            );

        $exchange =
            self::exchangeSummary(
                $item
            );

        $exchangeEyebrow =
            self::safe(
                $exchange['eyebrow']
            );

        $exchangeTitle =
            self::safe(
                $exchange['title']
            );

        $exchangeNotes =
            self::safe(
                $exchange['notes']
            );

        $safeImageUrl =
            self::safe(
                $imageUrl
            );

        $mapPinIcon =
            self::safe(
                self::emailIconUrl(
                    'map-pin'
                )
            );

        $rulerIcon =
            self::safe(
                self::emailIconUrl(
                    'ruler'
                )
            );

        $bedIcon =
            self::safe(
                self::emailIconUrl(
                    'bed'
                )
            );

        $bathIcon =
            self::safe(
                self::emailIconUrl(
                    'bath'
                )
            );

        $carIcon =
            self::safe(
                self::emailIconUrl(
                    'car'
                )
            );

        if ($safeImageUrl !== '') {
            $imageBlock = <<<HTML
<td
    width="210"
    valign="top"
    style="
        width:210px;
        padding:0;
        background:#f1f5f9;
    "
>
    <img
        src="{$safeImageUrl}"
        width="210"
        alt="{$title}"
        style="
            display:block;
            width:210px;
            height:230px;
            object-fit:cover;
            border:0;
        "
    />
</td>
HTML;
        } else {
            $imageBlock = <<<HTML
<td
    width="210"
    valign="middle"
    align="center"
    style="
        width:210px;
        height:230px;
        padding:0;
        background:#f1f5f9;
        color:#94a3b8;
        font-family:Arial, Helvetica, sans-serif;
        font-size:12px;
    "
>
    Sin imagen
</td>
HTML;
        }

        return <<<HTML
<table
    width="100%"
    border="0"
    cellpadding="0"
    cellspacing="0"
    role="presentation"
    style="
        width:100%;
        border:1px solid #e2e8f0;
        border-radius:12px;
        overflow:hidden;
        border-collapse:separate;
        background:#ffffff;
    "
>
<tbody>
<tr>

{$imageBlock}

<td
    valign="top"
    style="
        padding:20px 20px 16px;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <div
        style="
            color:#047857;
            font-size:9px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:0.8px;
        "
    >
        {$propertyType}
    </div>

    <div
        style="
            margin-top:7px;
            color:#0f172a;
            font-size:16px;
            font-weight:700;
            line-height:1.05;
            letter-spacing:-0.2px;
        "
    >
        {$title}
    </div>

    <table
        border="0"
        cellpadding="0"
        cellspacing="0"
        role="presentation"
        style="
            margin-top:11px;
        "
    >
    <tbody>
    <tr>
    <td
        valign="middle"
        style="
            padding:0 6px 0 0;
        "
    >
        <img
            src="{$mapPinIcon}"
            width="14"
            height="14"
            alt=""
            style="
                display:block;
                width:14px;
                height:14px;
                border:0;
            "
        />
    </td>

    <td
        valign="middle"
        style="
            color:#64748b;
            font-family:Manrope, Arial, Helvetica, sans-serif;
            font-size:11px;
            font-weight:400;
            line-height:1;
        "
    >
        {$location}
    </td>
    </tr>
    </tbody>
    </table>

    <table
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        role="presentation"
        style="
            width:100%;
            margin-top:14px;
            border-top:1px solid #e2e8f0;
            border-bottom:1px solid #e2e8f0;
        "
    >
    <tbody>
    <tr>

        <td
            style="
                padding:11px 10px 11px 0;
            "
        >
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                role="presentation"
            >
            <tbody>
            <tr>
            <td
                valign="middle"
                style="
                    padding-right:5px;
                "
            >
                <img
                    src="{$rulerIcon}"
                    width="14"
                    height="14"
                    alt=""
                    style="
                        display:block;
                        width:14px;
                        height:14px;
                    "
                />
            </td>
            <td
                valign="middle"
                style="
                    color:#475569;
                    font-family:Manrope, Arial, Helvetica, sans-serif;
                    font-size:11px;
                    font-weight:600;
                    line-height:1;
                    white-space:nowrap;
                "
            >
                {$areaText}
            </td>
            </tr>
            </tbody>
            </table>
        </td>

        <td
            style="
                padding:11px 10px;
            "
        >
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                role="presentation"
            >
            <tbody>
            <tr>
            <td
                valign="middle"
                style="
                    padding-right:5px;
                "
            >
                <img
                    src="{$bedIcon}"
                    width="14"
                    height="14"
                    alt=""
                    style="
                        display:block;
                        width:14px;
                        height:14px;
                    "
                />
            </td>
            <td
                valign="middle"
                style="
                    color:#475569;
                    font-family:Manrope, Arial, Helvetica, sans-serif;
                    font-size:11px;
                    font-weight:600;
                    line-height:1;
                    white-space:nowrap;
                "
            >
                {$bedrooms}
            </td>
            </tr>
            </tbody>
            </table>
        </td>

        <td
            style="
                padding:11px 10px;
            "
        >
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                role="presentation"
            >
            <tbody>
            <tr>
            <td
                valign="middle"
                style="
                    padding-right:5px;
                "
            >
                <img
                    src="{$bathIcon}"
                    width="14"
                    height="14"
                    alt=""
                    style="
                        display:block;
                        width:14px;
                        height:14px;
                    "
                />
            </td>
            <td
                valign="middle"
                style="
                    color:#475569;
                    font-family:Manrope, Arial, Helvetica, sans-serif;
                    font-size:11px;
                    font-weight:600;
                    line-height:1;
                    white-space:nowrap;
                "
            >
                {$bathrooms}
            </td>
            </tr>
            </tbody>
            </table>
        </td>

        <td
            style="
                padding:11px 0 11px 10px;
            "
        >
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                role="presentation"
            >
            <tbody>
            <tr>
            <td
                valign="middle"
                style="
                    padding-right:5px;
                "
            >
                <img
                    src="{$carIcon}"
                    width="14"
                    height="14"
                    alt=""
                    style="
                        display:block;
                        width:14px;
                        height:14px;
                    "
                />
            </td>
            <td
                valign="middle"
                style="
                    color:#475569;
                    font-family:Manrope, Arial, Helvetica, sans-serif;
                    font-size:11px;
                    font-weight:600;
                    line-height:1;
                    white-space:nowrap;
                "
            >
                {$garages}
            </td>
            </tr>
            </tbody>
            </table>
        </td>

    </tr>
    </tbody>
    </table>

    <div
        style="
            margin-top:12px;
            padding:12px;
            background:#f8fafc;
            border-left:3px solid #cbd5e1;
            border-radius:6px;
        "
    >
        <div
            style="
                color:#64748b;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:700;
                line-height:1;
                text-transform:uppercase;
                letter-spacing:0.6px;
            "
        >
            {$exchangeEyebrow}
        </div>

        <div
            style="
                margin-top:5px;
                color:#0f172a;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:11px;
                font-weight:700;
                line-height:1;
            "
        >
            {$exchangeTitle}
        </div>

        <div
            style="
                margin-top:5px;
                color:#64748b;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-style:italic;
                font-weight:400;
                line-height:1;
            "
        >
            {$exchangeNotes}
        </div>
    </div>
</td>
</tr>
</tbody>
</table>
HTML;
    }

    private static function buildSearchCard(
        array $item
    ): string {
        $id =
            (int)(
                $item['search_request_id'] ?? 0
            );

        $title =
            self::safe(
                trim(
                    (string)(
                        $item['search_title'] ?? ''
                    )
                ) ?: 'Búsqueda'
            );

        $description =
            self::safe(
                trim(
                    (string)(
                        $item['search_description'] ?? ''
                    )
                ) ?: 'Sin descripción.'
            );

        $location =
            self::safe(
                self::joinLocation([
                    $item['search_city'] ?? null,

                    $item['search_zone'] ?? null,

                    $item['search_province'] ?? null,
                ])
            );

        if ($location === '') {
            $location =
                'Sin ubicación';
        }

        $types =
            self::safe(
                self::propertyTypesLabel(
                    $item['search_property_types'] ?? null
                )
            );

        $payment =
            self::safe(
                self::paymentLabel(
                    $item
                )
            );

        $budget =
            self::safe(
                self::formatMoneyRange(
                    $item
                )
            );

        return <<<HTML
<table
    width="100%"
    border="0"
    cellpadding="0"
    cellspacing="0"
    role="presentation"
    style="
        width:100%;
        border:1px solid #e2e8f0;
        border-radius:12px;
        border-collapse:separate;
        background:#ffffff;
        overflow:hidden;
    "
>
<tbody>
<tr>

<td
    valign="top"
    style="
        width:38%;
        padding:17px 16px;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <div
        style="
            color:#94a3b8;
            font-size:9px;
            font-weight:600;
            line-height:1;
        "
    >
        #{$id}
    </div>

    <div
        style="
            margin-top:8px;
            color:#2166c2;
            font-size:14px;
            font-weight:700;
            line-height:1.05;
            letter-spacing:-0.1px;
        "
    >
        {$title}
    </div>

    <div
        style="
            margin-top:7px;
            color:#64748b;
            font-size:9px;
            font-weight:400;
            line-height:1;
        "
    >
        {$description}
    </div>
</td>

<td
    valign="top"
    style="
        width:23%;
        padding:17px 14px;
        border-left:1px solid #e2e8f0;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <div
        style="
            color:#94a3b8;
            font-size:8px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:0.6px;
        "
    >
        Ubicación
    </div>

    <div
        style="
            margin-top:7px;
            color:#334155;
            font-size:10px;
            font-weight:600;
            line-height:1.05;
        "
    >
        {$location}
    </div>
</td>

<td
    valign="top"
    style="
        width:18%;
        padding:17px 14px;
        border-left:1px solid #e2e8f0;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <div
        style="
            color:#94a3b8;
            font-size:8px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:0.6px;
        "
    >
        Tipo buscado
    </div>

    <div
        style="
            margin-top:7px;
            color:#334155;
            font-size:10px;
            font-weight:600;
            line-height:1.05;
        "
    >
        {$types}
    </div>

    <div
        style="
            margin-top:12px;
            color:#94a3b8;
            font-size:8px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:0.6px;
        "
    >
        Modalidad
    </div>

    <div
        style="
            margin-top:6px;
            color:#334155;
            font-size:10px;
            font-weight:600;
            line-height:1.05;
        "
    >
        {$payment}
    </div>
</td>

<td
    valign="top"
    style="
        width:21%;
        padding:17px 14px;
        border-left:1px solid #e2e8f0;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <div
        style="
            color:#94a3b8;
            font-size:8px;
            font-weight:700;
            line-height:1;
            text-transform:uppercase;
            letter-spacing:0.6px;
        "
    >
        Rango de valor
    </div>

    <div
        style="
            margin-top:7px;
            color:#2166c2;
            font-size:11px;
            font-weight:700;
            line-height:1.05;
        "
    >
        {$budget}
    </div>
</td>

</tr>
</tbody>
</table>
HTML;
    }

    private static function buildEmail(
        array $item,
        float $score,
        string $imageUrl,
        string $buttonUrl,
        string $descriptionMode
    ): string {
        $safeScore =
            number_format(
                $score,
                0,
                ',',
                '.'
            );

        $safeButtonUrl =
            self::safe(
                $buttonUrl
            );

        $propertyCard =
            self::buildPropertyCard(
                $item,
                $imageUrl
            );

        $searchCard =
            self::buildSearchCard(
                $item
            );

        if ($descriptionMode === 'search') {
            $descriptionHtml =
                'Detectamos una alta compatibilidad entre esta propiedad<br />y <strong>tu búsqueda</strong> en la red.';
        } elseif (
            $descriptionMode === 'property'
        ) {
            $descriptionHtml =
                'Detectamos una alta compatibilidad entre <strong>tu propiedad</strong><br />y esta búsqueda de la red.';
        } else {
            $descriptionHtml =
                'Detectamos una alta compatibilidad entre esta propiedad<br />y esta búsqueda de la red.';
        }

        $logoUrl =
            self::LOGO_URL;

        $heroUrl =
            self::HERO_URL;

        $footerUrl =
            self::FOOTER_URL;

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
                font-size:58px !important;
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
        background-color:#f0f1f5;
        padding:24px 12px;
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
        padding:24px 24px 16px;
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

<!-- HERO CANVA -->
<tr>
<td
    class="email-content"
    align="center"
    style="
        padding:0 24px 16px;
    "
>
    <img
        src="{$heroUrl}"
        width="552"
        alt="Encontramos una oportunidad con alto potencial"
        style="
            display:block;
            width:552px;
            height:auto;
            max-width:100%;
            border:0;
            border-radius:12px;
        "
    />
</td>
</tr>

<!-- SCORE -->
<tr>
<td
    class="email-content"
    align="center"
    style="
        padding:28px 24px 10px;
        font-family:Arial, Helvetica, sans-serif;
    "
>
    <div
        class="match-score"
        style="
            color:#61b228;
            font-size:66px;
            font-weight:700;
            line-height:1;
        "
    >
        {$safeScore}%
    </div>

    <div
        style="
            margin-top:4px;
            color:#0f172a;
            font-size:26px;
            font-weight:400;
            line-height:1;
        "
    >
        de compatibilidad
    </div>
</td>
</tr>

<!-- ESPACIO SCORE / PROPERTY -->
<tr>
<td
    height="24"
    style="
        height:24px;
        font-size:0;
        line-height:0;
    "
>
    &nbsp;
</td>
</tr>

<!-- PROPERTY CARD -->
<tr>
<td
    class="email-content"
    style="
        padding:0 24px 16px;
    "
>
    {$propertyCard}
</td>
</tr>

<!-- DESCRIPTION -->
<tr>
<td
    class="email-content"
    align="center"
    style="
        padding:24px 24px 28px;
        color:#30363d;
        font-family:Arial, Helvetica, sans-serif;
        font-size:20px;
        font-weight:400;
        line-height:1;
        text-align:center;
    "
>
    {$descriptionHtml}
</td>
</tr>

<!-- SEARCH CARD -->
<tr>
<td
    class="email-content"
    style="
        padding:0 24px 16px;
    "
>
    {$searchCard}
</td>
</tr>

<!-- SPACER -->
<tr>
<td
    height="20"
    style="
        height:20px;
        font-size:0;
        line-height:0;
    "
>
    &nbsp;
</td>
</tr>

<!-- BUTTON -->
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

    private static function buildTextBody(
        array $item,
        float $score,
        string $buttonUrl,
        string $descriptionMode
    ): string {
        $scoreText =
            number_format(
                $score,
                0,
                ',',
                '.'
            );

        $propertyTitle =
            trim(
                (string)(
                    $item['property_title'] ?? ''
                )
            );

        $searchTitle =
            trim(
                (string)(
                    $item['search_title'] ?? ''
                )
            );

        if ($descriptionMode === 'search') {
            $description =
                'Detectamos una alta compatibilidad entre esta propiedad y tu búsqueda en la red.';
        } elseif (
            $descriptionMode === 'property'
        ) {
            $description =
                'Detectamos una alta compatibilidad entre tu propiedad y esta búsqueda de la red.';
        } else {
            $description =
                'Detectamos una alta compatibilidad entre esta propiedad y esta búsqueda de la red.';
        }

        return
            "Encontramos una oportunidad con alto potencial\n\n" .
            "{$scoreText}% de compatibilidad\n\n" .
            $propertyTitle .
            "\n\n" .
            $description .
            "\n\n" .
            $searchTitle .
            "\n\n" .
            "Ver compatibilidad:\n" .
            $buttonUrl;
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

        /*
         * Datos simulados con la misma estructura
         * utilizada por el email productivo.
         */
        $item = [
            'property_title' =>
            'Departamento 4 ambientes reciclado en Centro',

            'property_type' =>
            'apartment',

            'property_city' =>
            'Mar del Plata',

            'property_zone' =>
            'Centro',

            'property_province' =>
            'Buenos Aires',

            'property_total_area' =>
            100,

            'property_bedrooms' =>
            3,

            'property_bathrooms' =>
            2,

            'property_garages' =>
            1,

            'requirement_criteria_mode' =>
            '',

            'requirement_accepts_total_swap' =>
            1,

            'requirement_accepts_swap_plus_cash' =>
            0,

            'requirement_accepts_cash_only' =>
            0,

            'requirement_accepts_open_proposals' =>
            0,

            'requirement_notes' =>
            'Acepta casa mayor valor',

            'search_request_id' =>
            12,

            'search_title' =>
            'Busco depto en MDP - TEST MULTIBIEN',

            'search_description' =>
            'Depto en zona centro',

            'search_city' =>
            'Mar del Plata',

            'search_zone' =>
            'General Pueyrredón',

            'search_province' =>
            'Buenos Aires',

            'search_currency' =>
            'USD',

            'search_min_value' =>
            null,

            'search_max_value' =>
            null,

            'search_payment_mode_cash' =>
            1,

            'search_payment_mode_swap' =>
            1,

            'search_property_types' =>
            'apartment',
        ];

        $imageUrl =
            'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=85';

        $buttonUrl =
            $frontendUrl .
            '/compatibilities/1';

        $score =
            94.0;

        $htmlBody =
            self::buildEmail(
                $item,
                $score,
                $imageUrl,
                $buttonUrl,
                $mode
            );

        $textBody =
            self::buildTextBody(
                $item,
                $score,
                $buttonUrl,
                $mode
            );

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
