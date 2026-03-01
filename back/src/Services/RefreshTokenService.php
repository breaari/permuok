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

    /** Revoca un refresh token por id (rotación) */
    public static function revokeById(int $id): void
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /** Revoca todos los refresh tokens activos de un usuario (logout total) */
    public static function revokeAllForUser(int $userId): void
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

