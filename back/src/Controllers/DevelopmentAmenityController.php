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
        if ($e instanceof \PDOException) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación con las amenities.',
                'DevelopmentAmenityController'
            );
        }

        $code =
            (int)$e->getCode();

        if (
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
            'No se pudo completar la operación con las amenities.',
            'DevelopmentAmenityController'
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
