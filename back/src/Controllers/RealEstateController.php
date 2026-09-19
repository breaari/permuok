<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RealEstateService;
use PDOException;
use Throwable;

class RealEstateController
{
    private static function requireRealEstate(): array
    {
        $ctx = AuthMiddleware::handle();

        if (
            (int)($ctx['role'] ?? 0) !== 2
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }

        return $ctx;
    }

    private static function handleError(
        Throwable $e
    ): void {
        if ($e instanceof PDOException) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'RealEstateController'
            );

            return;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación.',
            422
        );
    }

    public static function me(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $data =
                RealEstateService::getMyRealEstate(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function saveProfile(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $result =
                RealEstateService::saveProfile(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function addLicense(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $result =
                RealEstateService::addLicense(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function submitReview(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $result =
                RealEstateService::submitReview(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }
}
