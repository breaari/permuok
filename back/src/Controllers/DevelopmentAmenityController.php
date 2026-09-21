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

        $code = (int)$e->getCode();

        if (
            $code < 400 ||
            $code > 499
        ) {
            $code = 422;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación con las amenities.',
            $code
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

            if ($developmentId <= 0) {
                throw new \Exception(
                    'Desarrollo inválido',
                    422
                );
            }

            $rawBody =
                file_get_contents(
                    'php://input'
                );

            try {
                $data =
                    json_decode(
                        $rawBody !== false
                            ? $rawBody
                            : '',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
            } catch (\JsonException $e) {
                throw new \Exception(
                    'El cuerpo de la solicitud no contiene un JSON válido',
                    422
                );
            }

            if (!is_array($data)) {
                throw new \Exception(
                    'El cuerpo de la solicitud debe ser un objeto JSON',
                    422
                );
            }

            $amenities =
                $data['amenities']
                ?? null;

            if (!is_array($amenities)) {
                throw new \Exception(
                    'Amenities debe ser una lista',
                    422
                );
            }

            $result =
                DevelopmentAmenityService::replaceAll(
                    (int)$auth['id'],
                    $developmentId,
                    $amenities
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
