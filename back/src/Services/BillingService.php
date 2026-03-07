<?php

namespace App\Services;

use PDO;

class BillingService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function listPlans(): array
    {
        $pdo = self::db();

        $st = $pdo->query("
            SELECT id, code, name, price_ars, duration_days, max_agents, max_investors, can_publish_projects
            FROM plans
            WHERE is_active = 1 AND deleted_at IS NULL
            ORDER BY price_ars ASC
        ");

        return $st->fetchAll() ?: [];
    }

    public static function createPreference(int $userId, string $planCode): array
    {
        $pdo = self::db();

        $mpToken = trim((string)($_ENV['MP_ACCESS_TOKEN'] ?? ''));
        if ($mpToken === '') {
            throw new \Exception("MP_ACCESS_TOKEN no configurado");
        }

        $frontUrl = trim((string)($_ENV['FRONT_URL'] ?? ''));
        if ($frontUrl === '') {
            throw new \Exception("FRONT_URL no configurado");
        }
        $frontUrl = rtrim($frontUrl, '/');

        $notificationUrl = trim((string)($_ENV['MP_NOTIFICATION_URL'] ?? ''));
        if ($notificationUrl === '') {
            throw new \Exception("MP_NOTIFICATION_URL no configurado");
        }

        $user = self::getValidRealEstateUser($userId);
        $realEstate = self::getApprovedRealEstate((int)$user['real_estate_id']);

        $activeMembership = self::getActiveMembership((int)$user['real_estate_id']);
        if ($activeMembership) {
            $until = $activeMembership['end_date'] ?? null;
            throw new \Exception("Ya tenés una membresía activa hasta {$until}. No podés generar un nuevo pago.");
        }

        $plan = self::getPlanByCode($planCode);
        if (!$plan) {
            throw new \Exception("Plan no encontrado");
        }

        $externalRef = "re{$user['real_estate_id']}-u{$userId}-p{$plan['id']}-new-" . bin2hex(random_bytes(6));

        $st = $pdo->prepare("
            INSERT INTO payments
            (real_estate_id, user_id, plan_id, external_reference, amount_ars, currency, status)
            VALUES (:re, :u, :p, :ext, :amt, 'ARS', 'created')
        ");
        $st->execute([
            're' => (int)$user['real_estate_id'],
            'u'  => $userId,
            'p'  => (int)$plan['id'],
            'ext' => $externalRef,
            'amt' => (int)$plan['price_ars'],
        ]);

        $paymentRowId = (int)$pdo->lastInsertId();

        $pref = self::buildMercadoPagoPreference(
            title: (string)$plan['name'],
            amount: (int)$plan['price_ars'],
            externalRef: $externalRef
        );

        $resp = MercadoPagoClient::createPreference($pref);

        if (empty($resp['id'])) {
            throw new \Exception("No se pudo crear la preferencia");
        }

        $isTest = str_starts_with($mpToken, 'TEST-');
        $initPoint = $isTest
            ? ($resp['sandbox_init_point'] ?? null)
            : ($resp['init_point'] ?? null);

        if (!$initPoint) {
            throw new \Exception("No se pudo obtener el link de pago");
        }

        $st = $pdo->prepare("
            UPDATE payments
            SET preference_id=:pid, status='pending'
            WHERE id=:id
            LIMIT 1
        ");
        $st->execute([
            'pid' => (string)$resp['id'],
            'id'  => $paymentRowId
        ]);

        return [
            'payment_id' => $paymentRowId,
            'preference_id' => (string)$resp['id'],
            'init_point' => (string)$initPoint,
            'external_reference' => $externalRef,
        ];
    }

    public static function previewPlanChange(int $userId, string $targetPlanCode): array
    {
        $user = self::getValidRealEstateUser($userId);
        $membership = self::getRequiredActiveMembership((int)$user['real_estate_id']);
        $currentPlan = self::getPlanById((int)$membership['plan_id']);
        $targetPlan = self::getPlanByCode($targetPlanCode);

        if (!$targetPlan) {
            throw new \Exception("Plan destino no encontrado");
        }

        if ((int)$currentPlan['id'] === (int)$targetPlan['id']) {
            throw new \Exception("Ese plan ya es el plan actual");
        }

        $changeType = self::resolveChangeType(
            (int)$currentPlan['price_ars'],
            (int)$targetPlan['price_ars']
        );

        if ($changeType === 'upgrade') {
            $daysTotal = max(1, self::daysBetween(
                (string)$membership['start_date'],
                (string)$membership['end_date']
            ));
            $daysRemaining = max(1, self::daysRemaining((string)$membership['end_date']));

            $priceDiff = max(0, (int)$targetPlan['price_ars'] - (int)$currentPlan['price_ars']);
            $proratedAmount = (int)ceil(($priceDiff * $daysRemaining) / $daysTotal);

            return [
                'change_type' => 'upgrade',
                'mode' => 'immediate',
                'current_plan' => $currentPlan,
                'target_plan' => $targetPlan,
                'days_total' => $daysTotal,
                'days_remaining' => $daysRemaining,
                'amount_now_ars' => $proratedAmount,
                'effective_at' => date('Y-m-d H:i:s'),
                'message' => 'El upgrade se aplicará de inmediato y se cobrará el diferencial proporcional.',
            ];
        }

        return [
            'change_type' => 'downgrade',
            'mode' => 'next_cycle',
            'current_plan' => $currentPlan,
            'target_plan' => $targetPlan,
            'amount_now_ars' => 0,
            'effective_at' => $membership['end_date'],
            'message' => 'El cambio se programará para la próxima renovación.',
        ];
    }

    public static function confirmPlanChange(int $userId, string $targetPlanCode, string $mode): array
    {
        $pdo = self::db();

        $user = self::getValidRealEstateUser($userId);
        $membership = self::getRequiredActiveMembership((int)$user['real_estate_id']);
        $currentPlan = self::getPlanById((int)$membership['plan_id']);
        $targetPlan = self::getPlanByCode($targetPlanCode);

        if (!$targetPlan) {
            throw new \Exception("Plan destino no encontrado");
        }

        if ((int)$currentPlan['id'] === (int)$targetPlan['id']) {
            throw new \Exception("Ese plan ya es el plan actual");
        }

        $changeType = self::resolveChangeType(
            (int)$currentPlan['price_ars'],
            (int)$targetPlan['price_ars']
        );

        if ($changeType === 'upgrade' && $mode !== 'immediate') {
            throw new \Exception("Los upgrades deben aplicarse de inmediato");
        }

        if ($changeType === 'downgrade' && $mode !== 'next_cycle') {
            throw new \Exception("Los downgrades deben programarse para el próximo ciclo");
        }

        if ($changeType === 'downgrade') {
            $st = $pdo->prepare("
                UPDATE memberships
                SET
                    scheduled_plan_id = :scheduled_plan_id,
                    scheduled_change_at = :scheduled_change_at
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute([
                'scheduled_plan_id' => (int)$targetPlan['id'],
                'scheduled_change_at' => $membership['end_date'] . ' 00:00:00',
                'id' => (int)$membership['id'],
            ]);

            return [
                'scheduled' => true,
                'change_type' => 'downgrade',
                'mode' => 'next_cycle',
                'current_plan' => $currentPlan,
                'target_plan' => $targetPlan,
                'effective_at' => $membership['end_date'],
                'message' => 'El cambio quedó programado para la próxima renovación.',
            ];
        }

        $preview = self::previewPlanChange($userId, $targetPlanCode);
        $amountNow = (int)($preview['amount_now_ars'] ?? 0);

        if ($amountNow <= 0) {
            throw new \Exception("No se pudo calcular el importe del upgrade");
        }

        $externalRef = "re{$user['real_estate_id']}-u{$userId}-p{$targetPlan['id']}-upgrade-" . bin2hex(random_bytes(6));

        $st = $pdo->prepare("
            INSERT INTO payments
            (real_estate_id, user_id, plan_id, external_reference, amount_ars, currency, status)
            VALUES (:re, :u, :p, :ext, :amt, 'ARS', 'created')
        ");
        $st->execute([
            're' => (int)$user['real_estate_id'],
            'u'  => $userId,
            'p'  => (int)$targetPlan['id'],
            'ext' => $externalRef,
            'amt' => $amountNow,
        ]);

        $paymentRowId = (int)$pdo->lastInsertId();

        $pref = self::buildMercadoPagoPreference(
            title: 'Upgrade de plan - ' . (string)$targetPlan['name'],
            amount: $amountNow,
            externalRef: $externalRef
        );

        $resp = MercadoPagoClient::createPreference($pref);

        if (empty($resp['id'])) {
            throw new \Exception("No se pudo crear la preferencia");
        }

        $mpToken = trim((string)($_ENV['MP_ACCESS_TOKEN'] ?? ''));
        $isTest = str_starts_with($mpToken, 'TEST-');
        $initPoint = $isTest
            ? ($resp['sandbox_init_point'] ?? null)
            : ($resp['init_point'] ?? null);

        if (!$initPoint) {
            throw new \Exception("No se pudo obtener el link de pago");
        }

        $st = $pdo->prepare("
            UPDATE payments
            SET preference_id=:pid, status='pending'
            WHERE id=:id
            LIMIT 1
        ");
        $st->execute([
            'pid' => (string)$resp['id'],
            'id'  => $paymentRowId
        ]);

        return [
            'payment_id' => $paymentRowId,
            'preference_id' => (string)$resp['id'],
            'init_point' => (string)$initPoint,
            'external_reference' => $externalRef,
            'change_type' => 'upgrade',
            'mode' => 'immediate',
            'amount_now_ars' => $amountNow,
        ];
    }

    public static function cancelMembership(int $userId): array
    {
        $pdo = self::db();

        $user = self::getValidRealEstateUser($userId);
        $membership = self::getRequiredActiveMembership((int)$user['real_estate_id']);

        $st = $pdo->prepare("
            UPDATE memberships
            SET
                cancel_at_period_end = 1,
                cancelled_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            'id' => (int)$membership['id'],
        ]);

        return [
            'cancelled' => true,
            'effective_until' => $membership['end_date'],
            'message' => 'La cancelación quedó programada al fin del período actual.',
        ];
    }

    public static function getPaymentStatus(int $userId, ?string $preferenceId, ?string $externalRef): array
    {
        $pdo = self::db();

        $u = self::getValidRealEstateUser($userId);

        $sql = "
            SELECT
                id,
                real_estate_id,
                user_id,
                plan_id,
                provider,
                preference_id,
                external_reference,
                mp_payment_id,
                mp_status,
                mp_status_detail,
                amount_ars,
                currency,
                status,
                paid_at,
                approved_at,
                created_at,
                updated_at
            FROM payments
            WHERE real_estate_id = :re
        ";

        $params = ['re' => (int)$u['real_estate_id']];

        if ($preferenceId) {
            $sql .= " AND preference_id = :pid";
            $params['pid'] = $preferenceId;
        } elseif ($externalRef) {
            $sql .= " AND external_reference = :ext";
            $params['ext'] = $externalRef;
        } else {
            throw new \Exception("Falta preference_id o external_reference");
        }

        $sql .= " ORDER BY id DESC LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $pay = $st->fetch();

        return [
            'payment' => $pay ?: null,
        ];
    }

    private static function getValidRealEstateUser(int $userId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, role, email, real_estate_id
            FROM users
            WHERE id=:id AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $user = $st->fetch();

        if (!$user || (int)$user['role'] !== 2) {
            throw new \Exception("Usuario inválido");
        }

        if (!$user['real_estate_id']) {
            throw new \Exception("Inmobiliaria no vinculada");
        }

        return $user;
    }

    private static function getApprovedRealEstate(int $realEstateId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, profile_status
            FROM real_estates
            WHERE id=:id AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $realEstateId]);
        $re = $st->fetch();

        if (!$re || (int)$re['profile_status'] !== 2) {
            throw new \Exception("La inmobiliaria debe estar aprobada antes de pagar");
        }

        return $re;
    }

    private static function getPlanByCode(string $planCode): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE code=:c AND is_active=1 AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['c' => $planCode]);
        $row = $st->fetch();

        return $row ?: null;
    }

    private static function getPlanById(int $planId): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE id=:id AND is_active=1 AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $planId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    private static function getRequiredActiveMembership(int $realEstateId): array
    {
        $membership = self::getActiveMembership($realEstateId);

        if (!$membership) {
            throw new \Exception("No hay una membresía activa para operar este cambio");
        }

        return $membership;
    }

    private static function getActiveMembership(int $realEstateId): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE real_estate_id = :re
              AND status = 1
              AND end_date >= CURDATE()
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute(['re' => $realEstateId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    private static function resolveChangeType(int $currentPrice, int $targetPrice): string
    {
        if ($targetPrice > $currentPrice) {
            return 'upgrade';
        }

        if ($targetPrice < $currentPrice) {
            return 'downgrade';
        }

        return 'same';
    }

    private static function daysBetween(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        return (int)$start->diff($end)->days ?: 1;
    }

    private static function daysRemaining(string $endDate): int
    {
        $today = new \DateTime(date('Y-m-d'));
        $end = new \DateTime($endDate);

        if ($end < $today) {
            return 0;
        }

        return ((int)$today->diff($end)->days) + 1;
    }

    private static function buildMercadoPagoPreference(string $title, int $amount, string $externalRef): array
    {
        $frontUrl = trim((string)($_ENV['FRONT_URL'] ?? ''));
        if ($frontUrl === '') {
            throw new \Exception("FRONT_URL no configurado");
        }
        $frontUrl = rtrim($frontUrl, '/');

        $notificationUrl = trim((string)($_ENV['MP_NOTIFICATION_URL'] ?? ''));
        if ($notificationUrl === '') {
            throw new \Exception("MP_NOTIFICATION_URL no configurado");
        }

        return [
            'items' => [[
                'title' => $title,
                'quantity' => 1,
                'unit_price' => $amount,
                'currency_id' => 'ARS'
            ]],
            'external_reference' => $externalRef,
            'notification_url' => $notificationUrl,
            'back_urls' => [
                'success' => $frontUrl . '/billing',
                'pending' => $frontUrl . '/billing',
                'failure' => $frontUrl . '/billing',
            ],
        ];
    }
}