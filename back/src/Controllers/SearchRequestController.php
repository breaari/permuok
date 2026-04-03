<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\SearchRequestService;

class SearchRequestController
{
    public static function list(): void
    {
        $auth = AuthHelper::requireUser();

        $filters = [
            'status' => $_GET['status'] ?? null,
            'q' => $_GET['q'] ?? null,
            'limit' => $_GET['limit'] ?? 20,
            'page' => $_GET['page'] ?? 1,
        ];

        $result = SearchRequestService::listMySearchRequests((int)$auth['id'], $filters);
        ResponseHelper::ok($result);
    }

    public static function create(): void
    {
        $auth = AuthHelper::requireUser();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = SearchRequestService::createDraft((int)$auth['id'], $data);
        ResponseHelper::ok($result, 201);
    }

    public static function detail(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = SearchRequestService::getDetail((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function update(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = SearchRequestService::updateDraft((int)$auth['id'], $id, $data);
        ResponseHelper::ok($result);
    }

    public static function publish(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = SearchRequestService::publish((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function pause(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = SearchRequestService::pause((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function archive(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = SearchRequestService::archive((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }

    public static function delete(): void
    {
        $auth = AuthHelper::requireUser();
        $id = (int)($_GET['id'] ?? 0);

        $result = SearchRequestService::delete((int)$auth['id'], $id);
        ResponseHelper::ok($result);
    }
}