<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentUnitTypeService;

class DevelopmentUnitTypeController
{
    public static function list(): void
    {
        $auth = AuthHelper::requireUser();
        $developmentId = (int)($_GET['id'] ?? 0);

        $result = DevelopmentUnitTypeService::listByDevelopment((int)$auth['id'], $developmentId);
        ResponseHelper::ok($result);
    }

    public static function create(): void
    {
        $auth = AuthHelper::requireUser();
        $developmentId = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = DevelopmentUnitTypeService::create((int)$auth['id'], $developmentId, $data);
        ResponseHelper::ok($result, 201);
    }

    public static function update(): void
    {
        $auth = AuthHelper::requireUser();
        $unitTypeId = (int)($_GET['unit_type_id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = DevelopmentUnitTypeService::update((int)$auth['id'], $unitTypeId, $data);
        ResponseHelper::ok($result);
    }

    public static function delete(): void
    {
        $auth = AuthHelper::requireUser();
        $unitTypeId = (int)($_GET['unit_type_id'] ?? 0);

        $result = DevelopmentUnitTypeService::delete((int)$auth['id'], $unitTypeId);
        ResponseHelper::ok($result);
    }
}