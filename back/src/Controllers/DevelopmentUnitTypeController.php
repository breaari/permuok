<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\QueryParamHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentUnitTypeService;

class DevelopmentUnitTypeController
{
    private const ALLOWED_FIELDS = [
        'unit_type',
        'label',
        'rooms',
        'bedrooms',
        'bathrooms',
        'garages',
        'area_from',
        'area_to',
        'price_from',
        'price_to',
        'currency',
        'available_units',
    ];

    private static function error(
        \Throwable $e
    ): void {
        if (
            $e instanceof \PDOException ||
            !$e instanceof \Exception
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación con las tipologías.',
                'DevelopmentUnitTypeController'
            );

            return;
        }

        $status =
            (int)$e->getCode();

        if (
            $status < 400 ||
            $status > 499
        ) {
            $status = 422;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación con las tipologías.',
            $status
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

    private static function rejectUnknownFields(
        array $data
    ): void {
        $unknownFields =
            array_diff(
                array_keys($data),
                self::ALLOWED_FIELDS
            );

        if ($unknownFields !== []) {
            throw new \Exception(
                'La solicitud contiene campos no permitidos',
                422
            );
        }
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
                DevelopmentUnitTypeService::listByDevelopment(
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

    public static function create(): void
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

            self::rejectUnknownFields(
                $data
            );

            $result =
                DevelopmentUnitTypeService::create(
                    (int)$auth['id'],
                    $developmentId,
                    $data
                );

            ResponseHelper::ok(
                $result,
                201
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function update(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'unit_type_id',
            ]);

            $unitTypeId =
                QueryParamHelper::requiredPositiveInt(
                    'unit_type_id'
                );

            $data =
                self::readJsonBody();

            self::rejectUnknownFields(
                $data
            );

            if ($data === []) {
                throw new \Exception(
                    'No se enviaron campos para actualizar',
                    422
                );
            }

            $result =
                DevelopmentUnitTypeService::update(
                    (int)$auth['id'],
                    $unitTypeId,
                    $data
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function delete(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'unit_type_id',
            ]);

            $unitTypeId =
                QueryParamHelper::requiredPositiveInt(
                    'unit_type_id'
                );

            $result =
                DevelopmentUnitTypeService::delete(
                    (int)$auth['id'],
                    $unitTypeId
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
