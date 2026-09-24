<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\QueryParamHelper;
use App\Helpers\ResponseHelper;
use App\Services\PropertyImageService;

class PropertyImageController
{
    private static function error(
        \Throwable $e
    ): void {
        $message = trim(
            $e->getMessage()
        );

        $status =
            (int)$e->getCode();

        if (
            $status >= 400 &&
            $status <= 499
        ) {
            ResponseHelper::fail(
                $message,
                $status
            );

            return;
        }

        $internalErrors = [
            'No se pudo crear el directorio de imágenes',
            'No se pudo guardar una de las imágenes',
        ];

        if (
            $e instanceof \PDOException ||
            !$e instanceof \Exception ||
            in_array(
                $message,
                $internalErrors,
                true
            )
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación con las imágenes.',
                'PropertyImageController'
            );

            return;
        }

        $forbiddenErrors = [
            'No tenés permisos para administrar imágenes',
            'Tu cuenta está inactiva',
            'El usuario no está vinculado a una inmobiliaria',
        ];

        if (
            in_array(
                $message,
                $forbiddenErrors,
                true
            )
        ) {
            ResponseHelper::fail(
                $message,
                403
            );

            return;
        }

        $notFoundErrors = [
            'Usuario no encontrado',
            'Propiedad no encontrada',
            'Imagen no encontrada',
        ];

        if (
            in_array(
                $message,
                $notFoundErrors,
                true
            )
        ) {
            ResponseHelper::fail(
                $message,
                404
            );

            return;
        }

        ResponseHelper::fail(
            $message
                ?: 'No se pudo completar la operación con las imágenes.',
            422
        );
    }

    private static function readJsonObject(): array
    {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            $raw === false ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío',
                422
            );
        }

        if (strlen($raw) > 65536) {
            throw new \Exception(
                'El cuerpo de la solicitud es demasiado extenso',
                413
            );
        }

        $raw =
            trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto',
                422
            );
        }

        try {
            $data =
                json_decode(
                    $raw,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        if (!is_array($data)) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        return $data;
    }

    public static function upload(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'id',
            ]);

            $propertyId =
                QueryParamHelper::requiredPositiveInt(
                    'id'
                );

            if ($_POST !== []) {
                throw new \Exception(
                    'La solicitud contiene campos no permitidos',
                    422
                );
            }

            $unknownFileFields =
                array_diff(
                    array_keys($_FILES),
                    [
                        'images',
                    ]
                );

            if ($unknownFileFields !== []) {
                throw new \Exception(
                    'La solicitud contiene archivos no permitidos',
                    422
                );
            }

            $files =
                $_FILES['images']
                ?? [];

            if (!is_array($files)) {
                throw new \Exception(
                    'El contenido de las imágenes es inválido',
                    422
                );
            }

            $result =
                PropertyImageService::upload(
                    (int)$auth['id'],
                    $propertyId,
                    $files
                );

            ResponseHelper::ok(
                $result,
                201
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
                'image_id',
            ]);

            $imageId =
                QueryParamHelper::requiredPositiveInt(
                    'image_id'
                );

            $result =
                PropertyImageService::delete(
                    (int)$auth['id'],
                    $imageId
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function reorder(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'id',
            ]);

            $propertyId =
                QueryParamHelper::requiredPositiveInt(
                    'id'
                );

            $data =
                self::readJsonObject();

            $unknownFields =
                array_diff(
                    array_keys($data),
                    [
                        'images',
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
                    'images',
                    $data
                ) ||
                !is_array($data['images'])
            ) {
                throw new \Exception(
                    'Debés enviar el listado de imágenes',
                    422
                );
            }

            $result =
                PropertyImageService::reorder(
                    (int)$auth['id'],
                    $propertyId,
                    $data['images']
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
