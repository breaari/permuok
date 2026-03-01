<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Helpers\JwtHelper;
use App\Services\RefreshTokenService;
use PDO;

class RefreshController
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function handle(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $refreshToken = $data['refresh_token'] ?? '';
        if (!is_string($refreshToken) || trim($refreshToken) === '') {
            ResponseHelper::fail('Refresh token requerido', 400);
        }

        // 1) Validar refresh token contra DB (hash + no revocado + no expirado)
        $stored = RefreshTokenService::findValid($refreshToken);
        if (!$stored) {
            ResponseHelper::fail('Refresh token inválido', 401);
        }

        // 2) Rotación: revocar el refresh token usado
        RefreshTokenService::revokeById((int)$stored['id']);

        // 3) Confirmar usuario vigente (y rol actualizado)
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT id, role, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => (int)$stored['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::fail('Usuario no encontrado', 404);
        }
        if ((int)$user['is_active'] !== 1) {
            ResponseHelper::fail('Usuario inactivo', 403);
        }

        // 4) Emitir nuevos tokens
        $newAccessToken = JwtHelper::generateAccessToken([
            'id'   => (int)$user['id'],
            'role' => (int)$user['role'],
        ]);

        $newRefreshToken = RefreshTokenService::issue((int)$user['id']);

        ResponseHelper::ok([
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
        ]);
    }
}