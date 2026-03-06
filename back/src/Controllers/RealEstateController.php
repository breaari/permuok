<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RealEstateService;

class RealEstateController
{
    public static function me(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            $data = RealEstateService::getMyRealEstate((int)$ctx['id']);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function saveProfile(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = RealEstateService::saveProfile((int)$ctx['id'], $payload);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function addLicense(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = RealEstateService::addLicense((int)$ctx['id'], $payload);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function submitReview(): void
    {
        try {
            $ctx = AuthMiddleware::handle();
            $result = RealEstateService::submitReview((int)$ctx['id']);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}
