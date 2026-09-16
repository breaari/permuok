<?php

namespace App\Controllers;

use App\Helpers\JwtHelper;
use App\Helpers\RefreshTokenCookieHelper;
use App\Helpers\ResponseHelper;
use App\Services\RefreshTokenService;
use PDO;

class RefreshController
{
    private static function db(): PDO
    {
        require_once __DIR__ .
            '/../../db.php';

        return pdo();
    }

    public static function handle(): void
    {
        $refreshToken =
            RefreshTokenCookieHelper::read();

        if ($refreshToken === null) {
            RefreshTokenCookieHelper::clear();

            ResponseHelper::fail(
                'Refresh token requerido',
                401
            );
        }

        /*
         * El token solo puede consumirse una vez.
         */
        $stored =
            RefreshTokenService::consumeValid(
                $refreshToken
            );

        if (!$stored) {
            RefreshTokenCookieHelper::clear();

            ResponseHelper::fail(
                'Refresh token inválido',
                401
            );
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                role,
                is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'id' =>
            (int)$stored['user_id'],
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            RefreshTokenCookieHelper::clear();

            ResponseHelper::fail(
                'Usuario no encontrado',
                401
            );
        }

        if ((int)$user['is_active'] !== 1) {
            RefreshTokenService::revokeAllByUserId(
                    (int)$user['id']
                );

            RefreshTokenCookieHelper::clear();

            ResponseHelper::fail(
                'Usuario inactivo',
                403
            );
        }

        $newAccessToken =
            JwtHelper::generateAccessToken([
                'id' =>
                (int)$user['id'],

                'role' =>
                (int)$user['role'],
            ]);

        $newRefreshToken =
            RefreshTokenService::issue(
                (int)$user['id']
            );

        RefreshTokenCookieHelper::write(
            $newRefreshToken
        );

        ResponseHelper::ok([
            'access_token' =>
            $newAccessToken,
        ]);
    }
}
