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

        /*
        |--------------------------------------------------------------------------
        | VALIDAR CONFIG
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | USER + REAL ESTATE
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | REAL ESTATE APROBADA
        |--------------------------------------------------------------------------
        */
        $st = $pdo->prepare("
            SELECT id, validation_status
            FROM real_estates
            WHERE id=:id AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => (int)$user['real_estate_id']]);
        $re = $st->fetch();

        if (!$re || (int)$re['validation_status'] !== 1) {
            throw new \Exception("La inmobiliaria debe estar aprobada antes de pagar");
        }

        /*
        |--------------------------------------------------------------------------
        | BLOQUEAR DOBLE PAGO (membresía activa vigente)
        |--------------------------------------------------------------------------
        */
        $activeMembership = self::getActiveMembership((int)$user['real_estate_id']);
        if ($activeMembership) {
            $until = $activeMembership['end_date'] ?? null;
            throw new \Exception("Ya tenés una membresía activa hasta {$until}. No podés generar un nuevo pago.");
        }
        /*
        |--------------------------------------------------------------------------
        | PLAN
        |--------------------------------------------------------------------------
        */
        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE code=:c AND is_active=1 AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['c' => $planCode]);
        $plan = $st->fetch();

        if (!$plan) {
            throw new \Exception("Plan no encontrado");
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT LOCAL
        |--------------------------------------------------------------------------
        */
        $externalRef = "re{$user['real_estate_id']}-u{$userId}-p{$plan['id']}-" . bin2hex(random_bytes(6));

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

        /*
        |--------------------------------------------------------------------------
        | MERCADO PAGO PREFERENCE
        |--------------------------------------------------------------------------
        */
        $pref = [
            'items' => [[
                'title' => (string)$plan['name'],
                'quantity' => 1,
                'unit_price' => (int)$plan['price_ars'],
                'currency_id' => 'ARS'
            ]],
            'external_reference' => $externalRef,
            'notification_url' => $notificationUrl,
            'back_urls' => [
                'success' => $frontUrl . '/billing/success',
                'pending' => $frontUrl . '/billing/pending',
                'failure' => $frontUrl . '/billing/failure',
            ],
        ];

        // ✅ SOLO setear auto_return si FRONT_URL es público/https
        if (str_starts_with($frontUrl, 'https://')) {
            $pref['auto_return'] = 'approved';
        }



        /*
        |--------------------------------------------------------------------------
        | LLAMADA A MERCADO PAGO
        |--------------------------------------------------------------------------
        */
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
        /*
        |--------------------------------------------------------------------------
        | GUARDAR PREFERENCE
        |--------------------------------------------------------------------------
        */
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
            'init_point' => (string)$initPoint, // en TEST esto será sandbox_init_point
            'external_reference' => $externalRef,
        ];
    }

    public static function getPaymentStatus(int $userId, ?string $preferenceId, ?string $externalRef): array
    {
        $pdo = self::db();

        // validar usuario
        $st = $pdo->prepare("
        SELECT id, role, real_estate_id
        FROM users
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $st->execute(['id' => $userId]);
        $u = $st->fetch();

        if (!$u) {
            throw new \Exception("Usuario inválido");
        }

        if ((int)$u['role'] !== 2) {
            throw new \Exception("No autorizado");
        }

        if (!$u['real_estate_id']) {
            throw new \Exception("Usuario sin inmobiliaria");
        }

        // armar query base
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
}
