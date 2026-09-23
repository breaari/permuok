<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Helpers\QueryParamHelper;
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

    private static function handleError(
        \Throwable $e
    ): void {
        $code =
            (int)$e->getCode();

        if (
            !($e instanceof \PDOException) &&
            $code >= 400 &&
            $code <= 499
        ) {
            ResponseHelper::fail(
                $e->getMessage(),
                $code
            );
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación.',
            'AdminBillingController'
        );
    }

    public static function counts(): void
    {
        try {
            self::requireAdmin();

            QueryParamHelper::rejectUnknown([
                'q',
            ]);

            $q =
                QueryParamHelper::optionalString(
                    'q',
                    150
                );

            $counts =
                AdminBillingService::counts(
                    $q
                );

            ResponseHelper::ok([
                'counts' =>
                $counts,
            ]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function list(): void
    {
        try {
            self::requireAdmin();

            QueryParamHelper::rejectUnknown([
                'status',
                'page',
                'per_page',
                'q',
            ]);

            $status =
                QueryParamHelper::enum(
                    'status',
                    [
                        'active',
                        'none',
                        'cancel_at_period_end',
                        'scheduled_change',
                        'pending',
                        'expired',
                        'cancelled',
                    ],
                    'active'
                );

            $page =
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                );

            $perPage =
                QueryParamHelper::positiveInt(
                    'per_page',
                    10,
                    100
                );

            $q =
                QueryParamHelper::optionalString(
                    'q',
                    150
                );

            $data =
                AdminBillingService::list(
                    $status,
                    $page,
                    $perPage,
                    $q
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function detail(): void
    {
        try {
            self::requireAdmin();

            QueryParamHelper::rejectUnknown([
                'real_estate_id',
                'id',
            ]);

            $realEstateId =
                QueryParamHelper::requiredPositiveIntFromAliases(
                    [
                        'real_estate_id',
                        'id',
                    ],
                    'real_estate_id'
                );

            $data =
                AdminBillingService::detail(
                    $realEstateId
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }
}
