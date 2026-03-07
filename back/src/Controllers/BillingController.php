<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\BillingService;

class BillingController
{
    public static function listPlans(): void
    {
        try {
            $plans = BillingService::listPlans();
            ResponseHelper::ok(['plans' => $plans]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }

    public static function createPreference(): void
    {
        try {
            $ctx = AuthMiddleware::handle();

            // Solo inmobiliaria (role=2) por ahora
            if ((int)$ctx['role'] !== 2) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $planCode = trim((string)($payload['plan_code'] ?? ''));

            if ($planCode === '') {
                ResponseHelper::fail('plan_code requerido', 422);
            }

            $result = BillingService::createPreference((int)$ctx['id'], $planCode);
            ResponseHelper::ok($result, 201);
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: 'Error';

            // ⛔ Doble pago (membresía activa vigente)
            if (str_contains($msg, 'membresía activa')) {
                ResponseHelper::fail($msg, 409);
            }

            // Config faltante (backend mal configurado)
            if (str_contains($msg, 'no configurado')) {
                ResponseHelper::fail($msg, 500);
            }

            // Validaciones / reglas de negocio
            ResponseHelper::fail($msg, 422);
        }
    }

    public static function status(): void
    {
        try {
            $ctx = AuthMiddleware::handle();

            $preferenceId = $_GET['preference_id'] ?? null;
            $externalRef  = $_GET['external_reference'] ?? null;

            if (!$preferenceId && !$externalRef) {
                ResponseHelper::fail('Debés enviar preference_id o external_reference', 422);
            }

            $data = BillingService::getPaymentStatus((int)$ctx['id'], $preferenceId, $externalRef);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function previewPlanChange(): void
    {
        try {
            $ctx = AuthMiddleware::handle();

            if ((int)$ctx['role'] !== 2) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $planCode = trim((string)($payload['target_plan_code'] ?? ''));

            if ($planCode === '') {
                ResponseHelper::fail('target_plan_code requerido', 422);
            }

            $result = BillingService::previewPlanChange((int)$ctx['id'], $planCode);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }

    public static function confirmPlanChange(): void
    {
        try {
            $ctx = AuthMiddleware::handle();

            if ((int)$ctx['role'] !== 2) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $planCode = trim((string)($payload['target_plan_code'] ?? ''));
            $mode = trim((string)($payload['mode'] ?? ''));

            if ($planCode === '') {
                ResponseHelper::fail('target_plan_code requerido', 422);
            }

            if (!in_array($mode, ['immediate', 'next_cycle'], true)) {
                ResponseHelper::fail('mode inválido', 422);
            }

            $result = BillingService::confirmPlanChange((int)$ctx['id'], $planCode, $mode);
            ResponseHelper::ok($result, 201);
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: 'Error';

            if (str_contains($msg, 'no configurado')) {
                ResponseHelper::fail($msg, 500);
            }

            ResponseHelper::fail($msg, 422);
        }
    }

    public static function cancelMembership(): void
    {
        try {
            $ctx = AuthMiddleware::handle();

            if ((int)$ctx['role'] !== 2) {
                ResponseHelper::fail('No autorizado', 403);
            }

            $result = BillingService::cancelMembership((int)$ctx['id']);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 422);
        }
    }
}
