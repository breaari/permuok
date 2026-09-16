<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\MercadoPagoWebhookSignatureService;
use App\Services\WebhookMercadoPagoService;

class WebhookMercadoPagoController
{
    public static function handle(): void
    {
        try {
            $rawBody =
                file_get_contents(
                    'php://input'
                );

            $payload = [];

            if (
                is_string($rawBody)
                && trim($rawBody) !== ''
            ) {
                $decoded =
                    json_decode(
                        $rawBody,
                        true
                    );

                if (
                    json_last_error()
                    === JSON_ERROR_NONE
                    && is_array($decoded)
                ) {
                    $payload = $decoded;
                }
            }

            $headers =
                function_exists('getallheaders')
                ? getallheaders()
                : [];

            $normalizedHeaders = [];

            foreach ($headers as $name => $value) {
                $normalizedHeaders[strtolower((string)$name)] = $value;
            }

            $signatureHeader =
                $normalizedHeaders['x-signature']
                ?? (
                    $_SERVER['HTTP_X_SIGNATURE']
                    ?? null
                );

            $requestIdHeader =
                $normalizedHeaders['x-request-id']
                ?? (
                    $_SERVER['HTTP_X_REQUEST_ID']
                    ?? null
                );

            $validSignature =
                MercadoPagoWebhookSignatureService::validate(
                    $_GET,
                    $payload,
                    is_string($signatureHeader)
                        ? $signatureHeader
                        : null,
                    is_string($requestIdHeader)
                        ? $requestIdHeader
                        : null
                );

            if (!$validSignature) {
                error_log(
                    '[MercadoPago Webhook] ' .
                        'Firma inválida o ausente'
                );

                ResponseHelper::fail(
                    'Notificación no autorizada.',
                    401
                );
            }

            $result =
                WebhookMercadoPagoService::handleNotification(
                    $_GET,
                    $payload
                );

            ResponseHelper::ok(
                $result,
                200
            );
        } catch (\Throwable $e) {
            error_log(
                '[MercadoPago Webhook] ' .
                    $e->getMessage()
            );

            ResponseHelper::fail(
                'No se pudo procesar la notificación.',
                500
            );
        }
    }
}
