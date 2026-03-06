<?php

namespace App\Services;

use PDO;

final class RealEstateValidationStatus
{
    public const PENDING  = 0; // pendiente de revisión
    public const APPROVED = 1;
    public const REJECTED = 2;
}

class AdminRealEstateService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function normStatus(?string $status): int
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'approved' => RealEstateValidationStatus::APPROVED,
            'rejected' => RealEstateValidationStatus::REJECTED,
            // default: pending
            default    => RealEstateValidationStatus::PENDING,
        };
    }

    /**
     * Counts correctos para tabs (sin tener que "entrar" a cada tab).
     * Importante: Pending = review_requested_at IS NOT NULL y validation_status = 0
     */
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
              SUM(CASE WHEN r.review_requested_at IS NOT NULL AND r.validation_status = 0 THEN 1 ELSE 0 END) AS pending,
              SUM(CASE WHEN r.validation_status = 1 THEN 1 ELSE 0 END) AS approved,
              SUM(CASE WHEN r.validation_status = 2 THEN 1 ELSE 0 END) AS rejected
            FROM real_estates r
            WHERE r.deleted_at IS NULL
            {$whereQ}
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch() ?: ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        return [
            'pending'  => (int)($row['pending'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'rejected' => (int)($row['rejected'] ?? 0),
        ];
    }

    /**
     * Listado paginado.
     * Query: status=pending|approved|rejected, page, per_page, q
     */
    public static function list(string $status, int $page, int $perPage, ?string $q = null): array
    {
        $pdo = self::db();

        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 50); // cap por performance
        $offset = ($page - 1) * $perPage;

        $vs = self::normStatus($status);

        // filtros base
        $where = " r.deleted_at IS NULL ";
        $params = [];

        // status filter
        if ($vs === RealEstateValidationStatus::PENDING) {
            $where .= " AND r.review_requested_at IS NOT NULL AND r.validation_status = 0 ";
        } else {
            $where .= " AND r.validation_status = :vs ";
            $params['vs'] = $vs;
        }

        // búsqueda
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

        // total
        $stCount = $pdo->prepare("SELECT COUNT(*) AS total FROM real_estates r WHERE {$where}");
        $stCount->execute($params);
        $total = (int)(($stCount->fetch()['total'] ?? 0));

        // orden
        // pending: por review_requested_at desc
        // approved: por approved_at desc
        // rejected: por validated_at o review_requested_at desc (ajustable)
        $orderBy = match ($vs) {
            RealEstateValidationStatus::APPROVED => " r.approved_at DESC, r.id DESC ",
            RealEstateValidationStatus::REJECTED => " COALESCE(r.validated_at, r.review_requested_at) DESC, r.id DESC ",
            default => " r.review_requested_at DESC, r.id DESC ",
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
              r.validation_status,
              r.validation_note,
              r.review_requested_at,
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
        $to   = min($offset + $perPage, $total);

        return [
            'items' => $items,
            'meta' => [
                'status' => $status,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'pages' => (int)ceil($total / max(1, $perPage)),
            ],
        ];
    }

    /**
     * validate = approve/reject (endpoint nuevo).
     */
    public static function validate(int $adminUserId, int $realEstateId, string $action, ?string $note = null): array
    {
        $pdo = self::db();

        $action = strtolower(trim($action));
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new \Exception("Acción inválida");
        }

        if ($action === 'reject' && trim((string)$note) === '') {
            throw new \Exception("El motivo del rechazo es requerido");
        }

        $st = $pdo->prepare("
            SELECT id, validation_status, review_requested_at
            FROM real_estates
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $realEstateId]);
        $re = $st->fetch();

        if (!$re) {
            throw new \Exception("Inmobiliaria no encontrada");
        }

        $pdo->beginTransaction();
        try {
            if ($action === 'approve') {
                $st = $pdo->prepare("
                    UPDATE real_estates
                    SET
                      validation_status = 1,
                      validation_note = NULL,
                      approved_at = NOW(),
                      approved_by = :admin_id,
                      validated_at = NOW()
                    WHERE id = :id
                    LIMIT 1
                ");
                $st->execute(['admin_id' => $adminUserId, 'id' => $realEstateId]);
            } else {
                $st = $pdo->prepare("
                    UPDATE real_estates
                    SET
                      validation_status = 2,
                      validation_note = :note,
                      approved_at = NULL,
                      approved_by = NULL,
                      validated_at = NOW()
                    WHERE id = :id
                    LIMIT 1
                ");
                $st->execute(['note' => trim((string)$note), 'id' => $realEstateId]);
            }

            $pdo->commit();
            return [
                'real_estate_id' => $realEstateId,
                'action' => $action,
                'validation_status' => $action === 'approve' ? 1 : 2,
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
            r.validation_status,
            r.validation_note,
            r.review_requested_at,
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
