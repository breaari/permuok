<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\PropertyService;

class PropertyController
{
    public static function list(): void
    {
        $auth = AuthHelper::requireUser();

        $filters = [
            'status' => $_GET['status'] ?? null,
            'q' => $_GET['q'] ?? null,
            'limit' => $_GET['limit'] ?? 5,
            'page' => $_GET['page'] ?? 1,
        ];

        $result = PropertyService::listMyProperties((int)$auth['id'], $filters);
        ResponseHelper::ok($result);
    }

    public static function create(): void
    {
        $auth = AuthHelper::requireUser();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = PropertyService::createDraft((int)$auth['id'], $data);
        ResponseHelper::ok($result, 201);
    }

    public static function detail(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = PropertyService::getDetail((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function update(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = PropertyService::updateDraft((int)$auth['id'], $id, $data);
        ResponseHelper::ok($result);
    }

    public static function saveRequirements(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = PropertyService::replaceRequirements((int)$auth['id'], $id, $data);
        ResponseHelper::ok($result);
    }

    public static function publish(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = PropertyService::publish((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function pause(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = PropertyService::pause((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function archive(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = PropertyService::archive((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function close(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $closingType = trim((string)($data['closing_type'] ?? ''));
        $result = PropertyService::close((int)$auth['id'], $id, $closingType);
        ResponseHelper::ok($result);
    }

    public static function delete(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = PropertyService::delete((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }
}
