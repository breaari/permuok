<?php

namespace App\Services;

use PDO;

class BillingCycleService
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

    public static function processDueMemberships(): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE status = :active_status
              AND end_date < CURDATE()
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");
        $st->execute([
            'active_status' => self::MEMBERSHIP_STATUS_ACTIVE,
        ]);

        $memberships = $st->fetchAll() ?: [];

        $processed = 0;
        $cancelled = 0;
        $expired = 0;
        $renewedWithScheduledPlan = 0;

        foreach ($memberships as $membership) {
            $pdo->beginTransaction();

            try {
                $membershipId = (int)$membership['id'];
                $realEstateId = (int)$membership['real_estate_id'];
                $scheduledPlanId = isset($membership['scheduled_plan_id']) && $membership['scheduled_plan_id'] !== null
                    ? (int)$membership['scheduled_plan_id']
                    : null;
                $cancelAtPeriodEnd = (int)($membership['cancel_at_period_end'] ?? 0) === 1;

                if ($cancelAtPeriodEnd) {
                    $stCancel = $pdo->prepare("
                        UPDATE memberships
                        SET status = :cancelled_status
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stCancel->execute([
                        'cancelled_status' => self::MEMBERSHIP_STATUS_CANCELLED,
                        'id' => $membershipId,
                    ]);

                    $cancelled++;
                    $processed++;
                    $pdo->commit();
                    continue;
                }

                if ($scheduledPlanId) {
                    $plan = self::getPlanById($scheduledPlanId);
                    if (!$plan) {
                        // si el plan programado ya no existe, la membresía solo vence
                        $stExpire = $pdo->prepare("
                            UPDATE memberships
                            SET
                                status = :expired_status,
                                scheduled_plan_id = NULL,
                                scheduled_change_at = NULL
                            WHERE id = :id
                            LIMIT 1
                        ");
                        $stExpire->execute([
                            'expired_status' => self::MEMBERSHIP_STATUS_EXPIRED,
                            'id' => $membershipId,
                        ]);

                        $expired++;
                        $processed++;
                        $pdo->commit();
                        continue;
                    }

                    // marcar la membresía actual como vencida
                    $stExpireCurrent = $pdo->prepare("
                        UPDATE memberships
                        SET status = :expired_status
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stExpireCurrent->execute([
                        'expired_status' => self::MEMBERSHIP_STATUS_EXPIRED,
                        'id' => $membershipId,
                    ]);

                    $startDate = date('Y-m-d');
                    $endDate = date(
                        'Y-m-d',
                        strtotime($startDate . ' +' . ((int)$plan['duration_days'] - 1) . ' days')
                    );

                    $stInsert = $pdo->prepare("
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
                            :real_estate_id,
                            :plan_id,
                            NULL,
                            :billing_cycle,
                            :status,
                            0,
                            NULL,
                            :start_date,
                            :end_date,
                            NULL,
                            :max_users,
                            :max_agents,
                            :max_investors,
                            :can_publish_projects,
                            :can_view_projects,
                            NULL,
                            NOW()
                        )
                    ");
                    $stInsert->execute([
                        'real_estate_id' => $realEstateId,
                        'plan_id' => (int)$plan['id'],
                        'billing_cycle' => (int)($membership['billing_cycle'] ?? 1),
                        'status' => self::MEMBERSHIP_STATUS_ACTIVE,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'max_users' => (int)($plan['max_users'] ?? 1),
                        'max_agents' => (int)($plan['max_agents'] ?? 0),
                        'max_investors' => (int)($plan['max_investors'] ?? 0),
                        'can_publish_projects' => (int)($plan['can_publish_projects'] ?? 0),
                        'can_view_projects' => (int)($plan['can_view_projects'] ?? 0),
                    ]);

                    $renewedWithScheduledPlan++;
                    $processed++;
                    $pdo->commit();
                    continue;
                }

                // si no hay cancelación ni plan programado, solo vence
                $stExpire = $pdo->prepare("
                    UPDATE memberships
                    SET status = :expired_status
                    WHERE id = :id
                    LIMIT 1
                ");
                $stExpire->execute([
                    'expired_status' => self::MEMBERSHIP_STATUS_EXPIRED,
                    'id' => $membershipId,
                ]);

                $expired++;
                $processed++;
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        return [
            'ok' => true,
            'processed' => $processed,
            'cancelled' => $cancelled,
            'expired' => $expired,
            'renewed_with_scheduled_plan' => $renewedWithScheduledPlan,
        ];
    }

    private static function getPlanById(int $planId): ?array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE id = :id
              AND is_active = 1
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $planId]);
        $row = $st->fetch();

        return $row ?: null;
    }
}