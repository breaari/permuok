<?php

namespace App\Services;

use PDO;
use Exception;

class AdminUserService
{
    private const ROLE_SUPER_ADMIN = 1;
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;
    private const ROLE_INVESTOR = 4;

    private const PROFILE_DRAFT = 0;
    private const PROFILE_INITIAL_REVIEW = 1;
    private const PROFILE_APPROVED = 2;
    private const PROFILE_REJECTED = 3;
    private const PROFILE_CHANGES_PENDING = 4;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string)$role));

        return match ($role) {
            'agent' => 'agent',
            'investor' => 'investor',
            default => 'real_estate',
        };
    }

    private static function roleToInt(string $role): int
    {
        return match ($role) {
            'real_estate' => self::ROLE_REAL_ESTATE,
            'agent' => self::ROLE_AGENT,
            'investor' => self::ROLE_INVESTOR,
            default => self::ROLE_REAL_ESTATE,
        };
    }

    private static function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => 'all',
        };
    }

    private static function normalizeMembership(?string $membership): string
    {
        $membership = strtolower(trim((string)$membership));

        return match ($membership) {
            'active' => 'active',
            'none' => 'none',
            'cancel_at_period_end' => 'cancel_at_period_end',
            'scheduled_change' => 'scheduled_change',
            default => 'all',
        };
    }

    private static function buildStatusWhere(string $status, array &$params): string
    {
        $status = self::normalizeStatus($status);

        if ($status === 'active') {
            $params['is_active'] = 1;
            return " AND u.is_active = :is_active ";
        }

        if ($status === 'inactive') {
            $params['is_active'] = 0;
            return " AND u.is_active = :is_active ";
        }

        return "";
    }

    private static function buildMembershipWhere(string $role, string $membership): string
    {
        $role = self::normalizeRole($role);
        $membership = self::normalizeMembership($membership);

        if ($role !== 'real_estate' || $membership === 'all') {
            return "";
        }

        return match ($membership) {
            'none' => " AND m.id IS NULL ",
            'active' => " AND m.id IS NOT NULL AND COALESCE(m.cancel_at_period_end, 0) = 0 AND m.scheduled_plan_id IS NULL ",
            'cancel_at_period_end' => " AND m.id IS NOT NULL AND COALESCE(m.cancel_at_period_end, 0) = 1 ",
            'scheduled_change' => " AND m.id IS NOT NULL AND m.scheduled_plan_id IS NOT NULL ",
            default => "",
        };
    }

    private static function resolveMembershipStatus(?array $membershipRow): string
    {
        if (!$membershipRow) {
            return 'none';
        }

        $cancelAtPeriodEnd = (int)($membershipRow['cancel_at_period_end'] ?? 0) === 1;
        $scheduledPlanId = isset($membershipRow['scheduled_plan_id']) && $membershipRow['scheduled_plan_id'] !== null
            ? (int)$membershipRow['scheduled_plan_id']
            : null;

        if ($scheduledPlanId) {
            return 'scheduled_change';
        }

        if ($cancelAtPeriodEnd) {
            return 'cancel_at_period_end';
        }

        return 'active';
    }

    public static function counts(?string $q = null, ?string $status = 'all', ?string $membership = 'all'): array
    {
        $pdo = self::db();

        $q = trim((string)$q);
        $status = self::normalizeStatus($status);
        $membership = self::normalizeMembership($membership);

        $baseParams = [];
        $whereStatus = self::buildStatusWhere($status, $baseParams);

        $whereQ = '';
        if ($q !== '') {
            $whereQ = " AND (
                u.first_name LIKE :q
                OR u.last_name LIKE :q
                OR u.email LIKE :q
                OR u.phone LIKE :q
                OR re.name LIKE :q
                OR re.legal_name LIKE :q
            )";
            $baseParams['q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM users u
                    LEFT JOIN real_estates re ON re.id = u.real_estate_id AND re.deleted_at IS NULL
                    LEFT JOIN memberships m
                        ON m.id = (
                            SELECT m2.id
                            FROM memberships m2
                            WHERE m2.real_estate_id = re.id
                              AND m2.status = 1
                              AND m2.end_date >= CURDATE()
                              AND m2.deleted_at IS NULL
                            ORDER BY m2.id DESC
                            LIMIT 1
                        )
                    WHERE u.deleted_at IS NULL
                      AND u.role = " . self::ROLE_REAL_ESTATE . "
                      {$whereStatus}
                      {$whereQ}
                      " . self::buildMembershipWhere('real_estate', $membership) . "
                ) AS real_estate,

                (
                    SELECT COUNT(*)
                    FROM users u
                    LEFT JOIN real_estates re ON re.id = u.real_estate_id AND re.deleted_at IS NULL
                    WHERE u.deleted_at IS NULL
                      AND u.role = " . self::ROLE_AGENT . "
                      {$whereStatus}
                      {$whereQ}
                ) AS agent,

                (
                    SELECT COUNT(*)
                    FROM users u
                    LEFT JOIN real_estates re ON re.id = u.real_estate_id AND re.deleted_at IS NULL
                    WHERE u.deleted_at IS NULL
                      AND u.role = " . self::ROLE_INVESTOR . "
                      {$whereStatus}
                      {$whereQ}
                ) AS investor
        ";

        $st = $pdo->prepare($sql);
        foreach ($baseParams as $k => $v) {
            if ($k === 'is_active') {
                $st->bindValue(':' . $k, (int)$v, PDO::PARAM_INT);
            } else {
                $st->bindValue(':' . $k, $v);
            }
        }
        $st->execute();

        $row = $st->fetch() ?: [
            'real_estate' => 0,
            'agent' => 0,
            'investor' => 0,
        ];

        return [
            'real_estate' => (int)($row['real_estate'] ?? 0),
            'agent' => (int)($row['agent'] ?? 0),
            'investor' => (int)($row['investor'] ?? 0),
        ];
    }

    public static function list(
        string $role,
        int $page,
        int $perPage,
        ?string $q = null,
        ?string $status = 'all',
        ?string $membership = 'all'
    ): array {
        $pdo = self::db();

        $role = self::normalizeRole($role);
        $roleInt = self::roleToInt($role);
        $status = self::normalizeStatus($status);
        $membership = self::normalizeMembership($membership);

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50);
        $offset = ($page - 1) * $perPage;

        $params = ['role' => $roleInt];
        $where = " u.deleted_at IS NULL AND u.role = :role ";

        $where .= self::buildStatusWhere($status, $params);

        $q = trim((string)$q);
        if ($q !== '') {
            $where .= " AND (
                u.first_name LIKE :q
                OR u.last_name LIKE :q
                OR u.email LIKE :q
                OR u.phone LIKE :q
                OR re.name LIKE :q
                OR re.legal_name LIKE :q
            ) ";
            $params['q'] = '%' . $q . '%';
        }

        $where .= self::buildMembershipWhere($role, $membership);

        $countSql = "
            SELECT COUNT(*) AS total
            FROM users u
            LEFT JOIN real_estates re ON re.id = u.real_estate_id AND re.deleted_at IS NULL
            LEFT JOIN memberships m
                ON m.id = (
                    SELECT m2.id
                    FROM memberships m2
                    WHERE m2.real_estate_id = re.id
                      AND m2.status = 1
                      AND m2.end_date >= CURDATE()
                      AND m2.deleted_at IS NULL
                    ORDER BY m2.id DESC
                    LIMIT 1
                )
            WHERE {$where}
        ";

        $stCount = $pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            if ($k === 'role' || $k === 'is_active') {
                $stCount->bindValue(':' . $k, (int)$v, PDO::PARAM_INT);
            } else {
                $stCount->bindValue(':' . $k, $v);
            }
        }
        $stCount->execute();
        $total = (int)($stCount->fetch()['total'] ?? 0);

        $sql = "
            SELECT
                u.id,
                u.real_estate_id,
                u.role,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.is_active,
                u.deactivation_reason,
                u.deactivated_at,
                u.deactivated_by,
                admin.email AS deactivated_by_email,
                u.created_at,
                u.last_login,

                re.id AS real_estate_ref_id,
                re.name AS real_estate_name,
                re.legal_name AS real_estate_legal_name,
                re.cuit AS real_estate_cuit,
                re.email AS real_estate_email,
                re.phone AS real_estate_phone,
                re.profile_status AS real_estate_profile_status,

                m.id AS membership_id,
                m.plan_id AS membership_plan_id,
                p.name AS membership_plan_name,
                m.end_date AS membership_end_date,
                m.cancel_at_period_end AS membership_cancel_at_period_end,
                m.scheduled_plan_id AS membership_scheduled_plan_id
            FROM users u
            LEFT JOIN real_estates re
                ON re.id = u.real_estate_id
               AND re.deleted_at IS NULL
            LEFT JOIN users admin
                ON admin.id = u.deactivated_by
            LEFT JOIN memberships m
                ON m.id = (
                    SELECT m2.id
                    FROM memberships m2
                    WHERE m2.real_estate_id = re.id
                      AND m2.status = 1
                      AND m2.end_date >= CURDATE()
                      AND m2.deleted_at IS NULL
                    ORDER BY m2.id DESC
                    LIMIT 1
                )
            LEFT JOIN plans p
                ON p.id = m.plan_id
            WHERE {$where}
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $st = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            if ($k === 'role' || $k === 'is_active') {
                $st->bindValue(':' . $k, (int)$v, PDO::PARAM_INT);
            } else {
                $st->bindValue(':' . $k, $v);
            }
        }

        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll() ?: [];

        $items = array_map(function ($row) {
            $membership = null;

            if (!empty($row['membership_id'])) {
                $membership = [
                    'id' => (int)$row['membership_id'],
                    'plan_id' => isset($row['membership_plan_id']) ? (int)$row['membership_plan_id'] : null,
                    'plan_name' => $row['membership_plan_name'] ?? null,
                    'end_date' => $row['membership_end_date'] ?? null,
                    'cancel_at_period_end' => (int)($row['membership_cancel_at_period_end'] ?? 0),
                    'scheduled_plan_id' => isset($row['membership_scheduled_plan_id']) && $row['membership_scheduled_plan_id'] !== null
                        ? (int)$row['membership_scheduled_plan_id']
                        : null,
                ];
            }

            return [
                'id' => (int)$row['id'],
                'real_estate_id' => $row['real_estate_id'] !== null ? (int)$row['real_estate_id'] : null,
                'role' => (int)$row['role'],
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'is_active' => (int)($row['is_active'] ?? 0),
                'deactivation_reason' => $row['deactivation_reason'] ?? null,
                'deactivated_at' => $row['deactivated_at'] ?? null,
                'deactivated_by' => $row['deactivated_by'] !== null ? (int)$row['deactivated_by'] : null,
                'deactivated_by_email' => $row['deactivated_by_email'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'last_login' => $row['last_login'] ?? null,

                'real_estate_name' => $row['real_estate_name'] ?? null,
                'real_estate_legal_name' => $row['real_estate_legal_name'] ?? null,
                'real_estate_cuit' => $row['real_estate_cuit'] ?? null,
                'real_estate_email' => $row['real_estate_email'] ?? null,
                'real_estate_phone' => $row['real_estate_phone'] ?? null,
                'real_estate_profile_status' => isset($row['real_estate_profile_status'])
                    ? (int)$row['real_estate_profile_status']
                    : null,

                'membership' => $membership,
                'membership_status' => self::resolveMembershipStatus($membership),
                'membership_plan_name' => $row['membership_plan_name'] ?? null,
            ];
        }, $rows);

        $from = $total === 0 ? 0 : ($offset + 1);
        $to = min($offset + $perPage, $total);

        return [
            'items' => $items,
            'meta' => [
                'role' => $role,
                'status' => $status,
                'membership' => $membership,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'pages' => (int)ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public static function getDetail(int $userId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                u.id,
                u.real_estate_id,
                u.role,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.is_active,
                u.deactivation_reason,
                u.deactivated_at,
                u.deactivated_by,
                admin.email AS deactivated_by_email,
                u.created_at,
                u.last_login,

                re.id AS real_estate_ref_id,
                re.name AS real_estate_name,
                re.legal_name AS real_estate_legal_name,
                re.cuit AS real_estate_cuit,
                re.email AS real_estate_email,
                re.phone AS real_estate_phone,
                re.address AS real_estate_address,
                re.website AS real_estate_website,
re.instagram AS real_estate_instagram,
re.facebook AS real_estate_facebook,
                re.profile_status AS real_estate_profile_status
            FROM users u
            LEFT JOIN real_estates re ON re.id = u.real_estate_id AND re.deleted_at IS NULL
            LEFT JOIN users admin ON admin.id = u.deactivated_by
            WHERE u.id = :id
              AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $user = $st->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        $membership = null;
        if (!empty($user['real_estate_ref_id'])) {
            $stMembership = $pdo->prepare("
                SELECT
                    m.id,
                    m.plan_id,
                    p.name AS plan_name,
                    m.end_date,
                    m.cancel_at_period_end,
                    m.scheduled_plan_id
                FROM memberships m
                LEFT JOIN plans p ON p.id = m.plan_id
                WHERE m.real_estate_id = :real_estate_id
                  AND m.status = 1
                  AND m.end_date >= CURDATE()
                  AND m.deleted_at IS NULL
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stMembership->execute([
                'real_estate_id' => (int)$user['real_estate_ref_id'],
            ]);
            $membershipRow = $stMembership->fetch();

            if ($membershipRow) {
                $membership = [
                    'id' => (int)$membershipRow['id'],
                    'plan_id' => isset($membershipRow['plan_id']) ? (int)$membershipRow['plan_id'] : null,
                    'plan_name' => $membershipRow['plan_name'] ?? null,
                    'end_date' => $membershipRow['end_date'] ?? null,
                    'cancel_at_period_end' => (int)($membershipRow['cancel_at_period_end'] ?? 0),
                    'scheduled_plan_id' => isset($membershipRow['scheduled_plan_id']) && $membershipRow['scheduled_plan_id'] !== null
                        ? (int)$membershipRow['scheduled_plan_id']
                        : null,
                ];
            }
        }

        $children = [];
        $childrenSummary = [
            'agents' => 0,
            'investors' => 0,
            'total' => 0,
        ];

        if ((int)$user['role'] === self::ROLE_REAL_ESTATE && !empty($user['real_estate_id'])) {
            $stChildren = $pdo->prepare("
                SELECT
                    id,
                    role,
                    first_name,
                    last_name,
                    email,
                    phone,
                    is_active,
                    last_login,
                    created_at
                FROM users
                WHERE real_estate_id = :real_estate_id
                  AND role IN (" . self::ROLE_AGENT . ", " . self::ROLE_INVESTOR . ")
                  AND deleted_at IS NULL
                ORDER BY role ASC, created_at DESC, id DESC
            ");
            $stChildren->execute([
                'real_estate_id' => (int)$user['real_estate_id'],
            ]);
            $childrenRows = $stChildren->fetchAll() ?: [];

            $children = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'role' => (int)$row['role'],
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_active' => (int)($row['is_active'] ?? 0),
                    'last_login' => $row['last_login'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $childrenRows);

            foreach ($children as $child) {
                if ((int)$child['role'] === self::ROLE_AGENT) {
                    $childrenSummary['agents']++;
                } elseif ((int)$child['role'] === self::ROLE_INVESTOR) {
                    $childrenSummary['investors']++;
                }
            }

            $childrenSummary['total'] =
                $childrenSummary['agents'] + $childrenSummary['investors'];
        }

        return [
            'user' => [
                'id' => (int)$user['id'],
                'real_estate_id' => $user['real_estate_id'] !== null ? (int)$user['real_estate_id'] : null,
                'role' => (int)$user['role'],
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'email' => $user['email'] ?? null,
                'phone' => $user['phone'] ?? null,
                'is_active' => (int)($user['is_active'] ?? 0),
                'deactivation_reason' => $user['deactivation_reason'] ?? null,
                'deactivated_at' => $user['deactivated_at'] ?? null,
                'deactivated_by' => $user['deactivated_by'] !== null ? (int)$user['deactivated_by'] : null,
                'deactivated_by_email' => $user['deactivated_by_email'] ?? null,
                'created_at' => $user['created_at'] ?? null,
                'last_login' => $user['last_login'] ?? null,

                'real_estate_name' => $user['real_estate_name'] ?? null,
                'real_estate_legal_name' => $user['real_estate_legal_name'] ?? null,
                'real_estate_cuit' => $user['real_estate_cuit'] ?? null,
                'real_estate_email' => $user['real_estate_email'] ?? null,
                'real_estate_phone' => $user['real_estate_phone'] ?? null,
                'real_estate_address' => $user['real_estate_address'] ?? null,
                'real_estate_website' => $user['real_estate_website'] ?? null,
                'real_estate_instagram' => $user['real_estate_instagram'] ?? null,
                'real_estate_facebook' => $user['real_estate_facebook'] ?? null,
                'real_estate_profile_status' => isset($user['real_estate_profile_status'])
                    ? (int)$user['real_estate_profile_status']
                    : null,

                'membership' => $membership,
                'membership_status' => self::resolveMembershipStatus($membership),
            ],
            'children' => $children,
            'children_summary' => $childrenSummary,
        ];
    }

    public static function updateStatus(int $adminUserId, int $userId, int $isActive, ?string $reason = null): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, role, real_estate_id, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $target = $st->fetch();

        if (!$target) {
            throw new Exception("Usuario no encontrado");
        }

        if ((int)$target['role'] === self::ROLE_SUPER_ADMIN) {
            throw new Exception("No podés modificar un super admin");
        }

        if ((int)$target['id'] === (int)$adminUserId) {
            throw new Exception("No podés modificar tu propio estado");
        }

        $reason = trim((string)$reason);

        if ($isActive === 0 && $reason === '') {
            throw new Exception("El motivo de desactivación es requerido");
        }

        $pdo->beginTransaction();

        try {
            if ((int)$target['role'] === self::ROLE_REAL_ESTATE) {
                self::updateRealEstateTreeStatus(
                    $pdo,
                    (int)$adminUserId,
                    (int)$target['id'],
                    (int)$target['real_estate_id'],
                    $isActive,
                    $reason
                );
            } else {
                if ($isActive === 1) {
                    $st = $pdo->prepare("
                        UPDATE users
                        SET
                            is_active = 1,
                            deactivation_reason = NULL,
                            deactivated_at = NULL,
                            deactivated_by = NULL
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $st->execute(['id' => $userId]);
                } else {
                    $st = $pdo->prepare("
                        UPDATE users
                        SET
                            is_active = 0,
                            deactivation_reason = :reason,
                            deactivated_at = NOW(),
                            deactivated_by = :admin_id
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $st->execute([
                        'reason' => $reason,
                        'admin_id' => $adminUserId,
                        'id' => $userId,
                    ]);
                }
            }

            $pdo->commit();

            return [
                'updated' => true,
                'user_id' => $userId,
                'is_active' => $isActive,
                'reason' => $isActive === 0 ? $reason : null,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function updateRealEstateTreeStatus(
        PDO $pdo,
        int $adminUserId,
        int $realEstateUserId,
        ?int $realEstateId,
        int $isActive,
        string $reason
    ): void {
        if (!$realEstateId) {
            throw new Exception("La inmobiliaria no está vinculada");
        }

        if ($isActive === 1) {
            $st = $pdo->prepare("
                UPDATE users
                SET
                    is_active = 1,
                    deactivation_reason = NULL,
                    deactivated_at = NULL,
                    deactivated_by = NULL
                WHERE (
                    id = :owner_id
                    OR (
                        real_estate_id = :real_estate_id
                        AND role IN (" . self::ROLE_AGENT . ", " . self::ROLE_INVESTOR . ")
                    )
                )
                  AND deleted_at IS NULL
            ");
            $st->execute([
                'owner_id' => $realEstateUserId,
                'real_estate_id' => $realEstateId,
            ]);

            return;
        }

        $st = $pdo->prepare("
            UPDATE users
            SET
                is_active = 0,
                deactivation_reason = :reason,
                deactivated_at = NOW(),
                deactivated_by = :admin_id
            WHERE (
                id = :owner_id
                OR (
                    real_estate_id = :real_estate_id
                    AND role IN (" . self::ROLE_AGENT . ", " . self::ROLE_INVESTOR . ")
                )
            )
              AND deleted_at IS NULL
        ");
        $st->execute([
            'reason' => $reason,
            'admin_id' => $adminUserId,
            'owner_id' => $realEstateUserId,
            'real_estate_id' => $realEstateId,
        ]);
    }
}
