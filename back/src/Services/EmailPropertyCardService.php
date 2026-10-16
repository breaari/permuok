<?php

namespace App\Services;

use PDO;

class EmailPropertyCardService
{
    public static function build(
        PDO $pdo,
        int $propertyId,
        ?string $imageUrl = null
    ): ?string {
        if ($propertyId <= 0) {
            return null;
        }

        $stmt =
            $pdo->prepare("
                SELECT
                    p.id,
                    p.title,
                    p.property_type,
                    p.city,
                    p.zone,
                    p.province,
                    p.total_area,
                    p.covered_area,
                    p.bedrooms,
                    p.bathrooms,
                    p.garages,

                    pr.criteria_mode,
                    pr.accepts_total_swap,
                    pr.accepts_swap_plus_cash,
                    pr.accepts_cash_only,
                    pr.accepts_open_proposals,
                    pr.notes

                FROM properties p

                LEFT JOIN property_requirements pr
                    ON pr.property_id = p.id
                   AND pr.deleted_at IS NULL

                WHERE p.id = :property_id
                  AND p.deleted_at IS NULL

                LIMIT 1
            ");

        $stmt->execute([
            'property_id' =>
                $propertyId,
        ]);

        $property =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$property) {
            return null;
        }

        if (
            $imageUrl === null ||
            trim($imageUrl) === ''
        ) {
            $imageUrl =
                self::getPropertyImageUrl(
                    $pdo,
                    $propertyId
                );
        }

        return self::render(
            $property,
            (string)$imageUrl
        );
    }

    public static function render(
        array $property,
        string $imageUrl
    ): string {
        $type =
            self::safe(
                self::propertyTypeLabel(
                    $property[
                        'property_type'
                    ] ?? null
                )
            );

        $title =
            self::safe(
                trim(
                    (string)(
                        $property['title']
                        ?? $property['property_title']
                        ?? ''
                    )
                ) ?: 'Propiedad'
            );

        $location =
            self::safe(
                self::joinLocation([
                    $property['city']
                        ?? $property['property_city']
                        ?? null,

                    $property['zone']
                        ?? $property['property_zone']
                        ?? null,

                    $property['province']
                        ?? $property['property_province']
                        ?? null,
                ])
            );

        $area =
            $property['total_area']
            ?? $property['property_total_area']
            ?? $property['covered_area']
            ?? $property['property_covered_area']
            ?? null;

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
                $property['bedrooms']
                ?? $property['property_bedrooms']
                ?? '—'
            );

        $bathrooms =
            self::safe(
                $property['bathrooms']
                ?? $property['property_bathrooms']
                ?? '—'
            );

        $garages =
            self::safe(
                $property['garages']
                ?? $property['property_garages']
                ?? '—'
            );

        $exchange =
            self::exchangeSummary(
                $property
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
                self::absoluteImageUrl(
                    $imageUrl
                )
            );

        $mapPinIcon =
            self::safe(
                self::iconUrl('map-pin')
            );

        $rulerIcon =
            self::safe(
                self::iconUrl('ruler')
            );

        $bedIcon =
            self::safe(
                self::iconUrl('bed')
            );

        $bathIcon =
            self::safe(
                self::iconUrl('bath')
            );

        $carIcon =
            self::safe(
                self::iconUrl('car')
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
        {$type}
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

    <td style="padding:11px 10px 11px 0;">
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
            style="padding-right:5px;"
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
                    border:0;
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

    <td style="padding:11px 10px;">
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
            style="padding-right:5px;"
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
                    border:0;
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

    <td style="padding:11px 10px;">
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
            style="padding-right:5px;"
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
                    border:0;
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

    <td style="padding:11px 0 11px 10px;">
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
            style="padding-right:5px;"
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
                    border:0;
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
                font-size:9px;
                font-style:italic;
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

    private static function getPropertyImageUrl(
        PDO $pdo,
        int $propertyId
    ): string {
        $stmt =
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

        $stmt->execute([
            'property_id' =>
                $propertyId,
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
            trim($url);

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

    private static function propertyTypeLabel(
        ?string $value
    ): string {
        return match (
            trim(
                (string)$value
            )
        ) {
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

    private static function exchangeSummary(
        array $property
    ): array {
        $mode =
            trim(
                (string)(
                    $property['criteria_mode']
                    ?? $property[
                        'requirement_criteria_mode'
                    ]
                    ?? ''
                )
            );

        $totalSwap =
            self::isTrue(
                $property['accepts_total_swap']
                ?? $property[
                    'requirement_accepts_total_swap'
                ]
                ?? false
            );

        $swapCash =
            self::isTrue(
                $property['accepts_swap_plus_cash']
                ?? $property[
                    'requirement_accepts_swap_plus_cash'
                ]
                ?? false
            );

        $cashOnly =
            self::isTrue(
                $property['accepts_cash_only']
                ?? $property[
                    'requirement_accepts_cash_only'
                ]
                ?? false
            );

        $open =
            self::isTrue(
                $property['accepts_open_proposals']
                ?? $property[
                    'requirement_accepts_open_proposals'
                ]
                ?? false
            );

        $eyebrow =
            'Condiciones de permuta';

        $title =
            'Sin especificar';

        if ($mode === 'criteria') {
            $eyebrow =
                'Busco con criterios';
        } elseif ($open) {
            $eyebrow =
                'Escucho propuestas';
        }

        if ($cashOnly) {
            $title =
                'Solo efectivo';
        } elseif (
            $swapCash &&
            $totalSwap
        ) {
            $title =
                'Permuta total o parcial';
        } elseif ($swapCash) {
            $title =
                'Permuta + diferencia';
        } elseif ($totalSwap) {
            $title =
                'Permuta total';
        } elseif ($open) {
            $title =
                'Abierto a propuestas';
        }

        $notes =
            trim(
                (string)(
                    $property['notes']
                    ?? $property[
                        'requirement_notes'
                    ]
                    ?? ''
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

    private static function isTrue(
        mixed $value
    ): bool {
        return
            $value === true ||
            $value === 1 ||
            $value === '1';
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