<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\NotificationService;
use Throwable;

class NotificationController
{
    public static function index(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = NotificationService::list(
                (int)$user['id'],
                $_GET
            );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unreadCount(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = NotificationService::unreadCount(
                (int)$user['id']
            );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function markAsRead(int $notificationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = NotificationService::markAsRead(
                (int)$user['id'],
                $notificationId
            );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function markAllAsRead(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = NotificationService::markAllAsRead(
                (int)$user['id']
            );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function error(Throwable $e): void
    {
        $status = (int)($e->getCode() ?: 400);

        if ($status < 100 || $status > 599) {
            $status = 400;
        }

        ResponseHelper::fail($e->getMessage(), $status);
    }
}