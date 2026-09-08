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

        /*
         * Sólo procesamos membresías activas cuyo
         * período efectivamente ya terminó.
         *
         * Las renovaciones recurrentes NO se generan
         * acá. Las genera exclusivamente el webhook
         * cuando Mercado Pago confirma el pago.
         */
        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE status = :active_status
              AND end_date IS NOT NULL
              AND end_date < CURDATE()
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");

        $st->execute([
            'active_status' =>
            self::MEMBERSHIP_STATUS_ACTIVE,
        ]);

        $memberships =
            $st->fetchAll() ?: [];

        $processed = 0;
        $cancelled = 0;
        $expired = 0;

        $recurringExpired = 0;
        $legacyExpired = 0;

        $pausedProperties = 0;
        $pausedSearchRequests = 0;
        $pausedDevelopments = 0;

        foreach ($memberships as $membership) {
            $pdo->beginTransaction();

            try {
                $membershipId =
                    (int)$membership['id'];

                $realEstateId =
                    (int)$membership['real_estate_id'];

                $cancelAtPeriodEnd =
                    (int)(
                        $membership['cancel_at_period_end']
                        ?? 0
                    ) === 1;

                $subscriptionId = trim(
                    (string)(
                        $membership['mp_preapproval_id']
                        ?? ''
                    )
                );

                $isRecurring =
                    $subscriptionId !== '';

                /*
                 * 1. CANCELACIÓN PROGRAMADA
                 *
                 * El usuario ya había cancelado
                 * la renovación automática.
                 *
                 * Ahora que terminó el período
                 * pagado, cerramos definitivamente
                 * la membresía.
                 */
                if ($cancelAtPeriodEnd) {
                    $stCancel = $pdo->prepare("
                        UPDATE memberships
                        SET
                            status = :cancelled_status,
                            cancelled_at =
                                COALESCE(
                                    cancelled_at,
                                    NOW()
                                ),
                            scheduled_plan_id = NULL,
                            scheduled_change_at = NULL
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $stCancel->execute([
                        'cancelled_status' =>
                        self::MEMBERSHIP_STATUS_CANCELLED,

                        'id' =>
                        $membershipId,
                    ]);

                    $counts =
                        self::pauseRealEstateContent(
                            $realEstateId
                        );

                    $pausedProperties +=
                        $counts['properties'];

                    $pausedSearchRequests +=
                        $counts['search_requests'];

                    $pausedDevelopments +=
                        $counts['developments'];

                    $cancelled++;
                    $processed++;

                    $pdo->commit();

                    continue;
                }

                /*
                 * 2. SUSCRIPCIÓN RECURRENTE
                 *
                 * Si llegamos acá significa que
                 * end_date venció y ningún webhook
                 * de pago aprobado abrió un nuevo
                 * período.
                 *
                 * IMPORTANTE:
                 * NO extendemos fechas.
                 * NO aplicamos scheduled_plan_id.
                 * NO creamos otra membership.
                 *
                 * Sin pago aprobado no hay renovación.
                 */
                if ($isRecurring) {
                    $stExpire = $pdo->prepare("
                        UPDATE memberships
                        SET
                            status = :expired_status
                        WHERE id = :id
                          AND status = :active_status
                        LIMIT 1
                    ");

                    $stExpire->execute([
                        'expired_status' =>
                        self::MEMBERSHIP_STATUS_EXPIRED,

                        'active_status' =>
                        self::MEMBERSHIP_STATUS_ACTIVE,

                        'id' =>
                        $membershipId,
                    ]);

                    $counts =
                        self::pauseRealEstateContent(
                            $realEstateId
                        );

                    $pausedProperties +=
                        $counts['properties'];

                    $pausedSearchRequests +=
                        $counts['search_requests'];

                    $pausedDevelopments +=
                        $counts['developments'];

                    $expired++;
                    $recurringExpired++;
                    $processed++;

                    $pdo->commit();

                    continue;
                }

                /*
                 * 3. MEMBRESÍA HISTÓRICA / MANUAL
                 *
                 * No tiene mp_preapproval_id.
                 *
                 * Conservamos la posibilidad de que
                 * venza normalmente, pero eliminamos
                 * definitivamente la renovación gratis
                 * que antes creaba otra membership.
                 */
                $stExpire = $pdo->prepare("
                    UPDATE memberships
                    SET
                        status = :expired_status,
                        scheduled_plan_id = NULL,
                        scheduled_change_at = NULL
                    WHERE id = :id
                      AND status = :active_status
                    LIMIT 1
                ");

                $stExpire->execute([
                    'expired_status' =>
                    self::MEMBERSHIP_STATUS_EXPIRED,

                    'active_status' =>
                    self::MEMBERSHIP_STATUS_ACTIVE,

                    'id' =>
                    $membershipId,
                ]);

                $counts =
                    self::pauseRealEstateContent(
                        $realEstateId
                    );

                $pausedProperties +=
                    $counts['properties'];

                $pausedSearchRequests +=
                    $counts['search_requests'];

                $pausedDevelopments +=
                    $counts['developments'];

                $expired++;
                $legacyExpired++;
                $processed++;

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        return [
            'ok' => true,

            'processed' =>
            $processed,

            'cancelled' =>
            $cancelled,

            'expired' =>
            $expired,

            'recurring_expired' =>
            $recurringExpired,

            'legacy_expired' =>
            $legacyExpired,

            'paused_properties' =>
            $pausedProperties,

            'paused_search_requests' =>
            $pausedSearchRequests,

            'paused_developments' =>
            $pausedDevelopments,
        ];
    }

    private static function pauseRealEstateContent(
        int $realEstateId
    ): array {
        $pdo = self::db();

        $stProperties = $pdo->prepare("
            UPDATE properties
            SET
                status = 'paused',
                is_visible = 0,
                paused_at = NOW()
            WHERE real_estate_id = :real_estate_id
              AND status = 'published'
              AND deleted_at IS NULL
        ");

        $stProperties->execute([
            'real_estate_id' =>
            $realEstateId,
        ]);

        $stSearchRequests = $pdo->prepare("
            UPDATE search_requests
            SET
                status = 'paused',
                is_visible = 0,
                paused_at = NOW()
            WHERE real_estate_id = :real_estate_id
              AND status = 'published'
              AND deleted_at IS NULL
        ");

        $stSearchRequests->execute([
            'real_estate_id' =>
            $realEstateId,
        ]);

        $stDevelopments = $pdo->prepare("
            UPDATE developments
            SET
                status = 'paused',
                paused_at = NOW()
            WHERE real_estate_id = :real_estate_id
              AND status = 'published'
              AND deleted_at IS NULL
        ");

        $stDevelopments->execute([
            'real_estate_id' =>
            $realEstateId,
        ]);

        return [
            'properties' =>
            $stProperties->rowCount(),

            'search_requests' =>
            $stSearchRequests->rowCount(),

            'developments' =>
            $stDevelopments->rowCount(),
        ];
    }
}
