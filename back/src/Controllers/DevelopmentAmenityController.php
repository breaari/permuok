<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\QueryParamHelper;
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

        $code =
            (int)$e->getCode();

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

    private static function readJsonBody(): array
    {
        $rawBody =
            file_get_contents(
                'php://input'
            );

        if (
            $rawBody === false ||
            trim($rawBody) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío',
                422
            );
        }

        if (strlen($rawBody) > 65536) {
            throw new \Exception(
                'El cuerpo de la solicitud es demasiado extenso',
                413
            );
        }

        $rawBody =
            trim($rawBody);

        if ($rawBody[0] !== '{') {
            throw new \Exception(
                'El cuerpo de la solicitud debe ser un objeto JSON',
                422
            );
        }

        try {
            $data =
                json_decode(
                    $rawBody,
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

        return $data;
    }

    public static function list(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'id',
            ]);

            $developmentId =
                QueryParamHelper::requiredPositiveInt(
                    'id'
                );

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

            QueryParamHelper::rejectUnknown([
                'id',
            ]);

            $developmentId =
                QueryParamHelper::requiredPositiveInt(
                    'id'
                );

            $data =
                self::readJsonBody();

            $unknownFields =
                array_diff(
                    array_keys($data),
                    [
                        'amenities',
                    ]
                );

            if ($unknownFields !== []) {
                throw new \Exception(
                    'La solicitud contiene campos no permitidos',
                    422
                );
            }

            if (
                !array_key_exists(
                    'amenities',
                    $data
                ) ||
                !is_array($data['amenities'])
            ) {
                throw new \Exception(
                    'Amenities debe ser una lista',
                    422
                );
            }

            if (count($data['amenities']) > 50) {
                throw new \Exception(
                    'Se enviaron demasiadas amenities',
                    422
                );
            }

            $result =
                DevelopmentAmenityService::replaceAll(
                    (int)$auth['id'],
                    $developmentId,
                    $data['amenities']
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
