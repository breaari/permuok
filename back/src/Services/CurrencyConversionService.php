<?php

namespace App\Services;

use PDO;
use Exception;
use DOMDocument;

class CurrencyConversionService
{
    private const BASE_CURRENCY = 'USD';
    private const QUOTE_CURRENCY = 'ARS';

    private const RATE_TYPE =
        'official_sell';

    private const SOURCE =
        'dolarhoy';

    private const SOURCE_URL =
        'https://dolarhoy.com/cotizacion-dolar-oficial';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    /**
     * Actualiza la cotización oficial venta
     * obtenida desde DolarHoy.
     *
     * Si el valor no cambió, no genera
     * una fila duplicada.
     */
    public static function refreshOfficialRate(): array
    {
        $rate =
            self::fetchOfficialSellRate();

        $current =
            self::getLatestRate();

        if (
            $current !== null &&
            abs(
                (float)$current['rate'] -
                $rate
            ) < 0.000001
        ) {
            return [
                'updated' => false,
                'rate' => $rate,
                'currency' => 'ARS',
                'source' => self::SOURCE,
                'rate_type' => self::RATE_TYPE,
                'rate_id' =>
                    (int)$current['id'],
                'effective_at' =>
                    $current['effective_at'],
            ];
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            INSERT INTO currency_exchange_rates (
                base_currency,
                quote_currency,
                rate,
                rate_type,
                source,
                source_url,
                effective_at,
                fetched_at
            ) VALUES (
                :base_currency,
                :quote_currency,
                :rate,
                :rate_type,
                :source,
                :source_url,
                NOW(),
                NOW()
            )
        ");

        $st->execute([
            'base_currency' =>
                self::BASE_CURRENCY,

            'quote_currency' =>
                self::QUOTE_CURRENCY,

            'rate' =>
                $rate,

            'rate_type' =>
                self::RATE_TYPE,

            'source' =>
                self::SOURCE,

            'source_url' =>
                self::SOURCE_URL,
        ]);

        return [
            'updated' => true,
            'rate' => $rate,
            'currency' => 'ARS',
            'source' => self::SOURCE,
            'rate_type' => self::RATE_TYPE,
            'rate_id' =>
                (int)$pdo->lastInsertId(),
            'effective_at' =>
                date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convierte un importe entre ARS y USD
     * utilizando la última cotización
     * oficial venta guardada.
     */
    public static function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency
    ): float {
        $from =
            self::normalizeCurrency(
                $fromCurrency
            );

        $to =
            self::normalizeCurrency(
                $toCurrency
            );

        if ($amount < 0) {
            throw new Exception(
                'El importe a convertir no puede ser negativo.'
            );
        }

        if ($from === $to) {
            return round(
                $amount,
                2
            );
        }

        if (
            !in_array(
                $from,
                ['ARS', 'USD'],
                true
            ) ||
            !in_array(
                $to,
                ['ARS', 'USD'],
                true
            )
        ) {
            throw new Exception(
                "Conversión no soportada: {$from} → {$to}"
            );
        }

        $rateRow =
            self::getLatestRate();

        if ($rateRow === null) {
            throw new Exception(
                'No existe una cotización USD/ARS disponible.'
            );
        }

        $rate =
            (float)$rateRow['rate'];

        if ($rate <= 0) {
            throw new Exception(
                'La cotización USD/ARS guardada no es válida.'
            );
        }

        /*
         * USD → ARS
         */
        if (
            $from === 'USD' &&
            $to === 'ARS'
        ) {
            return round(
                $amount * $rate,
                2
            );
        }

        /*
         * ARS → USD
         */
        if (
            $from === 'ARS' &&
            $to === 'USD'
        ) {
            return round(
                $amount / $rate,
                2
            );
        }

        throw new Exception(
            "Conversión no soportada: {$from} → {$to}"
        );
    }

    /**
     * Devuelve la última cotización válida.
     */
    public static function getLatestRate(): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                id,
                base_currency,
                quote_currency,
                rate,
                rate_type,
                source,
                source_url,
                effective_at,
                fetched_at,
                created_at

            FROM currency_exchange_rates

            WHERE base_currency =
                :base_currency

              AND quote_currency =
                :quote_currency

              AND rate_type =
                :rate_type

            ORDER BY
                effective_at DESC,
                id DESC

            LIMIT 1
        ");

        $st->execute([
            'base_currency' =>
                self::BASE_CURRENCY,

            'quote_currency' =>
                self::QUOTE_CURRENCY,

            'rate_type' =>
                self::RATE_TYPE,
        ]);

        $row =
            $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Consulta DolarHoy y obtiene
     * Dólar Oficial - Venta.
     */
    private static function fetchOfficialSellRate(): float
    {
        $html =
            self::downloadSourcePage();

        if ($html === '') {
            throw new Exception(
                'DolarHoy devolvió una respuesta vacía.'
            );
        }

        $previousState =
            libxml_use_internal_errors(true);

        $document =
            new DOMDocument();

        $loaded =
            $document->loadHTML(
                $html,
                LIBXML_NOERROR |
                LIBXML_NOWARNING
            );

        libxml_clear_errors();

        libxml_use_internal_errors(
            $previousState
        );

        if (!$loaded) {
            throw new Exception(
                'No se pudo interpretar la respuesta de DolarHoy.'
            );
        }

        $text =
            $document->textContent ?? '';

        $text =
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            );

        $text =
            trim((string)$text);

        /*
         * Buscamos específicamente:
         *
         * Dólar Oficial
         * Compra $xxxx
         * Venta $xxxx
         */
        $matched =
            preg_match(
                '/D[oó]lar\s+Oficial.*?' .
                'Compra\s*\$?\s*([\d\.,]+).*?' .
                'Venta\s*\$?\s*([\d\.,]+)/isu',
                $text,
                $matches
            );

        if (
            $matched !== 1 ||
            empty($matches[2])
        ) {
            throw new Exception(
                'No se encontró la cotización de venta del Dólar Oficial en DolarHoy.'
            );
        }

        $rate =
            self::parseLocalizedNumber(
                $matches[2]
            );

        /*
         * Protección básica contra un cambio
         * accidental del HTML que provoque
         * interpretar otro número.
         */
        if (
            $rate < 100 ||
            $rate > 100000
        ) {
            throw new Exception(
                'La cotización obtenida desde DolarHoy parece inválida.'
            );
        }

        return $rate;
    }

    private static function downloadSourcePage(): string
    {
        $ch =
            curl_init(
                self::SOURCE_URL
            );

        if ($ch === false) {
            throw new Exception(
                'No se pudo inicializar la conexión con DolarHoy.'
            );
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_FOLLOWLOCATION =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    8,

                CURLOPT_TIMEOUT =>
                    15,

                CURLOPT_USERAGENT =>
                    'PermuOK/1.0 (+https://permuok.com)',

                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: es-AR,es;q=0.9',
                ],
            ]
        );

        $response =
            curl_exec($ch);

        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $error =
            curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new Exception(
                'No se pudo consultar DolarHoy: ' .
                $error
            );
        }

        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {
            throw new Exception(
                'DolarHoy respondió con HTTP ' .
                $httpCode
            );
        }

        return (string)$response;
    }

    private static function parseLocalizedNumber(
        string $value
    ): float {
        $value =
            trim($value);

        $value =
            str_replace(
                [
                    '$',
                    ' ',
                    "\xc2\xa0",
                ],
                '',
                $value
            );

        /*
         * Formato argentino:
         *
         * 1.510,50
         * →
         * 1510.50
         */
        if (
            str_contains($value, ',')
        ) {
            $value =
                str_replace(
                    '.',
                    '',
                    $value
                );

            $value =
                str_replace(
                    ',',
                    '.',
                    $value
                );
        }

        if (!is_numeric($value)) {
            throw new Exception(
                'La cotización recibida no tiene un formato numérico válido.'
            );
        }

        return (float)$value;
    }

    private static function normalizeCurrency(
        string $currency
    ): string {
        return strtoupper(
            trim($currency)
        );
    }
}