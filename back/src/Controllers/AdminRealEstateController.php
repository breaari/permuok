<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminRealEstateService;

class AdminRealEstateController
{
    public static function pending(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            if ((int)$ctx['role'] !== 1) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $items = AdminRealEstateService::listPending();
            ResponseHelper::ok(['items' => $items]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    public static function approve(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            if ((int)$ctx['role'] !== 1) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $realEstateId = (int)($payload['real_estate_id'] ?? 0);
            $action = trim((string)($payload['action'] ?? ''));
            $note = isset($payload['note']) ? trim((string)$payload['note']) : null;

            if ($realEstateId <= 0) {
                ResponseHelper::fail('real_estate_id requerido', 422);
            }
            if (!in_array($action, ['approve', 'reject'], true)) {
                ResponseHelper::fail('action inválido (approve|reject)', 422);
            }

            $result = AdminRealEstateService::processReview($ctx['id'], $realEstateId, $action, $note);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function approved(): void
{
    try {
        $ctx = AuthMiddleware::handle();
        if ((int)$ctx['role'] !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }

        $items = AdminRealEstateService::listApproved();
        ResponseHelper::ok(['items' => $items]);
    } catch (\Throwable $e) {
        ResponseHelper::fail($e->getMessage(), 422);
    }
}


    public static function rejected(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            if ((int)$ctx['role'] !== 1) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $items = AdminRealEstateService::listRejected();
            ResponseHelper::ok(['items' => $items]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}
