<?php

namespace App\Services;

use PDO;

class WebhookMercadoPagoService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function handleNotification(array $query, array $body): array
    {
        // casos comunes: ?type=payment&data.id=123 o body con data/id
        $paymentId =
            $query['data.id'] ?? $query['id'] ??
            ($body['data']['id'] ?? $body['id'] ?? null);

        if (!$paymentId) {
            return ['ok' => true, 'ignored' => 'no_payment_id'];
        }

        // Confirmar estado consultando a MP (fuente de verdad) :contentReference[oaicite:7]{index=7}
        $mpPayment = MercadoPagoClient::getPaymentById((string)$paymentId);

        $status = (string)($mpPayment['status'] ?? '');
        $statusDetail = (string)($mpPayment['status_detail'] ?? '');
        $externalRef = (string)($mpPayment['external_reference'] ?? '');

        if ($externalRef === '') {
            return ['ok' => true, 'ignored' => 'no_external_reference'];
        }

        $pdo = self::db();

        // buscar payment por external_reference
        $st = $pdo->prepare("SELECT * FROM payments WHERE external_reference = :ext LIMIT 1");
        $st->execute(['ext' => $externalRef]);
        $row = $st->fetch();

        if (!$row) {
            return ['ok' => true, 'ignored' => 'payment_not_found'];
        }

        // actualizar fila payments
        $newStatus = $row['status'];
        if ($status === 'approved') $newStatus = 'approved';
        elseif ($status === 'pending' || $status === 'in_process') $newStatus = 'pending';
        elseif ($status === 'rejected') $newStatus = 'rejected';
        elseif ($status === 'cancelled') $newStatus = 'cancelled';

        $st = $pdo->prepare("
            UPDATE payments
            SET mp_payment_id = :mpid,
                mp_status = :st,
                mp_status_detail = :std,
                status = :local_status,
                approved_at = IF(:local_status = 'approved', NOW(), approved_at)
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            'mpid' => (int)$paymentId,
            'st' => $status,
            'std' => $statusDetail,
            'local_status' => $newStatus,
            'id' => (int)$row['id'],
        ]);

        // si aprobado -> activar membresía
        if ($newStatus === 'approved') {
            self::activateMembershipFromPayment((int)$row['real_estate_id'], (int)$row['plan_id'], (int)$paymentId);
        }

        return ['ok' => true, 'processed' => true, 'status' => $newStatus];
    }

    private static function activateMembershipFromPayment(int $realEstateId, int $planId, int $mpPaymentId): void
    {
        $pdo = self::db();

        // plan
        $st = $pdo->prepare("SELECT * FROM plans WHERE id = :id LIMIT 1");
        $st->execute(['id' => $planId]);
        $plan = $st->fetch();
        if (!$plan) return;

        $start = date('Y-m-d');
        $end = date('Y-m-d', time() + ((int)$plan['duration_days'] * 86400));

        // desactivar membresías previas activas
        $pdo->prepare("
            UPDATE memberships
            SET status = 0
            WHERE real_estate_id = :re
              AND status = 1
        ")->execute(['re' => $realEstateId]);

        // crear nueva membresía activa
        $st = $pdo->prepare("
            INSERT INTO memberships
            (real_estate_id, plan_id, billing_cycle, status, start_date, end_date, max_users, max_agents, max_investors, can_publish_projects, mp_last_payment_id, created_at)
            VALUES
            (:re, :plan, 1, 1, :start, :end, 1, :ma, :mi, :cpp, :mpid, NOW())
        ");
        $st->execute([
            're' => $realEstateId,
            'plan' => $planId,
            'start' => $start,
            'end' => $end,
            'ma' => (int)$plan['max_agents'],
            'mi' => (int)$plan['max_investors'],
            'cpp' => (int)$plan['can_publish_projects'],
            'mpid' => $mpPaymentId,
        ]);
    }
}