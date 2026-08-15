<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\PropertyService;
use App\Services\MembershipGuard;

class PropertyController
{
    public static function list(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'status' => $_GET['status'] ?? null,
                'q' => $_GET['q'] ?? null,
                'limit' => $_GET['limit'] ?? 5,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = PropertyService::listMyProperties((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function explore(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'q' => $_GET['q'] ?? null,
                'property_type' => $_GET['property_type'] ?? null,
                'limit' => $_GET['limit'] ?? 6,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = PropertyService::listExploreProperties((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function create(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$auth['id']);

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::createDraft((int)$auth['id'], $data);
            ResponseHelper::ok($result, 201);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function detail(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::getDetail((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 404);
        }
    }

    public static function exploreDetail(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::getExploreDetail((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 404);
        }
    }

    public static function update(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::updateDraft((int)$auth['id'], $id, $data);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function saveRequirements(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::replaceRequirements((int)$auth['id'], $id, $data);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function publish(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$auth['id']);

            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::publish((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function pause(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::pause((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function archive(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::archive((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function close(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $closingType = trim((string)($data['closing_type'] ?? ''));
            $result = PropertyService::close((int)$auth['id'], $id, $closingType);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function delete(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::delete((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }
}