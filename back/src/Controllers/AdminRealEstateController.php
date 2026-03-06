<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminRealEstateService;

class AdminRealEstateController
{
    private static function requireAdmin(): array
    {
        $ctx = AuthMiddleware::handle();
        if ((int)($ctx['role'] ?? 0) !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }
        return $ctx;
    }

    // NUEVO: counts para tabs
    public static function counts(): void
    {
        try {
            self::requireAdmin();

            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

            $counts = AdminRealEstateService::counts($q);
            ResponseHelper::ok(['counts' => $counts]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    // NUEVO: list paginada
    public static function list(): void
    {
        try {
            self::requireAdmin();

            $status = (string)($_GET['status'] ?? 'pending');
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

            $data = AdminRealEstateService::list($status, $page, $perPage, $q);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    // NUEVO: validate (approve/reject)
    public static function validate(): void
    {
        try {
            $ctx = self::requireAdmin();

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $realEstateId = (int)($payload['real_estate_id'] ?? 0);
            $action = (string)($payload['action'] ?? '');
            $note = isset($payload['note']) ? (string)$payload['note'] : null;

            if ($realEstateId <= 0) {
                ResponseHelper::fail('real_estate_id requerido', 422);
            }

            $result = AdminRealEstateService::validate((int)$ctx['id'], $realEstateId, $action, $note);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    /*
      LEGACY ENDPOINTS (compatibilidad con tu front actual)
      Podés mantenerlos mientras migrás el front a /admin/real-estates (list) y /validate
    */

    public static function pending(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('pending', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    public static function approved(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('approved', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    public static function rejected(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('rejected', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    // tu front hoy postea acá: /admin/real-estates/approve (approve o reject)
    // lo mantenemos pero internamente llama validate()
    public static function approve(): void
    {
        self::validate();
    }

    public static function detail(): void
    {
        try {
            self::requireAdmin();

            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseHelper::fail('id requerido', 422);
            }

            $data = AdminRealEstateService::getDetail($id);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}
