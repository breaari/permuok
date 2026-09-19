<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentAmenityService;

class DevelopmentAmenityController
{
    private static function error(
        \Throwable $e
    ): void {
        if (
            $e instanceof \PDOException ||
            !$e instanceof \Exception
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación con las amenities.',
                'DevelopmentAmenityController'
            );

            return;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación con las amenities.',
            422
        );
    }

    public static function list(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $developmentId =
                (int)($_GET['id'] ?? 0);

            $result =
                DevelopmentAmenityService::listByDevelopment(
                    (int)$auth['id'],
                    $developmentId
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function replaceAll(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $developmentId =
                (int)($_GET['id'] ?? 0);

            $data =
                json_decode(
                    file_get_contents('php://input'),
                    true
                ) ?? [];

            $result =
                DevelopmentAmenityService::replaceAll(
                    (int)$auth['id'],
                    $developmentId,
                    $data['amenities'] ?? []
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
