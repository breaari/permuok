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
         * Consume el token anterior y crea
         * su reemplazo en una transacción.
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

        $newTokenId =
            (int)$stored['new_refresh_token_id'];

        try {
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
                RefreshTokenService::revokeAllByUserId(
                    (int)$stored['user_id']
                );

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

            RefreshTokenCookieHelper::write(
                (string)$stored['new_refresh_token']
            );

            ResponseHelper::ok([
                'access_token' =>
                $newAccessToken,
            ]);
        } catch (\Throwable $e) {
            /*
             * Si no pudimos entregar el token
             * nuevo, evitamos dejarlo activo.
             */
            try {
                RefreshTokenService::revokeById(
                    $newTokenId
                );
            } catch (\Throwable $cleanupError) {
                error_log(
                    'No se pudo revocar el refresh token ' .
                        'después de una rotación fallida: ' .
                        $cleanupError->getMessage()
                );
            }

            RefreshTokenCookieHelper::clear();

            throw $e;
        }
    }
}
