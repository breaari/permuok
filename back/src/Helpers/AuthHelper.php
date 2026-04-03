<?php

namespace App\Helpers;

class AuthHelper
{
    private static function getAuthorizationHeader(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return trim((string)$value);
                }
            }
        }

        return '';
    }

    public static function requireUser(): array
    {
        $header = self::getAuthorizationHeader();

        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            ResponseHelper::fail('No autenticado', 401);
        }

        $token = trim($matches[1] ?? '');
        if ($token === '') {
            ResponseHelper::fail('Token inválido', 401);
        }

        try {
            $payload = JwtHelper::validateAccessToken($token);
        } catch (\Throwable $e) {
            ResponseHelper::fail('Token expirado o inválido', 401);
        }

        if (!$payload || !isset($payload->id)) {
            ResponseHelper::fail('Token inválido', 401);
        }

        return [
            'id' => (int)$payload->id,
            'role' => isset($payload->role) ? (int)$payload->role : null,
        ];
    }
}