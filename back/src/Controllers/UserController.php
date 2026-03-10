<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\UserService;

class UserController
{
    private static function requireRealEstate(): array
    {
        $ctx = AuthMiddleware::handle();

        if ((int)($ctx['role'] ?? 0) !== 2) {
            ResponseHelper::fail('No autorizado', 403);
        }

        return $ctx;
    }

    public static function list(): void
    {
        try {
            $ctx = self::requireRealEstate();
            $data = UserService::listForRealEstate((int)$ctx['id']);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function create(): void
    {
        try {
            $ctx = self::requireRealEstate();
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $data = UserService::createForRealEstate((int)$ctx['id'], $payload);
            ResponseHelper::ok($data, 201);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function updateStatus(): void
    {
        try {
            $ctx = self::requireRealEstate();
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];

            $data = UserService::updateStatusForRealEstate((int)$ctx['id'], $payload);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}