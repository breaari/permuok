<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
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
        $raw = file_get_contents(
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

        $raw = trim($raw);

        /*
     * Este endpoint solamente acepta
     * un objeto JSON, no un array raíz.
     */
        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto',
                422
            );
        }

        try {
            $data = json_decode(
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

            $propertyId = filter_var(
                $_GET['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($propertyId === false) {
                throw new \Exception(
                    'Identificador de propiedad inválido',
                    422
                );
            }

            $files =
                $_FILES['images'] ?? [];

            if (!is_array($files)) {
                throw new \Exception(
                    'El contenido de las imágenes es inválido',
                    422
                );
            }

            $result =
                PropertyImageService::upload(
                    (int)$auth['id'],
                    (int)$propertyId,
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

            $imageId = filter_var(
                $_GET['image_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($imageId === false) {
                throw new \Exception(
                    'Identificador de imagen inválido',
                    422
                );
            }

            $result =
                PropertyImageService::delete(
                    (int)$auth['id'],
                    (int)$imageId
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

            $propertyId = filter_var(
                $_GET['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($propertyId === false) {
                throw new \Exception(
                    'Identificador de propiedad inválido',
                    422
                );
            }

            $data =
                self::readJsonObject();

            if (
                !array_key_exists(
                    'images',
                    $data
                )
            ) {
                throw new \Exception(
                    'Debés enviar el listado de imágenes',
                    422
                );
            }

            $result =
                PropertyImageService::reorder(
                    (int)$auth['id'],
                    (int)$propertyId,
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
