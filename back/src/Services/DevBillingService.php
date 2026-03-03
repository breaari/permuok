<?php

namespace App\Services;

use PDO;

class DevBillingService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function forceApprove(int $paymentId, int $approvedByUserId): array
    {
        $pdo = self::db();
        $pdo->beginTransaction();

        // 1) Traer payment
        $st = $pdo->prepare("
            SELECT *
            FROM payments
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute(['id' => $paymentId]);
        $pay = $st->fetch();

        if (!$pay) {
            $pdo->rollBack();
            throw new \Exception("Payment no encontrado");
        }

        // 2) Marcar payment como approved (simulado)
        $fakeMpPaymentId = (string)('DEV-' . $paymentId . '-' . time());

        $st = $pdo->prepare("
            UPDATE payments
            SET status='approved',
                mp_payment_id=:mpid,
                mp_status='approved',
                mp_status_detail='dev_force_approved',
                paid_at=NOW(),
                updated_at=NOW()
            WHERE id=:id
            LIMIT 1
        ");
        $st->execute([
            'mpid' => $fakeMpPaymentId,
            'id'   => $paymentId,
        ]);

        // 3) Crear/activar membership en base al plan asociado
        $realEstateId = (int)$pay['real_estate_id'];
        $planId = (int)$pay['plan_id'];

        $st = $pdo->prepare("SELECT * FROM plans WHERE id=:id LIMIT 1");
        $st->execute(['id' => $planId]);
        $plan = $st->fetch();

        if (!$plan) {
            $pdo->rollBack();
            throw new \Exception("Plan no encontrado");
        }

        // 3.1) Expirar membresías activas previas (si existieran)
        $pdo->prepare("
            UPDATE memberships
            SET status=2
            WHERE real_estate_id=:re
              AND status=1
              AND deleted_at IS NULL
        ")->execute(['re' => $realEstateId]);

        $start = date('Y-m-d');
        $end   = date('Y-m-d', time() + ((int)$plan['duration_days'] * 86400));

        // 3.2) Crear nueva membership activa
        $st = $pdo->prepare("
            INSERT INTO memberships
            (real_estate_id, billing_cycle, status, start_date, end_date, max_users, max_agents, max_investors, can_publish_projects, can_view_projects, created_at, deleted_at)
            VALUES
            (:re, 1, 1, :start, :end, 1, :agents, :investors, :publish, 1, NOW(), NULL)
        ");

        $st->execute([
            're'        => $realEstateId,
            'start'     => $start,
            'end'       => $end,
            'agents'    => (int)$plan['max_agents'],
            'investors' => (int)$plan['max_investors'],
            'publish'   => (int)$plan['can_publish_projects'],
        ]);

        $membershipId = (int)$pdo->lastInsertId();

        // 4) Guardar referencia del payment en membership (si tenés la columna)
        // Si NO la tenés, borrá este bloque.
        $pdo->prepare("
            UPDATE memberships
            SET mp_last_payment_id = :pid
            WHERE id = :mid
            LIMIT 1
        ")->execute([
            'pid' => $paymentId,
            'mid' => $membershipId,
        ]);

        $pdo->commit();

        return [
            'payment_id' => $paymentId,
            'mp_payment_id' => $fakeMpPaymentId,
            'membership_id' => $membershipId,
            'real_estate_id' => $realEstateId,
            'plan_id' => $planId,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }
}