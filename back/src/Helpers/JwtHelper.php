<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtHelper
{
    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            throw new Exception('JWT_SECRET no configurado en .env');
        }
        return $secret;
    }

    /* =========================
       ACCESS TOKEN (corto)
    ==========================*/
    public static function generateAccessToken(array $payload): string
    {
        $secret = self::getSecret();

        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 15); // 15 minutos
        $payload['typ'] = 'access';

        return JWT::encode($payload, $secret, 'HS256');
    }

    /* =========================
       REFRESH TOKEN (JWT) - OPCIONAL
       (vos NO lo estás usando si el refresh es en DB)
    ==========================*/
    public static function generateRefreshToken(array $payload): string
    {
        $secret = self::getSecret();

        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 24 * 30); // 30 días
        $payload['typ'] = 'refresh';

        return JWT::encode($payload, $secret, 'HS256');
    }

    /* =========================
       VALIDACIÓN GENERAL
    ==========================*/
    public static function validate(string $token)
    {
        $secret = self::getSecret();
        return JWT::decode($token, new Key($secret, 'HS256'));
    }

    /* =========================
       VALIDAR ACCESS
    ==========================*/
    public static function validateAccessToken(string $token)
    {
        $decoded = self::validate($token);

        $typ = $decoded->typ ?? null;
        if ($typ !== 'access') {
            throw new Exception('Token inválido (no es access)');
        }

        // mínimo esperado en payload
        if (!isset($decoded->id) || !isset($decoded->role)) {
            throw new Exception('Token inválido (payload incompleto)');
        }

        return $decoded;
    }

    /* =========================
       VALIDAR REFRESH (JWT) - OPCIONAL
    ==========================*/
    public static function validateRefreshToken(string $token)
    {
        $decoded = self::validate($token);

        $typ = $decoded->typ ?? null;
        if ($typ !== 'refresh') {
            throw new Exception('Token inválido (no es refresh)');
        }

        return $decoded;
    }
}