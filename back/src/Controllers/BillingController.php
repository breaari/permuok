<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\BillingService;

class BillingController
{

    private static function error(
        \Throwable $e
    ): void {
        $message = trim(
            $e->getMessage()
        );

        $conflictMessages = [
            'Ya se está procesando otra operación',
            'Ya se está generando una suscripción',
            'Ya tenés una membresía activa',
            'La suscripción ya fue autorizada',
            'Ese plan ya es el plan actual',
            'La renovación de la membresía está cancelada',
        ];

        foreach ($conflictMessages as $fragment) {
            if (str_contains($message, $fragment)) {
                ResponseHelper::fail(
                    $message,
                    409
                );

                return;
            }
        }

        $validationMessages = [
            'Plan no encontrado',
            'Plan destino no encontrado',
            'Tu perfil todavía no está habilitado',
            'Los upgrades deben aplicarse de inmediato',
            'Los downgrades deben programarse',
            'Falta preference_id o external_reference',
            'Inmobiliaria no vinculada',
            'La inmobiliaria debe estar aprobada',
            'No hay una membresía activa para operar este cambio',
        ];

        foreach ($validationMessages as $fragment) {
            if (str_contains($message, $fragment)) {
                ResponseHelper::fail(
                    $message,
                    422
                );

                return;
            }
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación de facturación.',
            'BillingController'
        );
    }


    public static function listPlans(): void
    {
        try {
            $plans = BillingService::listPlans();
            ResponseHelper::ok(['plans' => $plans]);
        } catch (\Throwable $e) {
            ResponseHelper::fromThrowable(
                $e
            );
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
            self::error($e);
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
            self::error($e);
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
            self::error($e);
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
            self::error($e);
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
            self::error($e);
        }
    }
}
