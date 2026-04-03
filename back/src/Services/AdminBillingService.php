<?php

namespace App\Services;

use PDO;

class AdminBillingService
{
    private const MEMBERSHIP_PENDING = 0;
    private const MEMBERSHIP_ACTIVE = 1;
    private const MEMBERSHIP_EXPIRED = 2;
    private const MEMBERSHIP_CANCELLED = 3;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'active' => 'active',
            'none' => 'none',
            'cancel_at_period_end' => 'cancel_at_period_end',
            'scheduled_change' => 'scheduled_change',
            'pending' => 'pending',
            'expired' => 'expired',
            'cancelled' => 'cancelled',
            default => 'active',
        };
    }

    private static function buildSearchWhere(?string $q, array &$params): string
    {
        $q = trim((string)$q);

        if ($q === '') {
            return '';
        }

        $params['q'] = '%' . $q . '%';

        return "
            AND (
                re.name LIKE :q
                OR re.legal_name LIKE :q
                OR re.email LIKE :q
                OR re.phone LIKE :q
                OR re.cuit LIKE :q
                OR owner.first_name LIKE :q
                OR owner.last_name LIKE :q
                OR owner.email LIKE :q
            )
        ";
    }

    /**
     * Estado principal de facturación.
     * cancel_at_period_end y scheduled_change NO reemplazan active.
     */
    private static function resolveMembershipAdminStatus(?array $row): string
    {
        if (!$row || empty($row['membership_id'])) {
            return 'none';
        }

        $status = (int)($row['membership_status_raw'] ?? -1);

        return match ($status) {
            self::MEMBERSHIP_PENDING => 'pending',
            self::MEMBERSHIP_ACTIVE => 'active',
            self::MEMBERSHIP_EXPIRED => 'expired',
            self::MEMBERSHIP_CANCELLED => 'cancelled',
            default => 'none',
        };
    }

    private static function hasCancelAtPeriodEnd(?array $row): bool
    {
        return !empty($row['membership_id']) && (int)($row['cancel_at_period_end'] ?? 0) === 1;
    }

    private static function hasScheduledChange(?array $row): bool
    {
        return !empty($row['membership_id']) && !empty($row['scheduled_plan_id']);
    }

    private static function buildStatusFilter(string $normalizedStatus, array $rows): array
    {
        return array_values(array_filter($rows, function ($row) use ($normalizedStatus) {
            $status = self::resolveMembershipAdminStatus($row);

            return match ($normalizedStatus) {
                'active' => $status === 'active',
                'none' => $status === 'none',
                'pending' => $status === 'pending',
                'expired' => $status === 'expired',
                'cancelled' => $status === 'cancelled',
                'cancel_at_period_end' => $status === 'active' && self::hasCancelAtPeriodEnd($row),
                'scheduled_change' => $status === 'active' && self::hasScheduledChange($row),
                default => $status === 'active',
            };
        }));
    }

    private static function mapRow(array $row): array
    {
        $membershipStatus = self::resolveMembershipAdminStatus($row);
        $hasCancelAtPeriodEnd = self::hasCancelAtPeriodEnd($row);
        $hasScheduledChange = self::hasScheduledChange($row);

        return [
            'real_estate_id' => (int)$row['real_estate_id'],
            'real_estate_name' => $row['real_estate_name'] ?? null,
            'real_estate_legal_name' => $row['real_estate_legal_name'] ?? null,
            'real_estate_cuit' => $row['real_estate_cuit'] ?? null,
            'real_estate_email' => $row['real_estate_email'] ?? null,
            'real_estate_phone' => $row['real_estate_phone'] ?? null,

            'owner_id' => $row['owner_id'] ? (int)$row['owner_id'] : null,
            'owner_name' => trim(($row['owner_first_name'] ?? '') . ' ' . ($row['owner_last_name'] ?? '')) ?: null,
            'owner_email' => $row['owner_email'] ?? null,
            'owner_phone' => $row['owner_phone'] ?? null,
            'owner_is_active' => isset($row['owner_is_active']) ? (int)$row['owner_is_active'] : null,

            'membership_status' => $membershipStatus,
            'has_cancel_at_period_end' => $hasCancelAtPeriodEnd,
            'has_scheduled_change' => $hasScheduledChange,

            'membership' => !empty($row['membership_id']) ? [
                'id' => (int)$row['membership_id'],
                'status' => isset($row['membership_status_raw']) ? (int)$row['membership_status_raw'] : null,
                'billing_cycle' => isset($row['billing_cycle']) ? (int)$row['billing_cycle'] : null,
                'cancel_at_period_end' => (int)($row['cancel_at_period_end'] ?? 0),
                'cancelled_at' => $row['cancelled_at'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'scheduled_change_at' => $row['scheduled_change_at'] ?? null,
                'mp_last_payment_id' => $row['mp_last_payment_id'] ? (int)$row['mp_last_payment_id'] : null,
            ] : null,

            'plan' => !empty($row['plan_id']) ? [
                'id' => (int)$row['plan_id'],
                'code' => $row['plan_code'] ?? null,
                'name' => $row['plan_name'] ?? null,
                'price_ars' => isset($row['plan_price_ars']) ? (int)$row['plan_price_ars'] : null,
                'duration_days' => isset($row['plan_duration_days']) ? (int)$row['plan_duration_days'] : null,
                'max_users' => isset($row['plan_max_users']) ? (int)$row['plan_max_users'] : null,
                'max_agents' => isset($row['plan_max_agents']) ? (int)$row['plan_max_agents'] : null,
                'max_investors' => isset($row['plan_max_investors']) ? (int)$row['plan_max_investors'] : null,
                'can_publish_projects' => (int)($row['plan_can_publish_projects'] ?? 0),
                'can_view_projects' => (int)($row['plan_can_view_projects'] ?? 0),
            ] : null,

            'scheduled_plan' => !empty($row['scheduled_plan_id']) ? [
                'id' => (int)$row['scheduled_plan_id'],
                'code' => $row['scheduled_plan_code'] ?? null,
                'name' => $row['scheduled_plan_name'] ?? null,
                'price_ars' => isset($row['scheduled_plan_price_ars']) ? (int)$row['scheduled_plan_price_ars'] : null,
            ] : null,

            'last_payment' => !empty($row['payment_id']) ? [
                'id' => (int)$row['payment_id'],
                'amount_ars' => isset($row['payment_amount_ars']) ? (int)$row['payment_amount_ars'] : null,
                'currency' => $row['payment_currency'] ?? null,
                'status' => $row['payment_status'] ?? null,
                'provider' => $row['payment_provider'] ?? null,
                'mp_payment_id' => $row['payment_mp_payment_id'] ? (int)$row['payment_mp_payment_id'] : null,
                'mp_status' => $row['payment_mp_status'] ?? null,
                'mp_status_detail' => $row['payment_mp_status_detail'] ?? null,
                'external_reference' => $row['payment_external_reference'] ?? null,
                'paid_at' => $row['payment_paid_at'] ?? null,
                'approved_at' => $row['payment_approved_at'] ?? null,
                'created_at' => $row['payment_created_at'] ?? null,
            ] : null,
        ];
    }

    private static function baseRows(?string $q = null): array
    {
        $pdo = self::db();

        $params = [];
        $whereQ = self::buildSearchWhere($q, $params);

        $sql = "
            SELECT
                re.id AS real_estate_id,
                re.name AS real_estate_name,
                re.legal_name AS real_estate_legal_name,
                re.cuit AS real_estate_cuit,
                re.email AS real_estate_email,
                re.phone AS real_estate_phone,

                owner.id AS owner_id,
                owner.first_name AS owner_first_name,
                owner.last_name AS owner_last_name,
                owner.email AS owner_email,
                owner.phone AS owner_phone,
                owner.is_active AS owner_is_active,

                m.id AS membership_id,
                m.status AS membership_status_raw,
                m.plan_id,
                m.scheduled_plan_id,
                m.billing_cycle,
                m.cancel_at_period_end,
                m.cancelled_at,
                m.start_date,
                m.end_date,
                m.scheduled_change_at,
                m.mp_last_payment_id,

                p.code AS plan_code,
                p.name AS plan_name,
                p.price_ars AS plan_price_ars,
                p.duration_days AS plan_duration_days,
                p.max_users AS plan_max_users,
                p.max_agents AS plan_max_agents,
                p.max_investors AS plan_max_investors,
                p.can_publish_projects AS plan_can_publish_projects,
                p.can_view_projects AS plan_can_view_projects,

                sp.code AS scheduled_plan_code,
                sp.name AS scheduled_plan_name,
                sp.price_ars AS scheduled_plan_price_ars,

                pay.id AS payment_id,
                pay.amount_ars AS payment_amount_ars,
                pay.currency AS payment_currency,
                pay.status AS payment_status,
                pay.provider AS payment_provider,
                pay.mp_payment_id AS payment_mp_payment_id,
                pay.mp_status AS payment_mp_status,
                pay.mp_status_detail AS payment_mp_status_detail,
                pay.external_reference AS payment_external_reference,
                pay.paid_at AS payment_paid_at,
                pay.approved_at AS payment_approved_at,
                pay.created_at AS payment_created_at

            FROM real_estates re
            LEFT JOIN users owner
                ON owner.real_estate_id = re.id
               AND owner.role = 2
               AND owner.deleted_at IS NULL

            LEFT JOIN memberships m
                ON m.id = (
                    SELECT m2.id
                    FROM memberships m2
                    WHERE m2.real_estate_id = re.id
                      AND m2.deleted_at IS NULL
                    ORDER BY m2.id DESC
                    LIMIT 1
                )

            LEFT JOIN plans p
                ON p.id = m.plan_id
               AND p.deleted_at IS NULL

            LEFT JOIN plans sp
                ON sp.id = m.scheduled_plan_id
               AND sp.deleted_at IS NULL

            LEFT JOIN payments pay
                ON pay.id = (
                    SELECT p2.id
                    FROM payments p2
                    WHERE p2.real_estate_id = re.id
                    ORDER BY p2.id DESC
                    LIMIT 1
                )

            WHERE re.deleted_at IS NULL
            {$whereQ}
            ORDER BY re.created_at DESC, re.id DESC
        ";

        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();

        return $st->fetchAll() ?: [];
    }

    public static function counts(?string $q = null): array
    {
        $rows = self::baseRows($q);

        $counts = [
            'active' => 0,
            'none' => 0,
            'cancel_at_period_end' => 0,
            'scheduled_change' => 0,
            'pending' => 0,
            'expired' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $status = self::resolveMembershipAdminStatus($row);

            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            if ($status === 'active' && self::hasCancelAtPeriodEnd($row)) {
                $counts['cancel_at_period_end']++;
            }

            if ($status === 'active' && self::hasScheduledChange($row)) {
                $counts['scheduled_change']++;
            }
        }

        return $counts;
    }

    public static function list(string $status, int $page, int $perPage, ?string $q = null): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50);
        $offset = ($page - 1) * $perPage;

        $normalizedStatus = self::normalizeStatus($status);

        $rows = self::baseRows($q);
        $rows = self::buildStatusFilter($normalizedStatus, $rows);

        $total = count($rows);
        $pagedRows = array_slice($rows, $offset, $perPage);
        $items = array_map(fn($row) => self::mapRow($row), $pagedRows);

        $from = $total === 0 ? 0 : ($offset + 1);
        $to = min($offset + $perPage, $total);

        return [
            'items' => $items,
            'meta' => [
                'status' => $normalizedStatus,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'pages' => (int)ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public static function detail(int $realEstateId): array
    {
        $rows = self::baseRows(null);

        $row = null;
        foreach ($rows as $candidate) {
            if ((int)$candidate['real_estate_id'] === $realEstateId) {
                $row = $candidate;
                break;
            }
        }

        if (!$row) {
            throw new \Exception("Inmobiliaria no encontrada");
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                p.id,
                p.real_estate_id,
                p.user_id,
                p.plan_id,
                pl.name AS plan_name,
                p.provider,
                p.preference_id,
                p.external_reference,
                p.mp_payment_id,
                p.mp_status,
                p.mp_status_detail,
                p.amount_ars,
                p.currency,
                p.status,
                p.paid_at,
                p.created_at,
                p.approved_at,
                p.updated_at
            FROM payments p
            LEFT JOIN plans pl ON pl.id = p.plan_id
            WHERE p.real_estate_id = :real_estate_id
            ORDER BY p.id DESC
            LIMIT 20
        ");
        $st->execute(['real_estate_id' => $realEstateId]);
        $payments = $st->fetchAll() ?: [];

        return [
            'summary' => self::mapRow($row),
            'payments' => $payments,
        ];
    }
}