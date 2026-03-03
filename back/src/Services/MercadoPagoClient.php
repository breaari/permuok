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

    public static function getPaymentById(string $paymentId): array
    {
        // GET /v1/payments/{id} :contentReference[oaicite:5]{index=5}
        $url = "https://api.mercadopago.com/v1/payments/" . urlencode($paymentId);
        return self::request('GET', $url);
    }

    private static function request(string $method, string $url, ?array $json = null): array
    {
        $ch = curl_init($url);
        $headers = [
            "Authorization: Bearer " . self::accessToken(),
            "Content-Type: application/json",
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json ?? [], JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new \Exception("MP request error: " . $err);
        }

        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $raw;
            throw new \Exception("MP HTTP {$code}: {$msg}");
        }

        return is_array($data) ? $data : [];
    }
}