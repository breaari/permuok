<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\NotificationService;
use Throwable;

class NotificationController
{
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

    private static function queryUnread(): int
    {
        if (
            !array_key_exists(
                'unread',
                $_GET
            )
        ) {
            return 0;
        }

        $value =
            $_GET['unread'];

        if (
            !is_string($value) &&
            !is_int($value) &&
            !is_bool($value)
        ) {
            throw new \Exception(
                'El parámetro unread debe ser 0 o 1.',
                422
            );
        }

        if (
            in_array(
                $value,
                [
                    1,
                    true,
                    '1',
                    'true',
                ],
                true
            )
        ) {
            return 1;
        }

        if (
            in_array(
                $value,
                [
                    0,
                    false,
                    '0',
                    'false',
                ],
                true
            )
        ) {
            return 0;
        }

        throw new \Exception(
            'El parámetro unread debe ser 0 o 1.',
            422
        );
    }

    public static function index(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            $query = [
                'page' =>
                self::queryPositiveInt(
                    'page',
                    1,
                    1000000
                ),

                'limit' =>
                self::queryPositiveInt(
                    'limit',
                    20,
                    50
                ),

                'unread' =>
                self::queryUnread(),
            ];

            $result =
                NotificationService::list(
                    (int)$user['id'],
                    $query
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unreadCount(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            $result =
                NotificationService::unreadCount(
                    (int)$user['id']
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function markAsRead(
        int $notificationId
    ): void {
        try {
            if ($notificationId <= 0) {
                throw new \Exception(
                    'Notificación inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            $result =
                NotificationService::markAsRead(
                    (int)$user['id'],
                    $notificationId
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function markAllAsRead(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            $result =
                NotificationService::markAllAsRead(
                    (int)$user['id']
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function error(
        Throwable $e
    ): void {
        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación con las notificaciones.',
            'NotificationController'
        );
    }
}
