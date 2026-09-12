<?php

namespace App\Services;

use PDO;

class AdminRealEstateService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function normStatus(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'incomplete' => 'incomplete',
            'ready_for_review' => 'ready_for_review',
            'initial_review' => 'initial_review',
            'changes_pending' => 'changes_pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'initial_review',
        };
    }

    private static function buildSearchWhere(?string $q, array &$params): string
    {
        $q = trim((string)$q);

        if ($q === '') {
            return '';
        }

        $params['q'] = '%' . $q . '%';

        return " AND (
            r.name LIKE :q
            OR r.legal_name LIKE :q
            OR r.email LIKE :q
            OR r.phone LIKE :q
            OR r.cuit LIKE :q
        ) ";
    }

    private static function mapItem(array $row): array
    {
        $row['admin_profile_stage'] = RealEstateService::resolveAdminProfileStage($row);
        return $row;
    }

    public static function counts(?string $q = null): array
    {
        $pdo = self::db();

        $params = [];
        $whereQ = self::buildSearchWhere($q, $params);

        $sql = "
            SELECT
              r.id,
              r.name,
              r.legal_name,
              r.cuit,
              r.email,
              r.phone,
              r.address,
              r.address_place_id,
              r.address_lat,
              r.address_lng,
              r.website,
              r.instagram,
              r.facebook,
              r.status,
              r.profile_status,
              r.validation_status,
              r.validation_note,
              r.review_requested_at,
              r.changes_requested_at,
              r.approved_at,
              r.approved_by,
              r.created_at,
              r.validated_at
            FROM real_estates r
            WHERE r.deleted_at IS NULL
            {$whereQ}
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll() ?: [];

        $counts = [
            'incomplete' => 0,
            'ready_for_review' => 0,
            'initial_review' => 0,
            'changes_pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($rows as $row) {
            $stage = RealEstateService::resolveAdminProfileStage($row);

            if (isset($counts[$stage])) {
                $counts[$stage]++;
            }
        }

        return $counts;
    }

    public static function list(string $status, int $page, int $perPage, ?string $q = null): array
    {
        $pdo = self::db();

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50);

        $normalizedStatus = self::normStatus($status);

        $params = [];
        $where = " r.deleted_at IS NULL ";
        $where .= self::buildSearchWhere($q, $params);

        $orderBy = " r.id DESC ";

        if (in_array($normalizedStatus, ['incomplete', 'ready_for_review'], true)) {
            $where .= " AND r.profile_status = :profile_status ";
            $params['profile_status'] = RealEstateProfileStatus::DRAFT;
            $orderBy = " r.created_at DESC, r.id DESC ";
        } else {
            switch ($normalizedStatus) {
                case 'initial_review':
                    $where .= " AND r.profile_status = :profile_status ";
                    $params['profile_status'] = RealEstateProfileStatus::INITIAL_REVIEW;
                    $orderBy = " COALESCE(r.review_requested_at, r.created_at) DESC, r.id DESC ";
                    break;

                case 'changes_pending':
                    $where .= " AND r.profile_status = :profile_status ";
                    $params['profile_status'] = RealEstateProfileStatus::CHANGES_PENDING;
                    $orderBy = " COALESCE(r.changes_requested_at, r.created_at) DESC, r.id DESC ";
                    break;

                case 'approved':
                    $where .= " AND r.profile_status = :profile_status ";
                    $params['profile_status'] = RealEstateProfileStatus::APPROVED;
                    $orderBy = " COALESCE(r.approved_at, r.validated_at, r.created_at) DESC, r.id DESC ";
                    break;

                case 'rejected':
                    $where .= " AND r.profile_status = :profile_status ";
                    $params['profile_status'] = RealEstateProfileStatus::REJECTED;
                    $orderBy = " COALESCE(r.validated_at, r.review_requested_at, r.created_at) DESC, r.id DESC ";
                    break;

                default:
                    $where .= " AND r.profile_status = :profile_status ";
                    $params['profile_status'] = RealEstateProfileStatus::INITIAL_REVIEW;
                    $orderBy = " COALESCE(r.review_requested_at, r.created_at) DESC, r.id DESC ";
                    break;
            }
        }

        $sql = "
            SELECT
              r.id,
              r.name,
              r.legal_name,
              r.cuit,
              r.email,
              r.phone,
              r.address,
              r.address_place_id,
              r.address_lat,
              r.address_lng,
              r.website,
              r.instagram,
              r.facebook,
              r.status,
              r.profile_status,
              r.validation_status,
              r.validation_note,
              r.review_requested_at,
              r.changes_requested_at,
              r.approved_at,
              r.approved_by,
              u.email AS approved_by_email,
              r.created_at,
              r.validated_at
            FROM real_estates r
            LEFT JOIN users u ON u.id = r.approved_by
            WHERE {$where}
            ORDER BY {$orderBy}
        ";

        $st = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }

        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $items = array_map(fn($row) => self::mapItem($row), $rows);

        if (in_array($normalizedStatus, ['incomplete', 'ready_for_review'], true)) {
            $items = array_values(array_filter(
                $items,
                fn($item) => ($item['admin_profile_stage'] ?? null) === $normalizedStatus
            ));
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($items, $offset, $perPage);

        $from = $total === 0 ? 0 : ($offset + 1);
        $to = min($offset + $perPage, $total);

        return [
            'items' => $pagedItems,
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

    public static function operationalCounts(
        ?string $q = null
    ): array {
        $pdo = self::db();

        $params = [];
        $whereQ =
            self::buildSearchWhere(
                $q,
                $params
            );

        $sql = "
        SELECT
            SUM(
                CASE
                    WHEN r.status = 1
                    THEN 1
                    ELSE 0
                END
            ) AS active,

            SUM(
                CASE
                    WHEN r.status = 0
                    AND r.profile_status IN (
                        :approved,
                        :changes_pending
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS suspended,

            COUNT(*) AS total

        FROM real_estates r

        WHERE r.deleted_at IS NULL
          AND r.profile_status IN (
              :approved_filter,
              :changes_pending_filter
          )

        {$whereQ}
    ";

        $st = $pdo->prepare($sql);

        $st->execute([
            ...$params,

            'approved' =>
            RealEstateProfileStatus::APPROVED,

            'changes_pending' =>
            RealEstateProfileStatus::CHANGES_PENDING,

            'approved_filter' =>
            RealEstateProfileStatus::APPROVED,

            'changes_pending_filter' =>
            RealEstateProfileStatus::CHANGES_PENDING,
        ]);

        $row =
            $st->fetch(PDO::FETCH_ASSOC)
            ?: [];

        return [
            'total' =>
            (int)($row['total'] ?? 0),

            'active' =>
            (int)($row['active'] ?? 0),

            'suspended' =>
            (int)($row['suspended'] ?? 0),
        ];
    }

    public static function operationalList(
        string $status,
        int $page,
        int $perPage,
        ?string $q = null
    ): array {
        $pdo = self::db();

        $page =
            max(1, $page);

        $perPage =
            min(
                max(1, $perPage),
                50
            );

        $status =
            strtolower(
                trim($status)
            );

        if (
            !in_array(
                $status,
                [
                    'all',
                    'active',
                    'suspended',
                ],
                true
            )
        ) {
            $status = 'all';
        }

        $params = [];

        $where = "
        r.deleted_at IS NULL

        AND r.profile_status IN (
            :approved,
            :changes_pending
        )
    ";

        $params['approved'] =
            RealEstateProfileStatus::APPROVED;

        $params['changes_pending'] =
            RealEstateProfileStatus::CHANGES_PENDING;

        $where .=
            self::buildSearchWhere(
                $q,
                $params
            );

        if ($status === 'active') {
            $where .= "
            AND r.status = 1
        ";
        }

        if ($status === 'suspended') {
            $where .= "
            AND r.status = 0
        ";
        }

        /*
    |--------------------------------------------------------------------------
    | Total
    |--------------------------------------------------------------------------
    */

        $countSql = "
        SELECT COUNT(*)

        FROM real_estates r

        WHERE {$where}
    ";

        $countSt =
            $pdo->prepare(
                $countSql
            );

        foreach (
            $params as $key => $value
        ) {
            $countSt->bindValue(
                ':' . $key,
                $value
            );
        }

        $countSt->execute();

        $total =
            (int)$countSt->fetchColumn();

        $offset =
            ($page - 1)
            * $perPage;

        /*
    |--------------------------------------------------------------------------
    | Listado
    |--------------------------------------------------------------------------
    */

        $sql = "
        SELECT
            r.id,
            r.name,
            r.legal_name,
            r.cuit,
            r.email,
            r.phone,
            r.address,
            r.status,
            r.profile_status,
            r.approved_at,
            r.created_at,

            (
                SELECT COUNT(*)
                FROM users u
                WHERE u.real_estate_id = r.id
                  AND u.deleted_at IS NULL
            ) AS users_count,

            (
                SELECT COUNT(*)
                FROM properties p
                WHERE p.real_estate_id = r.id
                  AND p.deleted_at IS NULL
            ) AS properties_count,

            (
                SELECT COUNT(*)
                FROM search_requests sr
                WHERE sr.real_estate_id = r.id
                  AND sr.deleted_at IS NULL
            ) AS search_requests_count,

            (
                SELECT COUNT(*)
                FROM developments d
                WHERE d.real_estate_id = r.id
                  AND d.deleted_at IS NULL
            ) AS developments_count,

            m.id AS membership_id,
            m.status AS membership_raw_status,
            m.start_date AS membership_start_date,
            m.end_date AS membership_end_date,

            pl.id AS plan_id,
            pl.name AS plan_name,
            pl.code AS plan_code

        FROM real_estates r

        LEFT JOIN memberships m
            ON m.id = (
                SELECT m2.id
                FROM memberships m2
                WHERE m2.real_estate_id = r.id
                  AND m2.deleted_at IS NULL
                ORDER BY m2.id DESC
                LIMIT 1
            )

        LEFT JOIN plans pl
            ON pl.id = m.plan_id

        WHERE {$where}

        ORDER BY
            r.name ASC,
            r.id DESC

        LIMIT {$perPage}
        OFFSET {$offset}
    ";

        $st =
            $pdo->prepare(
                $sql
            );

        foreach (
            $params as $key => $value
        ) {
            $st->bindValue(
                ':' . $key,
                $value
            );
        }

        $st->execute();

        $rows =
            $st->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $items =
            array_map(
                function (
                    array $row
                ): array {
                    $rawMembershipStatus =
                        $row['membership_raw_status'] !== null
                        ? (int)$row['membership_raw_status']
                        : null;

                    $membershipStatus =
                        'none';

                    if (
                        $rawMembershipStatus === 1
                        &&
                        !empty($row['membership_end_date'])
                        &&
                        $row['membership_end_date'] >= date('Y-m-d')
                    ) {
                        $membershipStatus =
                            'active';
                    } elseif (
                        $rawMembershipStatus === 1
                    ) {
                        $membershipStatus =
                            'expired';
                    } elseif (
                        $rawMembershipStatus === 0
                    ) {
                        $membershipStatus =
                            'pending';
                    } elseif (
                        $rawMembershipStatus === 2
                    ) {
                        $membershipStatus =
                            'expired';
                    } elseif (
                        $rawMembershipStatus === 3
                    ) {
                        $membershipStatus =
                            'cancelled';
                    }

                    return [
                        'id' =>
                        (int)$row['id'],

                        'name' =>
                        $row['name'],

                        'legal_name' =>
                        $row['legal_name'],

                        'cuit' =>
                        $row['cuit'],

                        'email' =>
                        $row['email'],

                        'phone' =>
                        $row['phone'],

                        'address' =>
                        $row['address'],

                        'status' =>
                        (int)$row['status'],

                        'profile_status' =>
                        (int)$row['profile_status'],

                        'approved_at' =>
                        $row['approved_at'],

                        'created_at' =>
                        $row['created_at'],

                        'users_count' =>
                        (int)$row['users_count'],

                        'properties_count' =>
                        (int)$row['properties_count'],

                        'search_requests_count' =>
                        (int)$row['search_requests_count'],

                        'developments_count' =>
                        (int)$row['developments_count'],

                        'membership_status' =>
                        $membershipStatus,

                        'membership' =>
                        $row['membership_id'] !== null
                            ? [
                                'id' =>
                                (int)$row['membership_id'],

                                'status' =>
                                $rawMembershipStatus,

                                'start_date' =>
                                $row['membership_start_date'],

                                'end_date' =>
                                $row['membership_end_date'],
                            ]
                            : null,

                        'plan' =>
                        $row['plan_id'] !== null
                            ? [
                                'id' =>
                                (int)$row['plan_id'],

                                'name' =>
                                $row['plan_name'],

                                'code' =>
                                $row['plan_code'],
                            ]
                            : null,
                    ];
                },
                $rows
            );

        return [
            'items' =>
            $items,

            'meta' => [
                'status' =>
                $status,

                'page' =>
                $page,

                'per_page' =>
                $perPage,

                'total' =>
                $total,

                'pages' =>
                (int)ceil(
                    $total
                        / max(
                            1,
                            $perPage
                        )
                ),
            ],
        ];
    }

    public static function validate(
        int $adminUserId,
        int $realEstateId,
        string $action,
        ?string $validationNote = null
    ): array {
        $pdo = self::db();

        $action = strtolower(trim($action));
        $validationNote = trim((string)$validationNote);

        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new \Exception("Acción inválida");
        }

        if ($action === 'reject' && $validationNote === '') {
            throw new \Exception("El motivo del rechazo es requerido");
        }

        $st = $pdo->prepare("
        SELECT
            id,
            profile_status,
            status,
            review_requested_at,
            changes_requested_at
        FROM real_estates
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'id' => $realEstateId,
        ]);

        $re = $st->fetch();

        if (!$re) {
            throw new \Exception("Inmobiliaria no encontrada");
        }

        $currentProfileStatus =
            (int)($re['profile_status'] ?? 0);

        if (!in_array(
            $currentProfileStatus,
            [
                RealEstateProfileStatus::INITIAL_REVIEW,
                RealEstateProfileStatus::CHANGES_PENDING,
            ],
            true
        )) {
            throw new \Exception(
                "La solicitud no está pendiente de revisión"
            );
        }

        $pdo->beginTransaction();

        try {
            if ($action === 'approve') {
                $st = $pdo->prepare("
                UPDATE real_estates
                SET
                    status = 1,
                    profile_status = :approved_profile_status,
                    validation_status = 1,
                    validation_note = NULL,
                    approved_at = NOW(),
                    approved_by = :admin_id,
                    validated_at = NOW(),
                    review_requested_at = CASE
                        WHEN review_requested_at IS NULL
                            THEN NOW()
                        ELSE review_requested_at
                    END,
                    changes_requested_at = NULL
                WHERE id = :id
                LIMIT 1
            ");

                $st->execute([
                    'approved_profile_status' =>
                    RealEstateProfileStatus::APPROVED,
                    'admin_id' => $adminUserId,
                    'id' => $realEstateId,
                ]);
            } else {
                $st = $pdo->prepare("
                UPDATE real_estates
                SET
                    status = 0,
                    profile_status = :rejected_profile_status,
                    validation_status = 2,
                    validation_note = :validation_note,
                    approved_at = NULL,
                    approved_by = NULL,
                    validated_at = NOW(),
                    changes_requested_at = NULL
                WHERE id = :id
                LIMIT 1
            ");

                $st->execute([
                    'rejected_profile_status' =>
                    RealEstateProfileStatus::REJECTED,
                    'validation_note' =>
                    $validationNote,
                    'id' =>
                    $realEstateId,
                ]);
            }

            $pdo->commit();

            return [
                'real_estate_id' =>
                $realEstateId,

                'action' =>
                $action,

                'profile_status' =>
                $action === 'approve'
                    ? RealEstateProfileStatus::APPROVED
                    : RealEstateProfileStatus::REJECTED,

                'status' =>
                $action === 'approve'
                    ? 1
                    : 0,

                'validation_note' =>
                $action === 'reject'
                    ? $validationNote
                    : null,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function getMembershipSummary(
        int $realEstateId
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT
            m.id,
            m.plan_id,
            m.scheduled_plan_id,
            m.status,
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

            sp.code AS scheduled_plan_code,
            sp.name AS scheduled_plan_name,
            sp.price_ars AS scheduled_plan_price_ars

        FROM memberships m

        LEFT JOIN plans p
            ON p.id = m.plan_id

        LEFT JOIN plans sp
            ON sp.id = m.scheduled_plan_id

        WHERE m.real_estate_id = :real_estate_id
          AND m.deleted_at IS NULL

        ORDER BY m.id DESC
        LIMIT 1
    ");

        $st->execute([
            'real_estate_id' => $realEstateId,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'membership' => null,
                'membership_status' => 'none',
                'plan' => null,
                'scheduled_plan' => null,
            ];
        }

        $rawStatus = (int)$row['status'];

        if ($rawStatus === 0) {
            $membershipStatus = 'pending';
        } elseif ($rawStatus === 2) {
            $membershipStatus = 'expired';
        } elseif ($rawStatus === 3) {
            $membershipStatus = 'cancelled';
        } elseif (
            $rawStatus === 1 &&
            !empty($row['end_date']) &&
            $row['end_date'] < date('Y-m-d')
        ) {
            $membershipStatus = 'expired';
        } elseif (
            $rawStatus === 1 &&
            (int)($row['cancel_at_period_end'] ?? 0) === 1
        ) {
            $membershipStatus = 'cancel_at_period_end';
        } elseif (
            $rawStatus === 1 &&
            !empty($row['scheduled_plan_id'])
        ) {
            $membershipStatus = 'scheduled_change';
        } elseif ($rawStatus === 1) {
            $membershipStatus = 'active';
        } else {
            $membershipStatus = 'none';
        }

        return [
            'membership' => [
                'id' => (int)$row['id'],
                'plan_id' => $row['plan_id'] !== null
                    ? (int)$row['plan_id']
                    : null,
                'scheduled_plan_id' =>
                $row['scheduled_plan_id'] !== null
                    ? (int)$row['scheduled_plan_id']
                    : null,
                'status' => $rawStatus,
                'billing_cycle' =>
                $row['billing_cycle'] !== null
                    ? (int)$row['billing_cycle']
                    : null,
                'cancel_at_period_end' =>
                (int)($row['cancel_at_period_end'] ?? 0),
                'cancelled_at' =>
                $row['cancelled_at'] ?? null,
                'start_date' =>
                $row['start_date'] ?? null,
                'end_date' =>
                $row['end_date'] ?? null,
                'scheduled_change_at' =>
                $row['scheduled_change_at'] ?? null,
                'mp_last_payment_id' =>
                $row['mp_last_payment_id'] !== null
                    ? (int)$row['mp_last_payment_id']
                    : null,
            ],

            'membership_status' =>
            $membershipStatus,

            'plan' => $row['plan_id'] !== null
                ? [
                    'id' => (int)$row['plan_id'],
                    'code' => $row['plan_code'] ?? null,
                    'name' => $row['plan_name'] ?? null,
                    'price_ars' =>
                    isset($row['plan_price_ars'])
                        ? (int)$row['plan_price_ars']
                        : null,
                ]
                : null,

            'scheduled_plan' =>
            $row['scheduled_plan_id'] !== null
                ? [
                    'id' =>
                    (int)$row['scheduled_plan_id'],
                    'code' =>
                    $row['scheduled_plan_code'] ?? null,
                    'name' =>
                    $row['scheduled_plan_name'] ?? null,
                    'price_ars' =>
                    isset($row['scheduled_plan_price_ars'])
                        ? (int)$row['scheduled_plan_price_ars']
                        : null,
                ]
                : null,
        ];
    }

    public static function setOperationalStatus(
        int $realEstateId,
        bool $isActive
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT
            id,
            status,
            profile_status
        FROM real_estates
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'id' => $realEstateId,
        ]);

        $realEstate =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$realEstate) {
            throw new \Exception(
                "Inmobiliaria no encontrada"
            );
        }

        $profileStatus =
            (int)(
                $realEstate['profile_status']
                ?? 0
            );

        $allowedProfileStatuses = [
            RealEstateProfileStatus::APPROVED,
            RealEstateProfileStatus::CHANGES_PENDING,
        ];

        if (
            !in_array(
                $profileStatus,
                $allowedProfileStatuses,
                true
            )
        ) {
            throw new \Exception(
                "Sólo se puede suspender o reactivar una inmobiliaria previamente aprobada"
            );
        }

        $newStatus =
            $isActive
            ? 1
            : 0;

        if (
            (int)$realEstate['status']
            === $newStatus
        ) {
            return [
                'real_estate_id' =>
                $realEstateId,

                'status' =>
                $newStatus,

                'changed' =>
                false,
            ];
        }

        $st = $pdo->prepare("
        UPDATE real_estates
        SET status = :status
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $st->execute([
            'status' =>
            $newStatus,

            'id' =>
            $realEstateId,
        ]);

        return [
            'real_estate_id' =>
            $realEstateId,

            'status' =>
            $newStatus,

            'changed' =>
            true,
        ];
    }

    public static function getDetail(
        int $realEstateId
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT
            r.id,
            r.name,
            r.legal_name,
            r.cuit,
            r.email,
            r.phone,
            r.address,
            r.website,
            r.instagram,
            r.facebook,
            r.status,
            r.profile_status,
            r.validation_status,
            r.validation_note,
            r.review_requested_at,
            r.changes_requested_at,
            r.approved_at,
            r.approved_by,
            u.email AS approved_by_email,
            r.created_at,
            r.validated_at,
            r.address_place_id,
            r.address_lat,
            r.address_lng

        FROM real_estates r

        LEFT JOIN users u
            ON u.id = r.approved_by

        WHERE r.deleted_at IS NULL
          AND r.id = :id

        LIMIT 1
    ");

        $st->execute([
            'id' => $realEstateId,
        ]);

        $re = $st->fetch(PDO::FETCH_ASSOC);

        if (!$re) {
            throw new \Exception(
                "Inmobiliaria no encontrada"
            );
        }

        $re['admin_profile_stage'] =
            RealEstateService::resolveAdminProfileStage(
                $re
            );

        $billing =
            self::getMembershipSummary(
                $realEstateId
            );

        $re['membership'] =
            $billing['membership'];

        $re['membership_status'] =
            $billing['membership_status'];

        $re['plan'] =
            $billing['plan'];

        $re['scheduled_plan'] =
            $billing['scheduled_plan'];

        return [
            'real_estate' => $re,
            'licenses' =>
            self::listLicenses(
                $realEstateId
            ),
        ];
    }

    public static function listLicenses(int $realEstateId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                l.id,
                l.real_estate_id,
                l.license_number,
                l.province_id,
                p.name AS province_name,
                p.code AS province_code,
                l.is_primary,
                l.created_at
            FROM real_estate_licenses l
            LEFT JOIN provinces p ON p.id = l.province_id
            WHERE l.deleted_at IS NULL
              AND l.real_estate_id = :id
            ORDER BY (l.is_primary = 1) DESC, l.id DESC
        ");
        $st->execute(['id' => $realEstateId]);

        return $st->fetchAll() ?: [];
    }
}
