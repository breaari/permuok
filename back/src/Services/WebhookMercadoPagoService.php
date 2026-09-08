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

    public static function handleNotification(
        array $query,
        array $body
    ): array {
        $type = (string)(
            $body['type']
            ?? $query['type']
            ?? $query['topic']
            ?? ''
        );

        if ($type === 'subscription_preapproval') {
            return self::handleSubscriptionPreapproval(
                $query,
                $body
            );
        }

        if ($type === 'subscription_authorized_payment') {
            return self::handleSubscriptionAuthorizedPayment(
                $query,
                $body
            );
        }

        return self::handlePaymentNotification(
            $query,
            $body
        );
    }

    private static function handleSubscriptionPreapproval(
        array $query,
        array $body
    ): array {
        $subscriptionId =
            $body['data']['id']
            ?? $body['id']
            ?? $query['data.id']
            ?? $query['id']
            ?? null;

        if (!$subscriptionId) {
            return [
                'ok' => true,
                'ignored' => 'no_subscription_id',
            ];
        }

        $subscription =
            MercadoPagoClient::getSubscriptionById(
                (string)$subscriptionId
            );

        $mpStatus = (string)(
            $subscription['status']
            ?? ''
        );

        if ($mpStatus === '') {
            return [
                'ok' => true,
                'ignored' => 'subscription_without_status',
            ];
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE mp_preapproval_id = :subscription_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $st->execute([
            'subscription_id' =>
            (string)$subscriptionId,
        ]);

        $membership = $st->fetch();

        if (!$membership) {
            return [
                'ok' => true,
                'ignored' => 'membership_not_found',
                'subscription_id' =>
                (string)$subscriptionId,
            ];
        }

        $st = $pdo->prepare("
            UPDATE memberships
            SET
                mp_subscription_status = :status,
                mp_subscription_updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        $st->execute([
            'status' => $mpStatus,
            'id' => (int)$membership['id'],
        ]);

        return [
            'ok' => true,
            'processed' => true,
            'type' => 'subscription_preapproval',
            'subscription_id' =>
            (string)$subscriptionId,
            'status' => $mpStatus,
            'membership_id' =>
            (int)$membership['id'],
        ];
    }

    private static function handleSubscriptionAuthorizedPayment(
        array $query,
        array $body
    ): array {
        $authorizedPaymentId =
            $body['data']['id']
            ?? $body['id']
            ?? $query['data.id']
            ?? $query['id']
            ?? null;

        if (!$authorizedPaymentId) {
            return [
                'ok' => true,
                'ignored' => 'no_authorized_payment_id',
            ];
        }

        $invoice =
            MercadoPagoClient::getAuthorizedPaymentById(
                (string)$authorizedPaymentId
            );

        $subscriptionId = (string)(
            $invoice['preapproval_id']
            ?? ''
        );

        if ($subscriptionId === '') {
            return [
                'ok' => true,
                'ignored' => 'no_preapproval_id',
            ];
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE mp_preapproval_id = :subscription_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $st->execute([
            'subscription_id' =>
            $subscriptionId,
        ]);

        $membership = $st->fetch();

        if (!$membership) {
            return [
                'ok' => true,
                'ignored' => 'membership_not_found',
                'subscription_id' =>
                $subscriptionId,
            ];
        }

        $effectivePlanId =
            !empty($membership['scheduled_plan_id'])
            ? (int)$membership['scheduled_plan_id']
            : (int)$membership['plan_id'];

        $billingUserId =
            isset($membership['billing_user_id'])
            ? (int)$membership['billing_user_id']
            : 0;

        if ($billingUserId <= 0) {
            return [
                'ok' => false,
                'ignored' => 'missing_billing_user_id',
                'membership_id' =>
                (int)$membership['id'],
            ];
        }

        $paymentData =
            is_array($invoice['payment'] ?? null)
            ? $invoice['payment']
            : [];

        $mpPaymentId =
            isset($paymentData['id'])
            ? (int)$paymentData['id']
            : null;

        $mpStatus = (string)(
            $paymentData['status']
            ?? $invoice['summarized']
            ?? $invoice['status']
            ?? ''
        );

        $mpStatusDetail = (string)(
            $paymentData['status_detail']
            ?? ''
        );

        $localStatus =
            self::normalizePaymentStatus(
                $mpStatus
            );

        $amount = (int)round(
            (float)(
                $invoice['transaction_amount']
                ?? 0
            )
        );

        $currency = (string)(
            $invoice['currency_id']
            ?? 'ARS'
        );

        $externalReference =
            'subscription-'
            . $subscriptionId
            . '-invoice-'
            . (string)$authorizedPaymentId;

        /*
         * Si ya procesamos esta factura,
         * actualizamos la misma fila.
         */
        $st = $pdo->prepare("
            SELECT *
            FROM payments
            WHERE external_reference = :external_reference
            LIMIT 1
        ");

        $st->execute([
            'external_reference' =>
            $externalReference,
        ]);

        $paymentRow = $st->fetch();

        $wasAlreadyApproved =
            $paymentRow
            && (string)($paymentRow['status'] ?? '') === 'approved';

        if (!$paymentRow) {
            $st = $pdo->prepare("
                INSERT INTO payments
                (
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
                    approved_at
                )
                VALUES
                (
                    :real_estate_id,
                    :user_id,
                    :plan_id,
                    'mercadopago',
                    NULL,
                    :external_reference,
                    :mp_payment_id,
                    :mp_status,
                    :mp_status_detail,
                    :amount_ars,
                    :currency,
                    :status,
                    :paid_at,
                    :approved_at
                )
            ");

            $st->execute([
                'real_estate_id' =>
                (int)$membership['real_estate_id'],

                'user_id' =>
                $billingUserId,

                'plan_id' =>
                $effectivePlanId,

                'external_reference' =>
                $externalReference,

                'mp_payment_id' =>
                $mpPaymentId,

                'mp_status' =>
                $mpStatus,

                'mp_status_detail' =>
                $mpStatusDetail,

                'amount_ars' =>
                $amount,

                'currency' =>
                $currency,

                'status' =>
                $localStatus,

                'paid_at' =>
                $localStatus === 'approved'
                    ? date('Y-m-d H:i:s')
                    : null,

                'approved_at' =>
                $localStatus === 'approved'
                    ? date('Y-m-d H:i:s')
                    : null,
            ]);

            $paymentRowId =
                (int)$pdo->lastInsertId();
        } else {
            $paymentRowId =
                (int)$paymentRow['id'];

            $st = $pdo->prepare("
                UPDATE payments
                SET
                    mp_payment_id = :mp_payment_id,
                    mp_status = :mp_status,
                    mp_status_detail = :mp_status_detail,
                    amount_ars = :amount_ars,
                    currency = :currency,
                    status = :status,
                    paid_at = IF(
                        :status_paid = 'approved',
                        COALESCE(paid_at, NOW()),
                        paid_at
                    ),
                    approved_at = IF(
                        :status_approved = 'approved',
                        COALESCE(approved_at, NOW()),
                        approved_at
                    )
                WHERE id = :id
                LIMIT 1
            ");

            $st->execute([
                'mp_payment_id' =>
                $mpPaymentId,

                'mp_status' =>
                $mpStatus,

                'mp_status_detail' =>
                $mpStatusDetail,

                'amount_ars' =>
                $amount,

                'currency' =>
                $currency,

                'status' =>
                $localStatus,

                'status_paid' =>
                $localStatus,

                'status_approved' =>
                $localStatus,

                'id' =>
                $paymentRowId,
            ]);
        }

        if (
            $localStatus === 'approved'
            && !$wasAlreadyApproved
        ) {
            self::activateOrRenewSubscriptionMembership(
                $membership,
                $mpPaymentId
            );
        }

        return [
            'ok' => true,
            'processed' => true,
            'type' => 'subscription_authorized_payment',
            'authorized_payment_id' =>
            (string)$authorizedPaymentId,
            'subscription_id' =>
            $subscriptionId,
            'payment_id' =>
            $mpPaymentId,
            'payment_row_id' =>
            $paymentRowId,
            'status' =>
            $localStatus,
            'membership_id' =>
            (int)$membership['id'],
        ];
    }

    private static function activateOrRenewSubscriptionMembership(
        array $membership,
        ?int $mpPaymentId
    ): void {
        $pdo = self::db();

        /*
     * Si había un downgrade programado,
     * el nuevo período comienza ya con
     * ese plan.
     */
        $effectivePlanId =
            !empty($membership['scheduled_plan_id'])
            ? (int)$membership['scheduled_plan_id']
            : (int)$membership['plan_id'];

        $plan = self::getPlan(
            $effectivePlanId
        );

        if (!$plan) {
            return;
        }

        $start = date('Y-m-d');

        $end = date(
            'Y-m-d',
            strtotime(
                $start
                    . ' +'
                    . (
                        (int)$plan['duration_days']
                        - 1
                    )
                    . ' days'
            )
        );

        $st = $pdo->prepare("
        UPDATE memberships
        SET
            plan_id = :plan_id,

            scheduled_plan_id = NULL,
            scheduled_change_at = NULL,

            status = :active_status,

            start_date = :start_date,
            end_date = :end_date,

            max_users = :max_users,
            max_agents = :max_agents,
            max_investors = :max_investors,
            can_publish_projects = :can_publish_projects,
            can_view_projects = :can_view_projects,

            mp_last_payment_id = :mp_payment_id,
            mp_subscription_status = 'authorized',
            mp_subscription_updated_at = NOW()

        WHERE id = :id
        LIMIT 1
    ");

        $st->execute([
            'plan_id' =>
            $effectivePlanId,

            'active_status' =>
            self::MEMBERSHIP_STATUS_ACTIVE,

            'start_date' =>
            $start,

            'end_date' =>
            $end,

            'max_users' =>
            (int)($plan['max_users'] ?? 1),

            'max_agents' =>
            (int)($plan['max_agents'] ?? 0),

            'max_investors' =>
            (int)($plan['max_investors'] ?? 0),

            'can_publish_projects' =>
            (int)($plan['can_publish_projects'] ?? 0),

            'can_view_projects' =>
            (int)($plan['can_view_projects'] ?? 0),

            'mp_payment_id' =>
            $mpPaymentId,

            'id' =>
            (int)$membership['id'],
        ]);
    }

    private static function normalizePaymentStatus(
        string $status
    ): string {
        return match ($status) {
            'approved' =>
            'approved',

            'pending',
            'in_process',
            'scheduled' =>
            'pending',

            'rejected' =>
            'rejected',

            'cancelled',
            'cancelled_by_user' =>
            'cancelled',

            default =>
            'pending',
        };
    }

    private static function handlePaymentNotification(
        array $query,
        array $body
    ): array {
        $paymentId =
            $query['data.id']
            ?? $query['id']
            ?? ($body['data']['id'] ?? null)
            ?? ($body['id'] ?? null);

        if (!$paymentId) {
            return [
                'ok' => true,
                'ignored' => 'no_payment_id',
            ];
        }

        $mpPayment =
            MercadoPagoClient::getPaymentById(
                (string)$paymentId
            );

        $status = (string)(
            $mpPayment['status']
            ?? ''
        );

        $statusDetail = (string)(
            $mpPayment['status_detail']
            ?? ''
        );

        $externalRef = (string)(
            $mpPayment['external_reference']
            ?? ''
        );

        if ($externalRef === '') {
            return [
                'ok' => true,
                'ignored' => 'no_external_reference',
            ];
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM payments
            WHERE external_reference = :ext
            LIMIT 1
        ");

        $st->execute([
            'ext' => $externalRef,
        ]);

        $row = $st->fetch();

        if (!$row) {
            return [
                'ok' => true,
                'ignored' => 'payment_not_found',
            ];
        }

        $wasAlreadyApproved =
            (string)($row['status'] ?? '') === 'approved';

        $newStatus =
            self::normalizePaymentStatus(
                $status
            );

        $st = $pdo->prepare("
            UPDATE payments
            SET
                mp_payment_id = :mpid,
                mp_status = :st,
                mp_status_detail = :std,
                status = :local_status,
                approved_at = IF(
                    :approved_status = 'approved',
                    COALESCE(approved_at, NOW()),
                    approved_at
                ),
                paid_at = IF(
                    :paid_status = 'approved',
                    COALESCE(paid_at, NOW()),
                    paid_at
                )
            WHERE id = :id
            LIMIT 1
        ");

        $st->execute([
            'mpid' =>
            (int)$paymentId,

            'st' =>
            $status,

            'std' =>
            $statusDetail,

            'local_status' =>
            $newStatus,

            'approved_status' =>
            $newStatus,

            'paid_status' =>
            $newStatus,

            'id' =>
            (int)$row['id'],
        ]);

        if (
            $newStatus === 'approved'
            && !$wasAlreadyApproved
        ) {
            if (
                str_contains(
                    $externalRef,
                    '-upgrade-'
                )
            ) {
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

            if (
                str_contains(
                    $externalRef,
                    '-new-'
                )
            ) {
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
        }

        return [
            'ok' => true,
            'processed' => true,
            'status' => $newStatus,
        ];
    }

    private static function activateMembershipFromPayment(
        int $realEstateId,
        int $planId,
        int $mpPaymentId
    ): void {
        $pdo = self::db();

        $plan = self::getPlan(
            $planId
        );

        if (!$plan) {
            return;
        }

        $start = date('Y-m-d');

        $end = date(
            'Y-m-d',
            strtotime(
                $start
                    . ' +'
                    . (
                        (int)$plan['duration_days']
                        - 1
                    )
                    . ' days'
            )
        );

        $pdo->prepare("
            UPDATE memberships
            SET status = :expired_status
            WHERE real_estate_id = :re
              AND status = :active_status
              AND deleted_at IS NULL
        ")->execute([
            'expired_status' =>
            self::MEMBERSHIP_STATUS_EXPIRED,

            'active_status' =>
            self::MEMBERSHIP_STATUS_ACTIVE,

            're' =>
            $realEstateId,
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
            're' =>
            $realEstateId,

            'plan' =>
            $planId,

            'status' =>
            self::MEMBERSHIP_STATUS_ACTIVE,

            'start' =>
            $start,

            'end' =>
            $end,

            'max_users' =>
            (int)($plan['max_users'] ?? 1),

            'max_agents' =>
            (int)($plan['max_agents'] ?? 0),

            'max_investors' =>
            (int)($plan['max_investors'] ?? 0),

            'can_publish_projects' =>
            (int)($plan['can_publish_projects'] ?? 0),

            'can_view_projects' =>
            (int)($plan['can_view_projects'] ?? 0),

            'mpid' =>
            $mpPaymentId,
        ]);
    }

    private static function applyUpgradeFromPayment(
        int $realEstateId,
        int $planId,
        int $mpPaymentId
    ): void {
        $pdo = self::db();

        $plan = self::getPlan(
            $planId
        );

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
            're' =>
            $realEstateId,

            'active_status' =>
            self::MEMBERSHIP_STATUS_ACTIVE,
        ]);

        $membership = $st->fetch();

        if (!$membership) {
            return;
        }

        $subscriptionId = trim(
            (string)(
                $membership['mp_preapproval_id']
                ?? ''
            )
        );

        $mpSubscriptionStatus =
            $membership['mp_subscription_status']
            ?? null;

        /*
     * Nuevo sistema recurrente:
     * una vez acreditado el diferencial,
     * actualizamos también el importe
     * de las próximas renovaciones.
     */
        if ($subscriptionId !== '') {
            $subscription =
                MercadoPagoClient::updateSubscription(
                    $subscriptionId,
                    [
                        'auto_recurring' => [
                            'transaction_amount' =>
                            (float)$plan['price_ars'],

                            'currency_id' =>
                            'ARS',
                        ],
                    ]
                );

            $mpSubscriptionStatus =
                (string)(
                    $subscription['status']
                    ?? $mpSubscriptionStatus
                    ?? 'authorized'
                );
        }

        /*
     * El período NO empieza nuevamente.
     *
     * El usuario ya había pagado el período
     * actual. Sólo obtiene las prestaciones
     * superiores desde ahora y conserva
     * la misma fecha de vencimiento.
     */
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

            mp_last_payment_id = :mpid,

            mp_subscription_status =
                CASE
                    WHEN :mp_status IS NOT NULL
                    THEN :mp_status_value
                    ELSE mp_subscription_status
                END,

            mp_subscription_updated_at =
                CASE
                    WHEN :mp_status_date IS NOT NULL
                    THEN NOW()
                    ELSE mp_subscription_updated_at
                END

        WHERE id = :id
        LIMIT 1
    ");

        $st->execute([
            'plan_id' =>
            $planId,

            'max_users' =>
            (int)($plan['max_users'] ?? 1),

            'max_agents' =>
            (int)($plan['max_agents'] ?? 0),

            'max_investors' =>
            (int)($plan['max_investors'] ?? 0),

            'can_publish_projects' =>
            (int)($plan['can_publish_projects'] ?? 0),

            'can_view_projects' =>
            (int)($plan['can_view_projects'] ?? 0),

            'mpid' =>
            $mpPaymentId,

            'mp_status' =>
            $mpSubscriptionStatus,

            'mp_status_value' =>
            $mpSubscriptionStatus,

            'mp_status_date' =>
            $mpSubscriptionStatus,

            'id' =>
            (int)$membership['id'],
        ]);
    }

    private static function getPlan(
        int $planId
    ): ?array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM plans
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $planId,
        ]);

        $row = $st->fetch();

        return $row ?: null;
    }
}
