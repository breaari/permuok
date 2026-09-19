<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentImageService;

class DevelopmentImageController
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
                'DevelopmentImageController'
            );

            return;
        }

        $membershipErrors = [
            'La inmobiliaria no tiene una membresía activa',
            'La membresía de la inmobiliaria no está activa',
            'La membresía de la inmobiliaria está vencida',
            'Tu plan no permite publicar desarrollos',
        ];

        if (
            in_array(
                $message,
                $membershipErrors,
                true
            )
        ) {
            ResponseHelper::fail(
                $message,
                402
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
            'Desarrollo no encontrado',
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

    public static function upload(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $developmentId =
                (int)($_GET['id'] ?? 0);

            $result =
                DevelopmentImageService::upload(
                    (int)$auth['id'],
                    $developmentId,
                    $_FILES['images'] ?? []
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

            $imageId =
                (int)($_GET['image_id'] ?? 0);

            $result =
                DevelopmentImageService::delete(
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

            $developmentId =
                (int)($_GET['id'] ?? 0);

            $data =
                json_decode(
                    file_get_contents('php://input'),
                    true
                ) ?? [];

            $result =
                DevelopmentImageService::reorder(
                    (int)$auth['id'],
                    $developmentId,
                    $data['images'] ?? []
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
