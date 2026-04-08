<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\SearchRequestService;

class SearchRequestController
{
    public static function list(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'status' => $_GET['status'] ?? null,
                'q' => $_GET['q'] ?? null,
                'limit' => $_GET['limit'] ?? 20,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = SearchRequestService::listMySearchRequests((int)$auth['id'], $filters);
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
                'limit' => $_GET['limit'] ?? 20,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = SearchRequestService::listExploreSearchRequests((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function create(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = SearchRequestService::createDraft((int)$auth['id'], $data);
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

            $result = SearchRequestService::getDetail((int)$auth['id'], $id);
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

            $result = SearchRequestService::getExploreDetail((int)$auth['id'], $id);
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

            $result = SearchRequestService::updateDraft((int)$auth['id'], $id, $data);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }

    public static function publish(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = SearchRequestService::publish((int)$auth['id'], $id);
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

            $result = SearchRequestService::pause((int)$auth['id'], $id);
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

            $result = SearchRequestService::archive((int)$auth['id'], $id);
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

            $result = SearchRequestService::delete((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }
}