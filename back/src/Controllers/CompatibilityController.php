<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\CompatibilityService;
use Throwable;

class CompatibilityController
{
    public static function recommendations(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result =
                CompatibilityService::listRecommendations(
                    (int)$user['id'],
                    $_GET
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }
    public static function detail(int $compatibilityId): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result =
                CompatibilityService::getRecommendationDetail(
                    (int)$user['id'],
                    $compatibilityId
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function respond(
        int $compatibilityId
    ): void {
        try {
            $user = AuthHelper::requireUser();

            $body = json_decode(
                file_get_contents('php://input'),
                true
            ) ?: [];

            $response = trim(
                (string)($body['response'] ?? '')
            );

            $result =
                CompatibilityService::respond(
                    (int)$user['id'],
                    $compatibilityId,
                    $response
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function feedback(
        int $compatibilityId
    ): void {
        try {
            $user = AuthHelper::requireUser();

            $body = json_decode(
                file_get_contents('php://input'),
                true
            ) ?: [];

            $result =
                CompatibilityService::saveFeedback(
                    (int)$user['id'],
                    $compatibilityId,
                    $body
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function seen(
        int $compatibilityId
    ): void {
        try {
            $user = AuthHelper::requireUser();

            $result =
                CompatibilityService::markAsSeen(
                    (int)$user['id'],
                    $compatibilityId
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }
    private static function error(
        Throwable $e
    ): void {
        $status = (int)$e->getCode();

        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudieron obtener las recomendaciones.',
            $status
        );
    }
}
