<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminBillingService;

class AdminBillingController
{
    private static function requireAdmin(): array
    {
        $ctx = AuthMiddleware::handle();

        if ((int)($ctx['role'] ?? 0) !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }

        return $ctx;
    }

    public static function counts(): void
    {
        try {
            self::requireAdmin();

            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
            $counts = AdminBillingService::counts($q);

            ResponseHelper::ok([
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    public static function list(): void
    {
        try {
            self::requireAdmin();

            $status = (string)($_GET['status'] ?? 'active');
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

            $data = AdminBillingService::list($status, $page, $perPage, $q);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function detail(): void
    {
        try {
            self::requireAdmin();

            $realEstateId = (int)($_GET['real_estate_id'] ?? $_GET['id'] ?? 0);

            if ($realEstateId <= 0) {
                ResponseHelper::fail('real_estate_id requerido', 422);
            }

            $data = AdminBillingService::detail($realEstateId);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}