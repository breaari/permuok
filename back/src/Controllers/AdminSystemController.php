<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminSystemService;

class AdminSystemController
{
    private static function requireAdmin(): void
    {
        $ctx =
            AuthMiddleware::handle();

        if (
            (int)(
                $ctx['role']
                ?? 0
            ) !== 1
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }
    }

    public static function compatibilityJobs(): void
    {
        try {
            self::requireAdmin();

            $status =
                trim(
                    (string)(
                        $_GET['status']
                        ?? 'failed'
                    )
                );

            $page =
                (int)(
                    $_GET['page']
                    ?? 1
                );

            $limit =
                (int)(
                    $_GET['limit']
                    ?? 20
                );

            $result =
                AdminSystemService::compatibilityJobs(
                    $status,
                    $page,
                    $limit
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::fail(
                $e->getMessage(),
                500
            );
        }
    }

    public static function emailJobs(): void
    {
        try {
            self::requireAdmin();

            $status =
                trim(
                    (string)(
                        $_GET['status']
                        ?? 'failed'
                    )
                );

            $page =
                (int)(
                    $_GET['page']
                    ?? 1
                );

            $limit =
                (int)(
                    $_GET['limit']
                    ?? 20
                );

            $result =
                AdminSystemService::emailJobs(
                    $status,
                    $page,
                    $limit
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::fail(
                $e->getMessage(),
                500
            );
        }
    }
}
