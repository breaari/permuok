<?php

namespace App\Services;

use PDO;

class WebhookMercadoPagoService
{
    private const MEMBERSHIP_STATUS_PENDING = 0;
    private const MEMBERSHIP_STATUS_ACTIVE = 1;
    private const MEMBERSHIP_STATUS_EXPIRED = 2;
    private const MEMBERSHIP_STATUS_CANCELLED = 3;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function handleNotification(array $query, array $body): array
    {
        $paymentId =
            $query['data.id'] ?? $query['id'] ??
            ($body['data']['id'] ?? $body['id'] ?? null);

        if (!$paymentId) {
            return ['ok' => true, 'ignored' => 'no_payment_id'];
        }

        $mpPayment = MercadoPagoClient::getPaymentById((string)$paymentId);

        $status = (string)($mpPayment['status'] ?? '');
        $statusDetail = (string)($mpPayment['status_detail'] ?? '');
        $externalRef = (string)($mpPayment['external_reference'] ?? '');

        if ($externalRef === '') {
            return ['ok' => true, 'ignored' => 'no_external_reference'];
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM payments
            WHERE external_reference = :ext
            LIMIT 1
        ");
        $st->execute(['ext' => $externalRef]);
        $row = $st->fetch();

        if (!$row) {
            return ['ok' => true, 'ignored' => 'payment_not_found'];
        }

        $newStatus = $row['status'];
        if ($status === 'approved') {
            $newStatus = 'approved';
        } elseif ($status === 'pending' || $status === 'in_process') {
            $newStatus = 'pending';
        } elseif ($status === 'rejected') {
            $newStatus = 'rejected';
        } elseif ($status === 'cancelled') {
            $newStatus = 'cancelled';
        }

        $st = $pdo->prepare("
            UPDATE payments
            SET
                mp_payment_id = :mpid,
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

        if ($newStatus === 'approved') {
            if (str_contains($externalRef, '-upgrade-')) {
                self::applyUpgradeFromPayment(
                    (int)$row['real_estate_id'],
                    (int)$row['plan_id'],
                    (int)$paymentId
                );

                return [
                    'ok' => true,
                    'processed' => true,
                    'status' => $newStatus,
                    'mode' => 'upgrade',
                ];
            }

            if (str_contains($externalRef, '-new-')) {
                self::activateMembershipFromPayment(
                    (int)$row['real_estate_id'],
                    (int)$row['plan_id'],
                    (int)$paymentId
                );

                return [
                    'ok' => true,
                    'processed' => true,
                    'status' => $newStatus,
                    'mode' => 'new_membership',
                ];
            }

            return [
                'ok' => true,
                'processed' => true,
                'status' => $newStatus,
                'ignored' => 'unknown_payment_mode',
            ];
        }

        return ['ok' => true, 'processed' => true, 'status' => $newStatus];
    }

    private static function activateMembershipFromPayment(int $realEstateId, int $planId, int $mpPaymentId): void
    {
        $pdo = self::db();

        $plan = self::getPlan($planId);
        if (!$plan) {
            return;
        }

        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime($start . ' +' . ((int)$plan['duration_days'] - 1) . ' days'));

        // vencer membresías activas previas, si por algún motivo existieran
        $pdo->prepare("
            UPDATE memberships
            SET status = :expired_status
            WHERE real_estate_id = :re
              AND status = :active_status
              AND deleted_at IS NULL
        ")->execute([
            'expired_status' => self::MEMBERSHIP_STATUS_EXPIRED,
            'active_status' => self::MEMBERSHIP_STATUS_ACTIVE,
            're' => $realEstateId,
        ]);

        $st = $pdo->prepare("
            INSERT INTO memberships
            (
                real_estate_id,
                plan_id,
                scheduled_plan_id,
                billing_cycle,
                status,
                cancel_at_period_end,
                cancelled_at,
                start_date,
                end_date,
                scheduled_change_at,
                max_users,
                max_agents,
                max_investors,
                can_publish_projects,
                can_view_projects,
                mp_last_payment_id,
                created_at
            )
            VALUES
            (
                :re,
                :plan,
                NULL,
                1,
                :status,
                0,
                NULL,
                :start,
                :end,
                NULL,
                :max_users,
                :max_agents,
                :max_investors,
                :can_publish_projects,
                :can_view_projects,
                :mpid,
                NOW()
            )
        ");
        $st->execute([
            're' => $realEstateId,
            'plan' => $planId,
            'status' => self::MEMBERSHIP_STATUS_ACTIVE,
            'start' => $start,
            'end' => $end,
            'max_users' => (int)($plan['max_users'] ?? 1),
            'max_agents' => (int)$plan['max_agents'],
            'max_investors' => (int)$plan['max_investors'],
            'can_publish_projects' => (int)$plan['can_publish_projects'],
            'can_view_projects' => (int)($plan['can_view_projects'] ?? 0),
            'mpid' => $mpPaymentId,
        ]);
    }

    private static function applyUpgradeFromPayment(int $realEstateId, int $planId, int $mpPaymentId): void
    {
        $pdo = self::db();

        $plan = self::getPlan($planId);
        if (!$plan) {
            return;
        }

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE real_estate_id = :re
              AND status = :active_status
              AND end_date >= CURDATE()
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute([
            're' => $realEstateId,
            'active_status' => self::MEMBERSHIP_STATUS_ACTIVE,
        ]);
        $membership = $st->fetch();

        if (!$membership) {
            return;
        }

        $st = $pdo->prepare("
            UPDATE memberships
            SET
                plan_id = :plan_id,
                scheduled_plan_id = NULL,
                scheduled_change_at = NULL,
                cancel_at_period_end = 0,
                cancelled_at = NULL,
                max_users = :max_users,
                max_agents = :max_agents,
                max_investors = :max_investors,
                can_publish_projects = :can_publish_projects,
                can_view_projects = :can_view_projects,
                mp_last_payment_id = :mpid
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            'plan_id' => $planId,
            'max_users' => (int)($plan['max_users'] ?? 1),
            'max_agents' => (int)$plan['max_agents'],
            'max_investors' => (int)$plan['max_investors'],
            'can_publish_projects' => (int)$plan['can_publish_projects'],
            'can_view_projects' => (int)($plan['can_view_projects'] ?? 0),
            'mpid' => $mpPaymentId,
            'id' => (int)$membership['id'],
        ]);
    }

    private static function getPlan(int $planId): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $planId]);
        $row = $st->fetch();

        return $row ?: null;
    }
}