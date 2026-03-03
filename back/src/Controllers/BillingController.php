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
}
