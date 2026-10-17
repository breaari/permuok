<?php

namespace App\Services;

use PDO;

class EmailDevelopmentCardService
{
    public static function build(
        PDO $pdo,
        int $developmentId,
        ?string $imageUrl = null
    ): ?string {
        if ($developmentId <= 0) {
            return null;
        }

        $stmt =
            $pdo->prepare("
                SELECT
                    d.id,
                    d.title,
                    d.city,
                    d.zone,
                    d.province,
                    d.development_stage,
                    d.price_from,
                    d.price_to,
                    d.currency,
                    d.total_units,
                    d.available_units,
                    d.delivery_date_estimated,

                    (
                        SELECT COUNT(*)

                        FROM development_unit_types dut

                        WHERE dut.development_id = d.id
                          AND dut.deleted_at IS NULL
                    )
                        AS unit_types_count

                FROM developments d

                WHERE d.id = :id
                  AND d.deleted_at IS NULL

                LIMIT 1
            ");

        $stmt->execute([
            'id' =>
            $developmentId,
        ]);

        $development =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$development) {
            return null;
        }

        if (
            $imageUrl === null ||
            trim($imageUrl) === ''
        ) {
            $imageUrl =
                self::getDevelopmentImageUrl(
                    $pdo,
                    $developmentId
                );
        }

        return self::render(
            $development,
            (string)$imageUrl
        );
    }

    public static function render(
        array $development,
        string $imageUrl = ''
    ): string {
        $id =
            (int)(
                $development['id']
                ?? 0
            );

        $title =
            self::safe(
                trim(
                    (string)(
                        $development['title']
                        ?? ''
                    )
                ) ?: 'Desarrollo'
            );

        $location =
            self::safe(
                self::joinLocation([
                    $development['city']
                        ?? null,

                    $development['zone']
                        ?? null,

                    $development['province']
                        ?? null,
                ])
            );

        if ($location === '') {
            $location =
                'Ubicación no informada';
        }

        $price =
            self::safe(
                self::formatPriceRange(
                    $development
                )
            );

        $stage =
            self::safe(
                self::formatStage(
                    $development['development_stage'] ?? null
                )
            );

        $availableUnits =
            $development['available_units']
            ?? null;

        $totalUnits =
            $development['total_units']
            ?? null;

        $units =
            (
                $availableUnits !== null &&
                $availableUnits !== ''
            ) ||
            (
                $totalUnits !== null &&
                $totalUnits !== ''
            )
            ? self::safe(
                (
                    $availableUnits !== null &&
                    $availableUnits !== ''
                    ? $availableUnits
                    : '—'
                ) .
                    '/' .
                    (
                        $totalUnits !== null &&
                        $totalUnits !== ''
                        ? $totalUnits
                        : '—'
                    )
            )
            : '—';

        $unitTypesCount =
            $development['unit_types_count']
            ?? null;

        $unitTypes =
            $unitTypesCount !== null &&
            $unitTypesCount !== ''
            ? self::safe(
                $unitTypesCount
            )
            : '—';

        $delivery =
            self::formatDeliveryDate(
                $development['delivery_date_estimated'] ?? null
            );

        $summary =
            self::buildSummary(
                $development
            );

        $summaryTitle =
            self::safe(
                $summary['title']
            );

        $summaryNotes =
            self::safe(
                $summary['notes']
            );

        $safeImageUrl =
            self::safe(
                self::absoluteImageUrl(
                    $imageUrl
                )
            );

        $mapPinIcon =
            self::safe(
                self::iconUrl(
                    'map-pin'
                )
            );

        $buildingIcon =
            self::safe(
                self::iconUrl(
                    'building2'
                )
            );

        $layoutGridIcon =
            self::safe(
                self::iconUrl(
                    'layout-grid'
                )
            );

        $homeIcon =
            self::safe(
                self::iconUrl(
                    'home'
                )
            );

        $calendarIcon =
            self::safe(
                self::iconUrl(
                    'calendar'
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
    height="230"
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
        line-height:1;
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
        padding:18px 18px 14px;
        font-family:Manrope, Arial, Helvetica, sans-serif;
    "
>
    <table
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        role="presentation"
    >
    <tbody>
    <tr>
    <td
        valign="top"
        style="
            padding-right:12px;
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
            Desarrollo
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
    </td>

    <td
        valign="top"
        align="right"
        style="
            width:125px;
        "
    >
        <div
            style="
                color:#0f172a;
                font-size:14px;
                font-weight:800;
                line-height:1;
                white-space:nowrap;
            "
        >
            {$price}
        </div>

        <div
            style="
                margin-top:6px;
                color:#94a3b8;
                font-size:9px;
                font-weight:500;
                line-height:1;
            "
        >
            ID: #{$id}
        </div>
    </td>
    </tr>
    </tbody>
    </table>

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
            padding-right:6px;
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
            font-size:10px;
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
            margin-top:13px;
            border-top:1px solid #e2e8f0;
            border-bottom:1px solid #e2e8f0;
        "
    >
    <tbody>
    <tr>

    <td
        valign="middle"
        style="
            padding:10px 5px 10px 0;
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
        <td style="padding-right:4px;">
            <img
                src="{$buildingIcon}"
                width="13"
                height="13"
                alt=""
                style="
                    display:block;
                    width:13px;
                    height:13px;
                    border:0;
                "
            />
        </td>

        <td
            style="
                color:#475569;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:600;
                line-height:1;
            "
        >
            {$stage}
        </td>
        </tr>
        </tbody>
        </table>
    </td>

    <td
        valign="middle"
        style="
            padding:10px 5px;
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
        <td style="padding-right:4px;">
            <img
                src="{$layoutGridIcon}"
                width="13"
                height="13"
                alt=""
                style="
                    display:block;
                    width:13px;
                    height:13px;
                    border:0;
                "
            />
        </td>

        <td
            style="
                color:#475569;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:600;
                line-height:1;
                white-space:nowrap;
            "
        >
            {$units}
        </td>
        </tr>
        </tbody>
        </table>
    </td>

    <td
        valign="middle"
        style="
            padding:10px 5px;
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
        <td style="padding-right:4px;">
            <img
                src="{$homeIcon}"
                width="13"
                height="13"
                alt=""
                style="
                    display:block;
                    width:13px;
                    height:13px;
                    border:0;
                "
            />
        </td>

        <td
            style="
                color:#475569;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:600;
                line-height:1;
            "
        >
            {$unitTypes}
        </td>
        </tr>
        </tbody>
        </table>
    </td>

    <td
        valign="middle"
        style="
            padding:10px 0 10px 5px;
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
        <td style="padding-right:4px;">
            <img
                src="{$calendarIcon}"
                width="13"
                height="13"
                alt=""
                style="
                    display:block;
                    width:13px;
                    height:13px;
                    border:0;
                "
            />
        </td>

        <td
            style="
                color:#475569;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:600;
                line-height:1;
                white-space:nowrap;
            "
        >
            {$delivery}
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
            border-left:3px solid #6ee7b7;
            border-radius:6px;
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
                src="{$buildingIcon}"
                width="13"
                height="13"
                alt=""
                style="
                    display:block;
                    width:13px;
                    height:13px;
                    border:0;
                "
            />
        </td>

        <td
            valign="middle"
            style="
                color:#047857;
                font-family:Manrope, Arial, Helvetica, sans-serif;
                font-size:9px;
                font-weight:700;
                line-height:1;
                text-transform:uppercase;
            "
        >
            Disponibilidad
        </td>
        </tr>
        </tbody>
        </table>

        <div
            style="
                margin-top:6px;
                color:#0f172a;
                font-size:11px;
                font-weight:700;
                line-height:1;
            "
        >
            {$summaryTitle}
        </div>

        <div
            style="
                margin-top:5px;
                color:#64748b;
                font-size:9px;
                font-style:italic;
                font-weight:400;
                line-height:1;
            "
        >
            {$summaryNotes}
        </div>
    </div>
</td>
</tr>
</tbody>
</table>
HTML;
    }

    private static function getDevelopmentImageUrl(
        PDO $pdo,
        int $developmentId
    ): string {
        $stmt =
            $pdo->prepare("
                SELECT id

                FROM development_images

                WHERE development_id = :development_id
                  AND deleted_at IS NULL

                ORDER BY
                    is_cover DESC,
                    sort_order ASC,
                    id ASC

                LIMIT 1
            ");

        $stmt->execute([
            'development_id' =>
            $developmentId,
        ]);

        $imageId =
            (int)(
                $stmt->fetchColumn()
                ?: 0
            );

        if ($imageId <= 0) {
            return '';
        }

        $path =
            ImageUrlSignatureService::generate(
                'development',
                $imageId,
                24 * 60 * 60
            );

        return self::absoluteImageUrl(
            $path
        );
    }

    private static function formatStage(
        ?string $value
    ): string {
        return match (trim(
            (string)$value
        )) {
            'land' =>
            'Lote / tierra',

            'prelaunch' =>
            'Prelanzamiento',

            'launch' =>
            'Lanzamiento',

            'presale' =>
            'Preventa',

            'under_construction' =>
            'En construcción',

            'finished' =>
            'Finalizado',

            default =>
            trim(
                (string)$value
            ) !== ''
                ? trim(
                    (string)$value
                )
                : 'Sin etapa',
        };
    }

    private static function formatPriceRange(
        array $development
    ): string {
        $currency =
            trim(
                (string)(
                    $development['currency']
                    ?? 'USD'
                )
            );

        if ($currency === '') {
            $currency =
                'USD';
        }

        $from =
            $development['price_from']
            ?? null;

        $to =
            $development['price_to']
            ?? null;

        $hasFrom =
            is_numeric($from) &&
            (float)$from > 0;

        $hasTo =
            is_numeric($to) &&
            (float)$to > 0;

        if ($hasFrom && $hasTo) {
            return
                self::formatMoney(
                    $from,
                    $currency
                ) .
                ' - ' .
                self::formatMoney(
                    $to,
                    $currency
                );
        }

        if ($hasFrom) {
            return
                'Desde ' .
                self::formatMoney(
                    $from,
                    $currency
                );
        }

        if ($hasTo) {
            return
                'Hasta ' .
                self::formatMoney(
                    $to,
                    $currency
                );
        }

        return 'Consultar';
    }

    private static function formatMoney(
        mixed $value,
        string $currency
    ): string {
        if (
            !is_numeric($value) ||
            (float)$value <= 0
        ) {
            return '—';
        }

        return
            strtoupper(
                $currency
            ) .
            ' ' .
            number_format(
                (float)$value,
                0,
                ',',
                '.'
            );
    }

    private static function formatDeliveryDate(
        mixed $value
    ): string {
        $value =
            trim(
                (string)$value
            );

        if ($value === '') {
            return '—';
        }

        return mb_substr(
            $value,
            0,
            7,
            'UTF-8'
        );
    }

    private static function buildSummary(
        array $development
    ): array {
        $available =
            $development['available_units']
            ?? null;

        $total =
            $development['total_units']
            ?? null;

        $unitTypes =
            $development['unit_types_count']
            ?? null;

        $title =
            'Unidades sin informar';

        $notes =
            'Cargá unidades y tipologías para completar la información comercial.';

        if (
            (
                $available !== null &&
                $available !== ''
            ) ||
            (
                $total !== null &&
                $total !== ''
            )
        ) {
            $title =
                (
                    $available !== null &&
                    $available !== ''
                    ? $available
                    : '—'
                ) .
                ' disponibles · ' .
                (
                    $total !== null &&
                    $total !== ''
                    ? $total
                    : '—'
                ) .
                ' totales';

            $notes =
                'Resumen de disponibilidad del desarrollo.';
        }

        if (
            is_numeric($unitTypes) &&
            (int)$unitTypes > 0
        ) {
            $count =
                (int)$unitTypes;

            $notes =
                $count .
                (
                    $count === 1
                    ? ' tipología cargada para este desarrollo.'
                    : ' tipologías cargadas para este desarrollo.'
                );
        }

        return [
            'title' =>
            $title,

            'notes' =>
            $notes,
        ];
    }

    private static function iconUrl(
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
            '.png';
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
        return implode(
            ', ',
            array_values(
                array_filter(
                    array_map(
                        static fn($value) =>
                        trim(
                            (string)$value
                        ),
                        $parts
                    )
                )
            )
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
}
