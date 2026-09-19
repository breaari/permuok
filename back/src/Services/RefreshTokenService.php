<?php

namespace App\Services;

use PDO;

class RefreshTokenService
{
    private const REFRESH_TTL_SECONDS = 30 * 24 * 60 * 60; // 30 días

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    /** Genera refresh token (string) y guarda hash en DB */
    public static function issue(int $userId): string
    {
        $pdo = self::db();

        $token = bin2hex(random_bytes(64));        // token plano
        $hash  = hash('sha256', $token);           // hash guardado

        $stmt = $pdo->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at)
            VALUES (:user_id, :token_hash, :expires_at, NOW())
        ");

        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', time() + self::REFRESH_TTL_SECONDS),
        ]);

        return $token;
    }

    /** Devuelve el registro del refresh token si es válido */
    public static function findValid(string $refreshToken): ?array
    {
        $pdo = self::db();
        $hash = hash('sha256', $refreshToken);

        $stmt = $pdo->prepare("
            SELECT id, user_id, expires_at, revoked_at
            FROM refresh_tokens
            WHERE token_hash = :token_hash
              AND revoked_at IS NULL
              AND expires_at >= NOW()
            LIMIT 1
        ");
        $stmt->execute(['token_hash' => $hash]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function consumeValid(
        string $refreshToken
    ): ?array {
        $pdo = self::db();

        $hash =
            hash(
                'sha256',
                $refreshToken
            );

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
            SELECT
                id,
                user_id,
                expires_at,
                revoked_at
            FROM refresh_tokens
            WHERE token_hash = :token_hash
            LIMIT 1
            FOR UPDATE
        ");

            $stmt->execute([
                'token_hash' => $hash,
            ]);

            $row = $stmt->fetch();

            if (
                !$row ||
                $row['revoked_at'] !== null ||
                strtotime(
                    (string)$row['expires_at']
                ) < time()
            ) {
                $pdo->commit();

                return null;
            }

            /*
         * Creamos el reemplazo dentro de
         * la misma transacción.
         */
            $newToken =
                bin2hex(
                    random_bytes(64)
                );

            $newHash =
                hash(
                    'sha256',
                    $newToken
                );

            $insert = $pdo->prepare("
            INSERT INTO refresh_tokens (
                user_id,
                token_hash,
                expires_at,
                created_at
            ) VALUES (
                :user_id,
                :token_hash,
                :expires_at,
                NOW()
            )
        ");

            $insert->execute([
                'user_id' =>
                (int)$row['user_id'],

                'token_hash' =>
                $newHash,

                'expires_at' =>
                date(
                    'Y-m-d H:i:s',
                    time() +
                        self::REFRESH_TTL_SECONDS
                ),
            ]);

            $newTokenId =
                (int)$pdo->lastInsertId();

            $revoke = $pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE id = :id
              AND revoked_at IS NULL
            LIMIT 1
        ");

            $revoke->execute([
                'id' =>
                (int)$row['id'],
            ]);

            if ($revoke->rowCount() !== 1) {
                $pdo->rollBack();

                return null;
            }

            $pdo->commit();

            $row['new_refresh_token'] =
                $newToken;

            $row['new_refresh_token_id'] =
                $newTokenId;

            return $row;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /** Revoca un refresh token por id (rotación) */
    public static function revokeById(int $id): void
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function revokeAllByUserId(int $userId): void
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        UPDATE refresh_tokens
        SET revoked_at = NOW()
        WHERE user_id = :user_id
          AND revoked_at IS NULL
    ");
        $stmt->execute(['user_id' => $userId]);
    }
}
