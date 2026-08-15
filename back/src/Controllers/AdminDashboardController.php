<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminDashboardService;

class AdminDashboardController
{
    private static function requireAdmin(): void
    {
        $ctx = AuthMiddleware::handle();

        if ((int)($ctx['role'] ?? 0) !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }
    }

    public static function stats(): void
    {
        try {
            self::requireAdmin();

            $stats = AdminDashboardService::stats();

            ResponseHelper::ok([
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }
}