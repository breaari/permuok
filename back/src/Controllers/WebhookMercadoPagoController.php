<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\WebhookMercadoPagoService;

class WebhookMercadoPagoController
{
    public static function handle(): void
    {
        try {
            // MP puede mandar datos por query o body (según configuración) :contentReference[oaicite:6]{index=6}
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = WebhookMercadoPagoService::handleNotification($_GET, $payload);

            // MP espera 200 rápido
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            // igual devolver 200 para no reintentar infinito en desarrollo
            ResponseHelper::ok(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}