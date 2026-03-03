<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RealEstateService;
use App\Services\AdminRealEstateService;

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
            ResponseHelper::ok($result, 201);
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

      public static function approve(): void
    {
        try {
            $ctx = AuthMiddleware::handle(); // debe ser super admin
            if ((int)$ctx['role'] !== 1) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $realEstateId = (int)($payload['real_estate_id'] ?? 0);

            if ($realEstateId <= 0) {
                ResponseHelper::fail('real_estate_id requerido', 422);
            }

            $result = AdminRealEstateService::approve($realEstateId, (int)$ctx['id']);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}