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

    private static function runWithMembershipLock(
        int $userId,
        callable $callback
    ): array {
        $pdo = self::db();

        $user =
            self::getValidRealEstateUser(
                $userId
            );

        $realEstateId =
            (int)$user['real_estate_id'];

        $lockName =
            'billing:membership:' .
            $realEstateId;

        $lockStmt = $pdo->prepare("
        SELECT GET_LOCK(
            :lock_name,
            15
        )
    ");

        $lockStmt->execute([
            'lock_name' => $lockName,
        ]);

        if (
            (int)$lockStmt->fetchColumn() !== 1
        ) {
            throw new \Exception(
                'Ya se está procesando otra operación sobre la membresía. Intentá nuevamente en unos segundos.'
            );
        }

        try {
            return $callback();
        } finally {
            $releaseStmt = $pdo->prepare("
            SELECT RELEASE_LOCK(
                :lock_name
            )
        ");

            $releaseStmt->execute([
                'lock_name' => $lockName,
            ]);
        }
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

    public static function createPreference(
        int $userId,
        string $planCode
    ): array {
        $pdo = self::db();

        $user =
            self::getValidRealEstateUser(
                $userId
            );

        $realEstateId =
            (int)$user['real_estate_id'];

        /*
     * El bloqueo pertenece a la conexión MySQL.
     * Evita que dos solicitudes simultáneas creen
     * dos suscripciones para la misma inmobiliaria.
     */
        $lockName =
            'billing:create:' .
            $realEstateId;

        $lockStmt = $pdo->prepare("
        SELECT GET_LOCK(
            :lock_name,
            15
        )
    ");

        $lockStmt->execute([
            'lock_name' => $lockName,
        ]);

        $lockAcquired =
            (int)$lockStmt->fetchColumn() === 1;

        if (!$lockAcquired) {
            throw new \Exception(
                'Ya se está generando una suscripción. Intentá nuevamente en unos segundos.'
            );
        }

        try {
            return self::createPreferenceLocked(
                $userId,
                $planCode
            );
        } finally {
            /*
         * Se libera incluso si Mercado Pago
         * o la base de datos producen un error.
         */
            $releaseStmt = $pdo->prepare("
            SELECT RELEASE_LOCK(
                :lock_name
            )
        ");

            $releaseStmt->execute([
                'lock_name' => $lockName,
            ]);
        }
    }

    private static function createPreferenceLocked(
        int $userId,
        string $planCode
    ): array {
        $pdo = self::db();

        $frontUrl = trim(
            (string)($_ENV['FRONT_URL'] ?? '')
        );

        if ($frontUrl === '') {
            throw new \Exception(
                "FRONT_URL no configurado"
            );
        }

        $frontUrl = rtrim($frontUrl, '/');

        $user = self::getValidRealEstateUser(
            $userId
        );

        $realEstateId =
            (int)$user['real_estate_id'];

        self::getBillableRealEstate(
            $realEstateId
        );

        $activeMembership =
            self::getActiveMembership(
                $realEstateId
            );

        if ($activeMembership) {
            $until =
                $activeMembership['end_date']
                ?? null;

            throw new \Exception(
                "Ya tenés una membresía activa hasta {$until}."
            );
        }

        $plan = self::getPlanByCode(
            $planCode
        );

        if (!$plan) {
            throw new \Exception(
                "Plan no encontrado"
            );
        }

        /*
     * Si ya existe una suscripción pendiente,
     * reutilizamos el checkout de Mercado Pago
     * en lugar de crear duplicados.
     */
        $st = $pdo->prepare("
    SELECT *
    FROM memberships
    WHERE real_estate_id = :re
      AND status = 0
      AND mp_preapproval_id IS NOT NULL
      AND deleted_at IS NULL
    ORDER BY id DESC
    LIMIT 1
");

        $st->execute([
            're' => $realEstateId,
        ]);

        $pendingMembership =
            $st->fetch();

        /*
 * Si había un checkout pendiente de otro plan,
 * primero cancelamos esa suscripción antes de
 * permitir la creación de una nueva.
 */
        if (
            $pendingMembership &&
            (int)$pendingMembership['plan_id'] !==
            (int)$plan['id']
        ) {
            $oldSubscriptionId =
                trim(
                    (string)
                    $pendingMembership['mp_preapproval_id']
                );

            $cancelledSubscription =
                MercadoPagoClient::updateSubscription(
                    $oldSubscriptionId,
                    [
                        'status' => 'cancelled',
                    ]
                );

            $cancelledStatus =
                (string)(
                    $cancelledSubscription['status']
                    ?? 'cancelled'
                );

            $cancelStmt = $pdo->prepare("
        UPDATE memberships
        SET
            status = 3,
            cancel_at_period_end = 1,
            cancelled_at = NOW(),
            mp_subscription_status = :mp_status,
            mp_subscription_updated_at = NOW()
        WHERE id = :id
          AND status = 0
        LIMIT 1
    ");

            $cancelStmt->execute([
                'mp_status' =>
                $cancelledStatus,

                'id' =>
                (int)$pendingMembership['id'],
            ]);

            $pendingMembership = false;
        }

        if ($pendingMembership) {
            $subscription = null;
            $subscriptionNotFound = false;

            try {
                $subscription =
                    MercadoPagoClient::getSubscriptionById(
                        (string)
                        $pendingMembership['mp_preapproval_id']
                    );
            } catch (\Throwable $e) {
                /*
         * Solamente consideramos obsoleto el registro
         * cuando Mercado Pago confirma que no existe.
         * Un timeout o error temporal no debe generar
         * una segunda suscripción.
         */
                if (
                    str_starts_with(
                        $e->getMessage(),
                        'MP HTTP 404:'
                    )
                ) {
                    $subscriptionNotFound = true;
                } else {
                    throw $e;
                }
            }

            if (!$subscriptionNotFound) {
                $initPoint =
                    $subscription['init_point']
                    ?? null;

                $mpStatus =
                    (string)(
                        $subscription['status']
                        ?? ''
                    );

                if (
                    $mpStatus === 'pending' &&
                    is_string($initPoint) &&
                    $initPoint !== ''
                ) {
                    return [
                        'subscription_id' =>
                        (string)
                        $pendingMembership['mp_preapproval_id'],

                        'preference_id' =>
                        (string)
                        $pendingMembership['mp_preapproval_id'],

                        'init_point' =>
                        $initPoint,

                        'status' =>
                        $mpStatus,

                        'membership_id' =>
                        (int)
                        $pendingMembership['id'],
                    ];
                }

                /*
         * Si ya fue autorizada, no creamos
         * otra mientras llega o se procesa
         * la notificación de Mercado Pago.
         */
                if ($mpStatus === 'authorized') {
                    throw new \Exception(
                        'La suscripción ya fue autorizada y se está procesando.'
                    );
                }

                /*
         * Un estado desconocido o temporal tampoco
         * habilita la creación de otro checkout.
         */
                if (
                    !in_array(
                        $mpStatus,
                        [
                            'cancelled',
                            'paused',
                        ],
                        true
                    )
                ) {
                    throw new \Exception(
                        'No se pudo reutilizar la suscripción pendiente.'
                    );
                }

                /*
         * Una suscripción pausada se cancela antes
         * de reemplazarla por otra.
         */
                if ($mpStatus === 'paused') {
                    MercadoPagoClient::updateSubscription(
                        (string)
                        $pendingMembership['mp_preapproval_id'],
                        [
                            'status' => 'cancelled',
                        ]
                    );

                    $mpStatus = 'cancelled';
                }
            } else {
                $mpStatus = 'not_found';
            }

            /*
     * Cerramos el registro local obsoleto antes
     * de generar una suscripción nueva.
     */
            $closePendingStmt = $pdo->prepare("
        UPDATE memberships
        SET
            status = 3,
            cancel_at_period_end = 1,
            cancelled_at = NOW(),
            mp_subscription_status = :mp_status,
            mp_subscription_updated_at = NOW()
        WHERE id = :id
          AND status = 0
        LIMIT 1
    ");

            $closePendingStmt->execute([
                'mp_status' => $mpStatus,
                'id' =>
                (int)$pendingMembership['id'],
            ]);

            $pendingMembership = false;
        }
        $externalRef =
            "re{$realEstateId}"
            . "-u{$userId}"
            . "-p{$plan['id']}"
            . "-subscription-"
            . bin2hex(
                random_bytes(6)
            );
        $isMpTest =
            filter_var(
                $_ENV['MP_IS_TEST'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

        $payerEmail =
            (string)$user['email'];

        if ($isMpTest) {
            $testPayerEmail = trim(
                (string)(
                    $_ENV['MP_TEST_PAYER_EMAIL']
                    ?? ''
                )
            );

            if ($testPayerEmail === '') {
                throw new \Exception(
                    'MP_TEST_PAYER_EMAIL no configurado'
                );
            }

            $payerEmail =
                $testPayerEmail;
        }
        $payload = [
            'reason' =>
            'PermuOK - '
                . (string)$plan['name'],

            'external_reference' =>
            $externalRef,

            'payer_email' =>
            $payerEmail,

            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' =>
                (float)$plan['price_ars'],
                'currency_id' => 'ARS',
            ],

            'back_url' =>
            $frontUrl . '/billing',

            'status' => 'pending',
        ];

        $subscription =
            MercadoPagoClient::createSubscription(
                $payload
            );

        $subscriptionId =
            (string)(
                $subscription['id']
                ?? ''
            );

        $initPoint =
            (string)(
                $subscription['init_point']
                ?? ''
            );

        $mpStatus =
            (string)(
                $subscription['status']
                ?? 'pending'
            );

        if (
            $subscriptionId === '' ||
            $initPoint === ''
        ) {
            throw new \Exception(
                "Mercado Pago no devolvió "
                    . "una suscripción válida"
            );
        }
        try {

            $st = $pdo->prepare("
       INSERT INTO memberships
(
    real_estate_id,
    billing_user_id,
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
            mp_preapproval_id,
            mp_subscription_status,
            mp_subscription_updated_at,
            created_at
        )
        VALUES
        (
            :real_estate_id,
              :billing_user_id,
            :plan_id,
            NULL,
            1,
            0,
            0,
            NULL,
            NULL,
NULL,
            NULL,
            :max_users,
            :max_agents,
            :max_investors,
            :can_publish_projects,
            :can_view_projects,
            NULL,
            :mp_preapproval_id,
            :mp_subscription_status,
            NOW(),
            NOW()
        )
    ");

            $st->execute([
                'real_estate_id' =>
                $realEstateId,
                'billing_user_id' => $userId,
                'plan_id' =>
                (int)$plan['id'],

                'max_users' =>
                (int)(
                    $plan['max_users']
                    ?? 1
                ),

                'max_agents' =>
                (int)(
                    $plan['max_agents']
                    ?? 0
                ),

                'max_investors' =>
                (int)(
                    $plan['max_investors']
                    ?? 0
                ),

                'can_publish_projects' =>
                (int)(
                    $plan['can_publish_projects']
                    ?? 0
                ),

                'can_view_projects' =>
                (int)(
                    $plan['can_view_projects']
                    ?? 0
                ),

                'mp_preapproval_id' =>
                $subscriptionId,

                'mp_subscription_status' =>
                $mpStatus,
            ]);
        } catch (\Throwable $e) {
            /*
     * Mercado Pago ya creó la suscripción,
     * pero no pudimos registrarla localmente.
     * Intentamos cancelarla para evitar
     * cobros sin membresía asociada.
     */
            try {
                MercadoPagoClient::updateSubscription(
                    $subscriptionId,
                    [
                        'status' => 'cancelled',
                    ]
                );
            } catch (\Throwable $cleanupError) {
                error_log(
                    'No se pudo cancelar una suscripción '
                        . 'de Mercado Pago después de fallar '
                        . 'su registro local.'
                );
            }

            throw $e;
        }
        return [
            'subscription_id' =>
            $subscriptionId,

            /*
         * Lo mantenemos temporalmente
         * para no romper frontend viejo.
         */
            'preference_id' =>
            $subscriptionId,

            'init_point' =>
            $initPoint,

            'external_reference' =>
            $externalRef,

            'status' =>
            $mpStatus,

            'membership_id' =>
            (int)$pdo->lastInsertId(),
        ];
    }

    private static function getBillableRealEstate(int $realEstateId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT *
        FROM real_estates
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'id' => $realEstateId,
        ]);

        $realEstate = $st->fetch();

        if (!$realEstate) {
            throw new \Exception('No se encontró la inmobiliaria.');
        }

        $profileStatus = (int)($realEstate['profile_status'] ?? 0);
        $validationStatus = (int)($realEstate['validation_status'] ?? 0);

        $isBillable =
            $validationStatus === 1 ||
            in_array($profileStatus, [2, 4], true);

        if (!$isBillable) {
            throw new \Exception(
                'Tu perfil todavía no está habilitado para contratar una membresía.'
            );
        }

        return $realEstate;
    }

    public static function previewPlanChange(int $userId, string $targetPlanCode): array
    {
        $user = self::getValidRealEstateUser($userId);
        $membership =
            self::getRequiredActiveMembership(
                (int)$user['real_estate_id']
            );

        if (
            (int)(
                $membership['cancel_at_period_end']
                ?? 0
            ) === 1
        ) {
            throw new \Exception(
                'La renovación de la membresía está cancelada. No se puede cambiar de plan.'
            );
        }

        $currentPlan =
            self::getPlanById(
                (int)$membership['plan_id']
            );
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

    public static function confirmPlanChange(
        int $userId,
        string $targetPlanCode,
        string $mode
    ): array {
        return self::runWithMembershipLock(
            $userId,
            static function () use (
                $userId,
                $targetPlanCode,
                $mode
            ): array {
                return self::confirmPlanChangeLocked(
                    $userId,
                    $targetPlanCode,
                    $mode
                );
            }
        );
    }

    private static function confirmPlanChangeLocked(int $userId, string $targetPlanCode, string $mode): array
    {
        $pdo = self::db();

        $user = self::getValidRealEstateUser($userId);
        $membership =
            self::getRequiredActiveMembership(
                (int)$user['real_estate_id']
            );

        if (
            (int)(
                $membership['cancel_at_period_end']
                ?? 0
            ) === 1
        ) {
            throw new \Exception(
                'La renovación de la membresía está cancelada. No se puede cambiar de plan.'
            );
        }

        $currentPlan =
            self::getPlanById(
                (int)$membership['plan_id']
            );
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
            $subscriptionId = trim(
                (string)(
                    $membership['mp_preapproval_id']
                    ?? ''
                )
            );

            /*
     * Si pertenece al nuevo sistema recurrente,
     * dejamos configurado desde ahora el importe
     * que deberá cobrarse en la próxima renovación.
     *
     * El plan local NO cambia todavía.
     */
            if ($subscriptionId !== '') {
                $subscription =
                    MercadoPagoClient::updateSubscription(
                        $subscriptionId,
                        [
                            'auto_recurring' => [
                                'transaction_amount' =>
                                (float)$targetPlan['price_ars'],

                                'currency_id' => 'ARS',
                            ],
                        ]
                    );

                $mpStatus = (string)(
                    $subscription['status']
                    ?? (
                        $membership['mp_subscription_status']
                        ?? ''
                    )
                );
            } else {
                /*
         * Membresía histórica/manual:
         * conserva el comportamiento local anterior.
         */
                $mpStatus = null;
            }
            try {
                $st = $pdo->prepare("
        UPDATE memberships
        SET
            scheduled_plan_id = :scheduled_plan_id,
            scheduled_change_at = :scheduled_change_at,
            cancel_at_period_end = 0,
            cancelled_at = NULL,

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
                    'scheduled_plan_id' =>
                    (int)$targetPlan['id'],

                    'scheduled_change_at' =>
                    $membership['end_date']
                        . ' 00:00:00',

                    'mp_status' =>
                    $mpStatus,

                    'mp_status_value' =>
                    $mpStatus,

                    'mp_status_date' =>
                    $mpStatus,

                    'id' =>
                    (int)$membership['id'],
                ]);
            } catch (\Throwable $e) {
                /*
     * Mercado Pago ya recibió el nuevo importe,
     * pero la programación local falló.
     * Restauramos el importe del plan actual.
     */
                if ($subscriptionId !== '') {
                    try {
                        MercadoPagoClient::updateSubscription(
                            $subscriptionId,
                            [
                                'auto_recurring' => [
                                    'transaction_amount' =>
                                    (float)$currentPlan['price_ars'],

                                    'currency_id' =>
                                    'ARS',
                                ],
                            ]
                        );
                    } catch (\Throwable $cleanupError) {
                        error_log(
                            'No se pudo restaurar el importe '
                                . 'de una suscripción después de '
                                . 'fallar la programación de downgrade.'
                        );
                    }
                }

                throw $e;
            }
            return [
                'scheduled' => true,
                'change_type' => 'downgrade',
                'mode' => 'next_cycle',

                'current_plan' =>
                $currentPlan,

                'target_plan' =>
                $targetPlan,

                'subscription_updated' =>
                $subscriptionId !== '',

                'effective_at' =>
                $membership['end_date'],

                'message' =>
                'El cambio quedó programado para la próxima renovación.',
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

        try {
            $pref =
                self::buildMercadoPagoPreference(
                    title: 'Upgrade de plan - ' .
                        (string)$targetPlan['name'],

                    amount: $amountNow,

                    externalRef: $externalRef
                );

            $resp =
                MercadoPagoClient::createPreference(
                    $pref
                );

            if (empty($resp['id'])) {
                throw new \Exception(
                    'No se pudo crear la preferencia'
                );
            }

            $mpToken =
                trim(
                    (string)(
                        $_ENV['MP_ACCESS_TOKEN']
                        ?? ''
                    )
                );

            $isTest =
                str_starts_with(
                    $mpToken,
                    'TEST-'
                );

            $initPoint =
                $isTest
                ? (
                    $resp['sandbox_init_point']
                    ?? null
                )
                : (
                    $resp['init_point']
                    ?? null
                );

            if (!$initPoint) {
                throw new \Exception(
                    'No se pudo obtener el link de pago'
                );
            }

            $st = $pdo->prepare("
        UPDATE payments
        SET
            preference_id = :preference_id,
            status = 'pending',
            mp_status_detail = NULL
        WHERE id = :id
        LIMIT 1
    ");

            $st->execute([
                'preference_id' =>
                (string)$resp['id'],

                'id' =>
                $paymentRowId,
            ]);
        } catch (\Throwable $e) {
            /*
     * El pago local ya existe, pero no se pudo
     * generar o registrar la preferencia externa.
     * Lo cerramos para que no permanezca
     * indefinidamente como pendiente.
     */
            try {
                $failed = $pdo->prepare("
            UPDATE payments
            SET
                status = 'cancelled',
                mp_status_detail =
                    'preference_creation_failed'
            WHERE id = :id
              AND status = 'created'
            LIMIT 1
        ");

                $failed->execute([
                    'id' =>
                    $paymentRowId,
                ]);
            } catch (\Throwable $cleanupError) {
                error_log(
                    'No se pudo cerrar un pago local '
                        . 'después de fallar la creación '
                        . 'de su preferencia.'
                );
            }

            throw $e;
        }

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

    public static function cancelMembership(
        int $userId
    ): array {
        return self::runWithMembershipLock(
            $userId,
            static function () use (
                $userId
            ): array {
                return self::cancelMembershipLocked(
                    $userId
                );
            }
        );
    }

    private static function cancelMembershipLocked(int $userId): array
    {
        $pdo = self::db();

        $user = self::getValidRealEstateUser(
            $userId
        );

        $membership =
            self::getRequiredActiveMembership(
                (int)$user['real_estate_id']
            );

        $subscriptionId = trim(
            (string)(
                $membership['mp_preapproval_id']
                ?? ''
            )
        );

        /*
     * Si esta membresía pertenece al nuevo
     * sistema recurrente, cancelamos también
     * la suscripción en Mercado Pago.
     *
     * Esto evita que se genere otro cobro.
     * El acceso local sigue vigente hasta
     * end_date.
     */
        if ($subscriptionId !== '') {
            $subscription =
                MercadoPagoClient::updateSubscription(
                    $subscriptionId,
                    [
                        'status' => 'cancelled',
                    ]
                );

            $mpStatus = (string)(
                $subscription['status']
                ?? 'cancelled'
            );
        } else {
            /*
         * Membresías históricas/manuales
         * no tienen preapproval.
         */
            $mpStatus = null;
        }

        $st = $pdo->prepare("
        UPDATE memberships
        SET
            cancel_at_period_end = 1,
            cancelled_at = NOW(),
            scheduled_plan_id = NULL,
            scheduled_change_at = NULL,
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
            'mp_status' =>
            $mpStatus,

            'mp_status_value' =>
            $mpStatus,

            'mp_status_date' =>
            $mpStatus,

            'id' =>
            (int)$membership['id'],
        ]);

        return [
            'cancelled' => true,

            'subscription_cancelled' =>
            $subscriptionId !== '',

            'effective_until' =>
            $membership['end_date'],

            'message' =>
            'La renovación automática fue cancelada. '
                . 'La membresía seguirá activa hasta '
                . $membership['end_date']
                . '.',
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
