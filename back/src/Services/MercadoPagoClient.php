<?php

namespace App\Services;

class MercadoPagoClient
{
    private static function accessToken(): string
    {
        $t = $_ENV['MP_ACCESS_TOKEN'] ?? '';
        if ($t === '') {
            throw new \Exception("MP_ACCESS_TOKEN no configurado");
        }
        return $t;
    }

    public static function createPreference(array $payload): array
    {
        $url = "https://api.mercadopago.com/checkout/preferences";
        $res = self::request('POST', $url, $payload);
        return $res;
    }

    public static function createSubscription(
        array $payload
    ): array {
        return self::request(
            'POST',
            'https://api.mercadopago.com/preapproval',
            $payload
        );
    }

    public static function getSubscriptionById(
        string $subscriptionId
    ): array {
        return self::request(
            'GET',
            'https://api.mercadopago.com/preapproval/'
                . urlencode($subscriptionId)
        );
    }

    public static function getAuthorizedPaymentById(
        string $authorizedPaymentId
    ): array {
        return self::request(
            'GET',
            'https://api.mercadopago.com/authorized_payments/'
                . urlencode($authorizedPaymentId)
        );
    }

    public static function updateSubscription(
        string $subscriptionId,
        array $payload
    ): array {
        return self::request(
            'PUT',
            'https://api.mercadopago.com/preapproval/'
                . urlencode($subscriptionId),
            $payload
        );
    }

    public static function getPaymentById(string $paymentId): array
    {
        // GET /v1/payments/{id} :contentReference[oaicite:5]{index=5}
        $url = "https://api.mercadopago.com/v1/payments/" . urlencode($paymentId);
        return self::request('GET', $url);
    }

    private static function request(
        string $method,
        string $url,
        ?array $json = null
    ): array {
        $method =
            strtoupper(trim($method));

        if (
            !in_array(
                $method,
                ['GET', 'POST', 'PUT'],
                true
            )
        ) {
            throw new \Exception(
                'Método HTTP no permitido para Mercado Pago'
            );
        }

        /*
     * Todas las solicitudes deben dirigirse
     * exclusivamente a la API oficial.
     */
        $parsedUrl =
            parse_url($url);

        if (
            !is_array($parsedUrl) ||
            ($parsedUrl['scheme'] ?? '') !== 'https' ||
            ($parsedUrl['host'] ?? '') !==
            'api.mercadopago.com'
        ) {
            throw new \Exception(
                'Destino de Mercado Pago inválido'
            );
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \Exception(
                'No se pudo iniciar la conexión con Mercado Pago'
            );
        }

        $headers = [
            'Authorization: Bearer ' .
                self::accessToken(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>
            $headers,

            /*
         * Evita que una demora externa mantenga
         * ocupado indefinidamente el proceso PHP.
         */
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,

            /*
         * Solamente se permiten conexiones HTTPS.
         */
            CURLOPT_PROTOCOLS =>
            CURLPROTO_HTTPS,

            CURLOPT_SSL_VERIFYPEER =>
            true,

            CURLOPT_SSL_VERIFYHOST =>
            2,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] =
                true;
        }

        if ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] =
                'PUT';
        }

        if (
            in_array(
                $method,
                ['POST', 'PUT'],
                true
            )
        ) {
            try {
                $options[CURLOPT_POSTFIELDS] =
                    json_encode(
                        $json ?? [],
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES |
                            JSON_THROW_ON_ERROR
                    );
            } catch (\JsonException $e) {
                curl_close($ch);

                throw new \Exception(
                    'No se pudo preparar la solicitud para Mercado Pago',
                    0,
                    $e
                );
            }
        }

        curl_setopt_array(
            $ch,
            $options
        );

        $raw = curl_exec($ch);

        $error =
            curl_error($ch);

        $errorNumber =
            curl_errno($ch);

        $code =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        curl_close($ch);

        if ($raw === false) {
            throw new \Exception(
                'Error de conexión con Mercado Pago (' .
                    $errorNumber .
                    '): ' .
                    $error
            );
        }

        $data =
            json_decode(
                $raw,
                true
            );

        if (
            $code < 200 ||
            $code >= 300
        ) {
            $message =
                is_array($data)
                ? json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                )
                : substr(
                    (string)$raw,
                    0,
                    2000
                );

            throw new \Exception(
                "Mercado Pago respondió HTTP {$code}: {$message}"
            );
        }

        if (!is_array($data)) {
            throw new \Exception(
                'Mercado Pago devolvió una respuesta JSON inválida'
            );
        }

        return $data;
    }
}
