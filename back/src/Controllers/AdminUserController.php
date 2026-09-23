<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminUserService;

class AdminUserController
{
    private static function requireAdmin(): array
    {
        $ctx = AuthMiddleware::handle();

        if ((int)($ctx['role'] ?? 0) !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }

        return $ctx;
    }

    private static function handleError(
        \Throwable $e
    ): void {
        if ($e instanceof \PDOException) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'AdminUserController'
            );

            return;
        }

        $message =
            trim($e->getMessage());

        $code =
            (int)$e->getCode();

        if (
            $code >= 400 &&
            $code <= 499
        ) {
            ResponseHelper::fail(
                $message !== ''
                    ? $message
                    : 'La solicitud no es válida.',
                $code
            );

            return;
        }

        $status = match ($message) {
            'Usuario no encontrado' =>
            404,

            'No podés modificar un super admin',
            'No podés modificar tu propio estado' =>
            403,

            'Estado inválido',
            'El motivo de desactivación es requerido' =>
            422,

            default =>
            500,
        };

        if ($status !== 500) {
            ResponseHelper::fail(
                $message,
                $status
            );

            return;
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación.',
            'AdminUserController'
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

        $raw = trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto',
                422
            );
        }

        try {
            $payload = json_decode(
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

        if (!is_array($payload)) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        return $payload;
    }

    public static function counts(): void
    {
        try {
            self::requireAdmin();

            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
            $status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
            $membership = isset($_GET['membership']) ? (string)$_GET['membership'] : 'all';

            $counts = AdminUserService::counts($q, $status, $membership);
            ResponseHelper::ok(['counts' => $counts]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function list(): void
    {
        try {
            self::requireAdmin();

            $role = (string)($_GET['role'] ?? 'real_estate');
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
            $status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
            $membership = isset($_GET['membership']) ? (string)$_GET['membership'] : 'all';

            $data = AdminUserService::list(
                $role,
                $page,
                $perPage,
                $q,
                $status,
                $membership
            );

            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function detail(): void
    {
        try {
            self::requireAdmin();

            $id = filter_var(
                $_GET['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($id === false) {
                throw new \Exception(
                    'Identificador de usuario inválido',
                    422
                );
            }

            $data =
                AdminUserService::getDetail(
                    (int)$id
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function updateStatus(): void
    {
        try {
            $ctx =
                self::requireAdmin();

            $payload =
                self::readJsonObject();

            $userId = filter_var(
                $payload['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($userId === false) {
                throw new \Exception(
                    'user_id inválido',
                    422
                );
            }

            if (
                !array_key_exists(
                    'is_active',
                    $payload
                )
            ) {
                throw new \Exception(
                    'is_active requerido',
                    422
                );
            }

            $rawIsActive =
                $payload['is_active'];

            if (
                in_array(
                    $rawIsActive,
                    [true, 1, '1'],
                    true
                )
            ) {
                $isActive = 1;
            } elseif (
                in_array(
                    $rawIsActive,
                    [false, 0, '0'],
                    true
                )
            ) {
                $isActive = 0;
            } else {
                throw new \Exception(
                    'is_active inválido',
                    422
                );
            }

            $reason = null;

            if (
                array_key_exists(
                    'reason',
                    $payload
                ) &&
                $payload['reason'] !== null
            ) {
                if (
                    !is_string(
                        $payload['reason']
                    )
                ) {
                    throw new \Exception(
                        'El motivo de desactivación es inválido',
                        422
                    );
                }

                $reason =
                    trim($payload['reason']);

                if (
                    mb_strlen($reason) > 1000
                ) {
                    throw new \Exception(
                        'El motivo de desactivación es demasiado extenso',
                        422
                    );
                }
            }

            if (
                $isActive === 0 &&
                ($reason === null || $reason === '')
            ) {
                throw new \Exception(
                    'El motivo de desactivación es requerido',
                    422
                );
            }

            /*
         * Al reactivar un usuario no conservamos
         * accidentalmente un motivo nuevo.
         */
            if ($isActive === 1) {
                $reason = null;
            }

            $data =
                AdminUserService::updateStatus(
                    (int)$ctx['id'],
                    (int)$userId,
                    $isActive,
                    $reason
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }
}
