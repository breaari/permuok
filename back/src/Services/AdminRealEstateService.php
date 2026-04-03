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
        $st->execute(['id' => $realEstateId]);
        $re = $st->fetch();

        if (!$re) {
            throw new \Exception("Inmobiliaria no encontrada");
        }

        $currentProfileStatus = (int)($re['profile_status'] ?? 0);

        if (!in_array($currentProfileStatus, [
            RealEstateProfileStatus::INITIAL_REVIEW,
            RealEstateProfileStatus::CHANGES_PENDING,
        ], true)) {
            throw new \Exception("La solicitud no está pendiente de revisión");
        }

        $pdo->beginTransaction();

        try {
            if ($action === 'approve') {
                $st = $pdo->prepare("
                    UPDATE real_estates
                    SET
                      profile_status = :approved_profile_status,
                      validation_status = 1,
                      validation_note = NULL,
                      approved_at = NOW(),
                      approved_by = :admin_id,
                      validated_at = NOW(),
                      review_requested_at = CASE
                          WHEN review_requested_at IS NULL THEN NOW()
                          ELSE review_requested_at
                      END,
                      changes_requested_at = NULL
                    WHERE id = :id
                    LIMIT 1
                ");
                $st->execute([
                    'approved_profile_status' => RealEstateProfileStatus::APPROVED,
                    'admin_id' => $adminUserId,
                    'id' => $realEstateId,
                ]);
            } else {
                $st = $pdo->prepare("
                    UPDATE real_estates
                    SET
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
                    'rejected_profile_status' => RealEstateProfileStatus::REJECTED,
                    'validation_note' => $validationNote,
                    'id' => $realEstateId,
                ]);
            }

            $pdo->commit();

            return [
                'real_estate_id' => $realEstateId,
                'action' => $action,
                'profile_status' => $action === 'approve'
                    ? RealEstateProfileStatus::APPROVED
                    : RealEstateProfileStatus::REJECTED,
                'validation_note' => $action === 'reject' ? $validationNote : null,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetail(int $realEstateId): array
    {
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
            LEFT JOIN users u ON u.id = r.approved_by
            WHERE r.deleted_at IS NULL
              AND r.id = :id
            LIMIT 1
        ");
        $st->execute(['id' => $realEstateId]);
        $re = $st->fetch();

        if (!$re) {
            throw new \Exception("Inmobiliaria no encontrada");
        }

        $re['admin_profile_stage'] = RealEstateService::resolveAdminProfileStage($re);

        return [
            'real_estate' => $re,
            'licenses' => self::listLicenses($realEstateId),
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