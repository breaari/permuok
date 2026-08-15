<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentAmenityService;

class DevelopmentAmenityController
{
    public static function list(): void
    {
        $auth = AuthHelper::requireUser();
        $developmentId = (int)($_GET['id'] ?? 0);

        $result = DevelopmentAmenityService::listByDevelopment((int)$auth['id'], $developmentId);
        ResponseHelper::ok($result);
    }

    public static function replaceAll(): void
    {
        $auth = AuthHelper::requireUser();
        $developmentId = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = DevelopmentAmenityService::replaceAll(
            (int)$auth['id'],
            $developmentId,
            $data['amenities'] ?? []
        );

        ResponseHelper::ok($result);
    }
}