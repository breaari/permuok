<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\DevBillingService;

class DevBillingController
{
    public static function approve()
    {
        $ctx = AuthMiddleware::handle();

        // solo super admin o dev (ajustá si querés)
        if ((int)$ctx['role'] !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $paymentId = (int)($data['payment_id'] ?? 0);

        if ($paymentId <= 0) {
            ResponseHelper::fail('payment_id requerido', 422);
        }

        $result = DevBillingService::forceApprove($paymentId, (int)$ctx['id']);
        ResponseHelper::ok($result, 200);
    }
}