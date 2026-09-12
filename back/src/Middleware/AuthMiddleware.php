<?php

namespace App\Middleware;

use App\Helpers\JwtHelper;
use App\Helpers\ResponseHelper;
use PDO;

class AuthMiddleware
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function handle(): array
    {
        $headers =
            function_exists('getallheaders')
            ? getallheaders()
            : [];

        $authHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? null;

        if (!$authHeader) {
            ResponseHelper::fail(
                'Token requerido',
                401
            );
        }

        if (
            !preg_match(
                '/^Bearer\s+(.+)$/i',
                trim($authHeader),
                $m
            )
        ) {
            ResponseHelper::fail(
                'Token requerido',
                401
            );
        }

        $token = trim($m[1]);

        try {
            $decoded =
                JwtHelper::validateAccessToken(
                    $token
                );
        } catch (\Throwable $e) {
            ResponseHelper::fail(
                'Token inválido o expirado',
                401
            );
        }

        $userId =
            (int)($decoded->id ?? 0);

        if ($userId <= 0) {
            ResponseHelper::fail(
                'Token inválido',
                401
            );
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.role,
            u.email,
            u.first_name,
            u.last_name,
            u.phone,
            u.is_active,
            u.real_estate_id,

            r.status AS real_estate_status,
            r.profile_status AS real_estate_profile_status

        FROM users u

        LEFT JOIN real_estates r
            ON r.id = u.real_estate_id
           AND r.deleted_at IS NULL

        WHERE u.id = :id
          AND u.deleted_at IS NULL

        LIMIT 1
    ");

        $stmt->execute([
            'id' => $userId,
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::fail(
                'Usuario no encontrado',
                401
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Usuario individual desactivado
    |--------------------------------------------------------------------------
    */

        if ((int)$user['is_active'] !== 1) {
            ResponseHelper::fail(
                'Usuario inactivo',
                403
            );
        }

        $role =
            (int)$user['role'];

        $realEstateId =
            $user['real_estate_id'] !== null
            ? (int)$user['real_estate_id']
            : null;

        $realEstateStatus =
            $user['real_estate_status'] !== null
            ? (int)$user['real_estate_status']
            : null;

        $realEstateProfileStatus =
            $user['real_estate_profile_status'] !== null
            ? (int)$user['real_estate_profile_status']
            : null;

        /*
    |--------------------------------------------------------------------------
    | Suspensión administrativa de inmobiliaria
    |--------------------------------------------------------------------------
    |
    | profile_status:
    |
    | 0 = draft
    | 1 = initial review
    | 2 = approved
    | 3 = rejected
    | 4 = changes pending
    |
    | Una inmobiliaria en borrador/revisión/rechazada puede seguir entrando
    | para completar o corregir su perfil.
    |
    | Si ya estaba aprobada y status = 0, significa suspensión administrativa.
    |
    */

        if (
            $role === 2 &&
            in_array(
                $realEstateProfileStatus,
                [2, 4],
                true
            ) &&
            $realEstateStatus !== 1
        ) {
            ResponseHelper::fail(
                'Inmobiliaria suspendida',
                403
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Agentes e inversores
    |--------------------------------------------------------------------------
    |
    | No pueden operar si la inmobiliaria a la que pertenecen está suspendida
    | o dejó de existir.
    |
    */

        if (
            in_array(
                $role,
                [3, 4],
                true
            )
        ) {
            if (
                !$realEstateId ||
                $realEstateStatus !== 1
            ) {
                ResponseHelper::fail(
                    'Inmobiliaria suspendida',
                    403
                );
            }
        }

        return [
            'id' =>
            (int)$user['id'],

            'role' =>
            $role,

            'email' =>
            (string)$user['email'],

            'first_name' =>
            $user['first_name'] ?? null,

            'last_name' =>
            $user['last_name'] ?? null,

            'phone' =>
            $user['phone'] ?? null,

            'real_estate_id' =>
            $realEstateId,
        ];
    }
}
