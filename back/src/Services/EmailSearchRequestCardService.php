<?php

namespace App\Services;

use PDO;

class EmailSearchRequestCardService
{
    public static function build(
        PDO $pdo,
        int $searchRequestId
    ): ?string {
        if ($searchRequestId <= 0) {
            return null;
        }

        $stmt =
            $pdo->prepare("
                SELECT
                    sr.id,
                    sr.title,
                    sr.description,
                    sr.city,
                    sr.zone,
                    sr.province,
                    sr.currency,
                    sr.min_value,
                    sr.max_value,
                    sr.payment_mode_cash,
                    sr.payment_mode_swap,

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
                        AS property_types

                FROM search_requests sr

                WHERE sr.id = :id

                  AND sr.deleted_at IS NULL

                LIMIT 1
            ");

        $stmt->execute([
            'id' =>
            $searchRequestId,
        ]);

        $item =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$item) {
            return null;
        }

        return self::render(
            $item
        );
    }

    public static function render(
        array $item
    ): string {
        $id =
            (int)(
                $item['id']
                ?? $item['search_request_id']
                ?? 0
            );

        $title =
            self::safe(
                trim(
                    (string)(
                        $item['title']
                        ?? $item['search_title']
                        ?? ''
                    )
                ) ?: 'Búsqueda'
            );

        $description =
            self::safe(
                trim(
                    (string)(
                        $item['description']
                        ?? $item['search_description']
                        ?? ''
                    )
                ) ?: 'Sin descripción.'
            );

        $location =
            self::safe(
                self::joinLocation([
                    $item['city']
                        ?? $item['search_city']
                        ?? null,

                    $item['zone']
                        ?? $item['search_zone']
                        ?? null,

                    $item['province']
                        ?? $item['search_province']
                        ?? null,
                ])
            );

        if ($location === '') {
            $location =
                'Sin ubicación';
        }

        $types =
            self::safe(
                self::propertyTypesLabel(
                    $item['property_types']
                        ?? $item['search_property_types']
                        ?? null
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

    private static function propertyTypeLabel(
        string $type
    ): string {
        return match ($type) {
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
            ucfirst($type),
        };
    }

    private static function formatMoneyRange(
        array $item
    ): string {
        $currency =
            trim(
                (string)(
                    $item['currency']
                    ?? $item['search_currency']
                    ?? 'USD'
                )
            );

        $min =
            $item['min_value']
            ?? $item['search_min_value']
            ?? null;

        $max =
            $item['max_value']
            ?? $item['search_max_value']
            ?? null;

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

    private static function formatMoney(
        mixed $value,
        string $currency
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
                    $currency
                )
            );

        if ($currency === '') {
            $currency =
                'USD';
        }

        return
            $currency .
            ' ' .
            number_format(
                (float)$value,
                0,
                ',',
                '.'
            );
    }

    private static function paymentLabel(
        array $item
    ): string {
        $cash =
            self::isTrue(
                $item['payment_mode_cash']
                    ?? $item['search_payment_mode_cash']
                    ?? false
            );

        $swap =
            self::isTrue(
                $item['payment_mode_swap']
                    ?? $item['search_payment_mode_swap']
                    ?? false
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
