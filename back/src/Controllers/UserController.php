<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\UserService;
use PDOException;
use Throwable;

class UserController
{
    private static function requireRealEstate(): array
    {
        $ctx = AuthMiddleware::handle();

        if (
            (int)($ctx['role'] ?? 0) !== 2
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }

        return $ctx;
    }

    private static function handleError(
        Throwable $e
    ): void {
        /*
     * Los errores internos nunca deben
     * enviarse directamente al cliente.
     */
        if (
            $e instanceof PDOException ||
            $e->getMessage() ===
            'No se pudo generar la contraseña'
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'UserController'
            );

            return;
        }

        $message =
            trim(
                $e->getMessage()
            );

        if (
            $message === 'No autorizado' ||
            $message ===
            'No podés modificar usuarios de otra inmobiliaria' ||
            $message ===
            'Solo podés modificar agentes o inversores'
        ) {
            ResponseHelper::fail(
                $message,
                403
            );

            return;
        }

        if (
            $message ===
            'La inmobiliaria no está vinculada' ||
            $message ===
            'Inmobiliaria no encontrada' ||
            $message ===
            'Usuario no encontrado'
        ) {
            ResponseHelper::fail(
                $message,
                404
            );

            return;
        }

        if (
            $message ===
            'Necesitás una membresía activa para administrar usuarios'
        ) {
            ResponseHelper::fail(
                $message,
                402
            );

            return;
        }

        ResponseHelper::fail(
            $message !== ''
                ? $message
                : 'No se pudo completar la operación.',
            422
        );
    }

    public static function list(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $data =
                UserService::listForRealEstate(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function create(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $data =
                UserService::createForRealEstate(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok(
                $data,
                201
            );
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function updateStatus(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $data =
                UserService::updateStatusForRealEstate(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }
}
