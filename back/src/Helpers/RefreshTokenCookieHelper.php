<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

class RefreshTokenCookieHelper
{
    private const COOKIE_NAME =
        'permuok_refresh_token';

    private const TTL_SECONDS =
        30 * 24 * 60 * 60;

    private static function isHttps(): bool
    {
        $https = strtolower(
            trim(
                (string)(
                    $_SERVER['HTTPS']
                    ?? ''
                )
            )
        );

        if (
            $https === 'on'
            || $https === '1'
        ) {
            return true;
        }

        $forwardedProto = strtolower(
            trim(
                explode(
                    ',',
                    (string)(
                        $_SERVER[
                            'HTTP_X_FORWARDED_PROTO'
                        ]
                        ?? ''
                    )
                )[0]
            )
        );

        if ($forwardedProto === 'https') {
            return true;
        }

        return (int)(
            $_SERVER['SERVER_PORT']
            ?? 0
        ) === 443;
    }

    public static function write(
        string $refreshToken
    ): void {
        $refreshToken =
            trim($refreshToken);

        if ($refreshToken === '') {
            throw new RuntimeException(
                'Refresh token vacío'
            );
        }

        $written = setcookie(
            self::COOKIE_NAME,
            $refreshToken,
            [
                'expires' =>
                    time() + self::TTL_SECONDS,

                'path' =>
                    '/',

                'secure' =>
                    self::isHttps(),

                'httponly' =>
                    true,

                'samesite' =>
                    'Strict',
            ]
        );

        if (!$written) {
            throw new RuntimeException(
                'No se pudo crear la cookie de sesión'
            );
        }
    }

    public static function read(): ?string
    {
        $token = trim(
            (string)(
                $_COOKIE[
                    self::COOKIE_NAME
                ]
                ?? ''
            )
        );

        return $token !== ''
            ? $token
            : null;
    }

    public static function clear(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' =>
                    time() - 3600,

                'path' =>
                    '/',

                'secure' =>
                    self::isHttps(),

                'httponly' =>
                    true,

                'samesite' =>
                    'Strict',
            ]
        );
    }
}