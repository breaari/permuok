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
            'draft' => 'draft',
            'initial_review' => 'initial_review',
            'changes_pending' => 'changes_pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'initial_review',
        };
    }

    public static function counts(?string $q = null): array
    {
        $pdo = self::db();

        $whereQ = '';
        $params = [];

        $q = trim((string)$q);
        if ($q !== '') {
            $whereQ = " AND (
                r.name LIKE :q
                OR r.legal_name LIKE :q
                OR r.email LIKE :q
                OR r.cuit LIKE :q
            )";
            $params['q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
              COALESCE(SUM(CASE WHEN r.profile_status = " . RealEstateProfileStatus::DRAFT . " THEN 1 ELSE 0 END), 0) AS draft,
              COALESCE(SUM(CASE WHEN r.profile_status = " . RealEstateProfileStatus::INITIAL_REVIEW . " THEN 1 ELSE 0 END), 0) AS initial_review,
              COALESCE(SUM(CASE WHEN r.profile_status = " . RealEstateProfileStatus::CHANGES_PENDING . " THEN 1 ELSE 0 END), 0) AS changes_pending,
              COALESCE(SUM(CASE WHEN r.profile_status = " . RealEstateProfileStatus::APPROVED . " THEN 1 ELSE 0 END), 0) AS approved,
              COALESCE(SUM(CASE WHEN r.profile_status = " . RealEstateProfileStatus::REJECTED . " THEN 1 ELSE 0 END), 0) AS rejected
            FROM real_estates r
            WHERE r.deleted_at IS NULL
            {$whereQ}
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch() ?: [
            'draft' => 0,
            'initial_review' => 0,
            'changes_pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        return [
            'draft' => (int)($row['draft'] ?? 0),
            'initial_review' => (int)($row['initial_review'] ?? 0),
            'changes_pending' => (int)($row['changes_pending'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'rejected' => (int)($row['rejected'] ?? 0),
        ];
    }

    public static function list(string $status, int $page, int $perPage, ?string $q = null): array
    {
        $pdo = self::db();

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50);
        $offset = ($page - 1) * $perPage;

        $normalizedStatus = self::normStatus($status);

        $where = " r.deleted_at IS NULL ";
        $params = [];

        switch ($normalizedStatus) {
            case 'draft':
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::DRAFT;
                break;

            case 'initial_review':
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::INITIAL_REVIEW;
                break;

            case 'changes_pending':
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::CHANGES_PENDING;
                break;

            case 'approved':
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::APPROVED;
                break;

            case 'rejected':
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::REJECTED;
                break;

            default:
                $where .= " AND r.profile_status = :profile_status ";
                $params['profile_status'] = RealEstateProfileStatus::INITIAL_REVIEW;
                break;
        }

        $q = trim((string)$q);
        if ($q !== '') {
            $where .= " AND (
                r.name LIKE :q
                OR r.legal_name LIKE :q
                OR r.email LIKE :q
                OR r.cuit LIKE :q
            ) ";
            $params['q'] = '%' . $q . '%';
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM real_estates r
            WHERE {$where}
        ";

        $stCount = $pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $stCount->bindValue(':' . $k, $v);
        }
        $stCount->execute();
        $total = (int)($stCount->fetch()['total'] ?? 0);

        $orderBy = match ($normalizedStatus) {
            'draft' => " r.created_at DESC, r.id DESC ",
            'initial_review' => " COALESCE(r.review_requested_at, r.created_at) DESC, r.id DESC ",
            'changes_pending' => " COALESCE(r.changes_requested_at, r.created_at) DESC, r.id DESC ",
            'approved' => " COALESCE(r.approved_at, r.validated_at, r.created_at) DESC, r.id DESC ",
            'rejected' => " COALESCE(r.validated_at, r.review_requested_at, r.created_at) DESC, r.id DESC ",
            default => " r.id DESC ",
        };

        $sql = "
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
              r.validated_at
            FROM real_estates r
            LEFT JOIN users u ON u.id = r.approved_by
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $st = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }

        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);

        $st->execute();
        $items = $st->fetchAll() ?: [];

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
                r.validated_at
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