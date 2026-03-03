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

    public static function approve(int $realEstateId, int $adminId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            UPDATE real_estates
            SET
              validation_status = 1,
              approved_at = NOW(),
              approved_by = :admin,
              validated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $realEstateId,
            'admin' => $adminId,
        ]);

        return ['approved' => true];
    }

    public static function listPending(): array
    {
        $pdo = self::db();

        // Pendientes = pidió revisión (review_requested_at NOT NULL) y aún no aprobada
        // Ajustá validation_status según tu convención:
        // 0=draft, 1=approved, 2=rejected (ejemplo)
        $st = $pdo->query("
  SELECT
    r.id,
    r.name,
    r.legal_name,
    r.cuit,
    r.email,
    r.phone,
    r.address,
    r.validation_status,
    r.review_requested_at,
    r.created_at
  FROM real_estates r
  WHERE r.deleted_at IS NULL
    AND r.review_requested_at IS NOT NULL
    AND r.validation_status = 0
  ORDER BY r.review_requested_at DESC, r.id DESC
  LIMIT 200
");

        return $st->fetchAll() ?: [];
    }

    public static function processReview(int $adminUserId, int $realEstateId, string $action, ?string $note = null): array
    {
        $pdo = self::db();

        // validar existe
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
                SET validation_status = 1,
                    approved_at = NOW(),
                    approved_by = :admin_id
                WHERE id = :id
                LIMIT 1
            ");
                $st->execute(['admin_id' => $adminUserId, 'id' => $realEstateId]);

                $pdo->commit();
                return [
                    'real_estate_id' => $realEstateId,
                    'validation_status' => 1,
                    'approved_by' => $adminUserId,
                ];
            }

            if ($action === 'reject') {
                // si no tenés validation_note, eliminá esa línea
                $st = $pdo->prepare("
                UPDATE real_estates
                SET validation_status = 2,
                    approved_at = NULL,
                    approved_by = NULL,
                    validation_note = :note
                WHERE id = :id
                LIMIT 1
            ");
                $st->execute(['note' => $note, 'id' => $realEstateId]);

                $pdo->commit();
                return [
                    'real_estate_id' => $realEstateId,
                    'validation_status' => 2,
                    'note' => $note,
                ];
            }

            throw new \Exception("Acción inválida");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }


    public static function listApproved(): array
{
    $pdo = self::db();
    $st = $pdo->query("
        SELECT
            r.id,
            r.name,
            r.legal_name,
            r.cuit,
            r.email,
            r.phone,
            r.address,
            r.validation_status,
            r.review_requested_at,
            r.approved_at,
            r.approved_by,
            u.email AS approved_by_email,
            r.validation_note,
            r.created_at
        FROM real_estates r
        LEFT JOIN users u ON u.id = r.approved_by
        WHERE r.deleted_at IS NULL
          AND r.validation_status = 1
        ORDER BY r.approved_at DESC, r.id DESC
        LIMIT 200
    ");
    return $st->fetchAll() ?: [];
}

public static function listRejected(): array
{
    $pdo = self::db();
    $st = $pdo->query("
        SELECT
            r.id,
            r.name,
            r.legal_name,
            r.cuit,
            r.email,
            r.phone,
            r.address,
            r.validation_status,
            r.review_requested_at,
            r.approved_at,
            r.approved_by,
            u.email AS approved_by_email,
            r.validation_note,
            r.created_at
        FROM real_estates r
        LEFT JOIN users u ON u.id = r.approved_by
        WHERE r.deleted_at IS NULL
          AND r.validation_status = 2
        ORDER BY r.review_requested_at DESC, r.id DESC
        LIMIT 200
    ");
    return $st->fetchAll() ?: [];
}
}
