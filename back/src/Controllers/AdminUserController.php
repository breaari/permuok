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
        }

        $message =
            trim($e->getMessage());

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
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación.',
            'AdminUserController'
        );
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

            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseHelper::fail('id requerido', 422);
            }

            $data = AdminUserService::getDetail($id);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function updateStatus(): void
    {
        try {
            $ctx = self::requireAdmin();

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $userId = (int)($payload['user_id'] ?? 0);
            $isActive = null;

            if (
                array_key_exists(
                    'is_active',
                    $payload
                )
            ) {
                $rawIsActive =
                    $payload['is_active'];

                if (
                    $rawIsActive === true ||
                    $rawIsActive === 1 ||
                    $rawIsActive === '1'
                ) {
                    $isActive = 1;
                } elseif (
                    $rawIsActive === false ||
                    $rawIsActive === 0 ||
                    $rawIsActive === '0'
                ) {
                    $isActive = 0;
                }
            }
            $reason = isset($payload['reason']) ? trim((string)$payload['reason']) : null;

            if ($userId <= 0) {
                ResponseHelper::fail('user_id requerido', 422);
            }

            if ($isActive === null) {
                ResponseHelper::fail('is_active requerido', 422);
            }

            if ($isActive === 0 && $reason === '') {
                ResponseHelper::fail('El motivo de desactivación es requerido', 422);
            }

            $data = AdminUserService::updateStatus(
                (int)$ctx['id'],
                $userId,
                $isActive,
                $reason
            );

            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }
}
