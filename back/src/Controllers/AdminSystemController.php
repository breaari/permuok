<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminSystemService;

class AdminSystemController
{
    private static function requireAdmin(): void
    {
        $ctx =
            AuthMiddleware::handle();

        if (
            (int)(
                $ctx['role']
                ?? 0
            ) !== 1
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }
    }

    private static function queryString(
        string $name,
        string $default,
        int $maxLength = 50
    ): string {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return $default;
        }

        $value =
            $_GET[$name];

        if (!is_string($value)) {
            throw new \Exception(
                "El parámetro {$name} tiene un formato inválido.",
                422
            );
        }

        $value =
            trim($value);

        $length =
            function_exists('mb_strlen')
            ? mb_strlen(
                $value,
                'UTF-8'
            )
            : strlen($value);

        if (
            $value === '' ||
            $length > $maxLength
        ) {
            throw new \Exception(
                "El parámetro {$name} tiene un formato inválido.",
                422
            );
        }

        return $value;
    }

    private static function queryPositiveInt(
        string $name,
        int $default,
        int $maximum
    ): int {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return $default;
        }

        $value =
            $_GET[$name];

        if (
            !is_int($value) &&
            !(
                is_string($value) &&
                preg_match(
                    '/^[1-9][0-9]*$/',
                    $value
                ) === 1
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} debe ser un entero positivo.",
                422
            );
        }

        $normalized =
            (int)$value;

        if (
            $normalized < 1 ||
            $normalized > $maximum
        ) {
            throw new \Exception(
                "El parámetro {$name} está fuera del rango permitido.",
                422
            );
        }

        return $normalized;
    }

    public static function compatibilityJobs(): void
    {
        try {
            self::requireAdmin();

            $status =
                self::queryString(
                    'status',
                    'failed'
                );

            if (
                !in_array(
                    $status,
                    [
                        'pending',
                        'processing',
                        'completed',
                        'failed',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'El estado de compatibilidad no es válido.',
                    422
                );
            }

            $page =
                self::queryPositiveInt(
                    'page',
                    1,
                    1000000
                );

            $limit =
                self::queryPositiveInt(
                    'limit',
                    20,
                    100
                );

            $result =
                AdminSystemService::compatibilityJobs(
                    $status,
                    $page,
                    $limit
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudieron cargar los trabajos de compatibilidad.',
                'AdminSystemController::compatibilityJobs'
            );
        }
    }

    public static function emailJobs(): void
    {
        try {
            self::requireAdmin();

            $status =
                self::queryString(
                    'status',
                    'failed'
                );

            if (
                !in_array(
                    $status,
                    [
                        'pending',
                        'processing',
                        'sent',
                        'failed',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'El estado del trabajo de correo no es válido.',
                    422
                );
            }

            $page =
                self::queryPositiveInt(
                    'page',
                    1,
                    1000000
                );

            $limit =
                self::queryPositiveInt(
                    'limit',
                    20,
                    100
                );

            $result =
                AdminSystemService::emailJobs(
                    $status,
                    $page,
                    $limit
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudieron cargar los trabajos de correo.',
                'AdminSystemController::emailJobs'
            );
        }
    }
}
