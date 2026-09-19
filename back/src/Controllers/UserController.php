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
         * Nunca mostramos errores de PDO,
         * consultas SQL ni errores internos
         * de generación de contraseñas.
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

        /*
         * UserService utiliza excepciones comunes
         * para sus validaciones funcionales.
         */
        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación.',
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
