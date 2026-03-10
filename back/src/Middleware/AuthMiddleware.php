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
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);
        if (!$authHeader) {
            ResponseHelper::fail('Token requerido', 401);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
            ResponseHelper::fail('Token requerido', 401);
        }
        $token = trim($m[1]);

        try {
            $decoded = JwtHelper::validateAccessToken($token);
        } catch (\Throwable $e) {
            ResponseHelper::fail('Token inválido o expirado', 401);
        }

        $userId = (int)($decoded->id ?? 0);

        if ($userId <= 0) {
            ResponseHelper::fail('Token inválido', 401);
        }

        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT
                id,
                role,
                email,
                first_name,
                last_name,
                phone,
                is_active,
                real_estate_id
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::fail('Usuario no encontrado', 401);
        }

        if ((int)$user['is_active'] !== 1) {
            ResponseHelper::fail('Usuario inactivo', 403);
        }

        return [
            'id' => (int)$user['id'],
            'role' => (int)$user['role'],
            'email' => (string)$user['email'],
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'real_estate_id' => $user['real_estate_id'] !== null ? (int)$user['real_estate_id'] : null,
        ];
    }
}