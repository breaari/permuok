<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\BillingCycleService;

class BillingCycleController
{
    private static function requireAdmin(): array
    {
        $auth = AuthMiddleware::handle();

        if (
            (int)($auth['role'] ?? 0) !== 1
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }

        return $auth;
    }

    public static function process(): void
    {
        try {
            /*
             * Este proceso modifica membresías
             * y pausa publicaciones vencidas.
             *
             * Nunca debe poder ejecutarse
             * sin autenticación administrativa.
             */
            self::requireAdmin();

            $result =
                BillingCycleService::processDueMemberships();

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail(
                'No se pudo procesar el ciclo de membresías.',
                500
            );
        }
    }
}
