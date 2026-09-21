<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentUnitTypeService;

class DevelopmentUnitTypeController
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
                'No se pudo completar la operación con las tipologías.',
                'DevelopmentUnitTypeController'
            );

            return;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación con las tipologías.',
            422
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
                'El cuerpo de la solicitud está vacío'
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
                'El cuerpo de la solicitud no contiene un JSON válido'
            );
        }

        if (!is_array($data)) {
            throw new \Exception(
                'El cuerpo de la solicitud debe ser un objeto JSON'
            );
        }

        return $data;
    }

    public static function list(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $developmentId =
                (int)($_GET['id'] ?? 0);

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

            $developmentId =
                (int)($_GET['id'] ?? 0);
            $data =
                self::readJsonBody();

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

            $unitTypeId =
                (int)($_GET['unit_type_id'] ?? 0);

            $data =
                self::readJsonBody();

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

            $unitTypeId =
                (int)($_GET['unit_type_id'] ?? 0);

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
