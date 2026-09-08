<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\WebhookMercadoPagoService;

class WebhookMercadoPagoController
{
    public static function handle(): void
    {
        try {
            $rawBody = file_get_contents(
                'php://input'
            );

            $payload = [];

            if (
                is_string($rawBody)
                && trim($rawBody) !== ''
            ) {
                $decoded = json_decode(
                    $rawBody,
                    true
                );

                if (
                    json_last_error() === JSON_ERROR_NONE
                    && is_array($decoded)
                ) {
                    $payload = $decoded;
                }
            }

            $result =
                WebhookMercadoPagoService::handleNotification(
                    $_GET,
                    $payload
                );

            /*
             * Mercado Pago recibió y procesamos
             * correctamente la notificación.
             */
            ResponseHelper::ok(
                $result,
                200
            );
        } catch (\Throwable $e) {
            /*
             * Si ocurrió un error real,
             * NO debemos responder 200.
             *
             * Así Mercado Pago puede volver
             * a intentar la notificación.
             */
            error_log(
                '[MercadoPago Webhook] '
                    . $e->getMessage()
            );

            ResponseHelper::fail(
                'No se pudo procesar la notificación.',
                500
            );
        }
    }
}
