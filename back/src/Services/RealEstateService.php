<?php

namespace App\Services;

use PDO;
use Exception;

class RealEstateService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getUser(int $userId): array
    {
        $pdo = self::db();
        $st = $pdo->prepare("SELECT id, role, real_estate_id FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $st->execute(['id' => $userId]);
        $u = $st->fetch();
        if (!$u) throw new Exception("Usuario no encontrado");
        return $u;
    }

    public static function getMyRealEstate(int $userId): array
    {
        $u = self::getUser($userId);

        if (!$u['real_estate_id']) {
            return [
                'real_estate' => null,
                'licenses' => [],
            ];
        }

        $pdo = self::db();

        // 🔹 TRAE DATOS DE LA INMOBILIARIA + QUIÉN APROBÓ
        $st = $pdo->prepare("
        SELECT
            r.id,
            r.name,
            r.legal_name,
            r.cuit,
            r.address,
            r.phone,
            r.email,
            r.website,
            r.instagram,
            r.facebook,
            r.status,
            r.validation_status,
            r.review_requested_at,
            r.approved_at,
            r.approved_by,
            u.email AS approved_by_email,
            r.created_at,
            r.validated_at
        FROM real_estates r
        LEFT JOIN users u ON u.id = r.approved_by
        WHERE r.id = :id
          AND r.deleted_at IS NULL
        LIMIT 1
    ");
        $st->execute(['id' => (int)$u['real_estate_id']]);
        $re = $st->fetch();

        // 🔹 TRAE LICENCIAS
        $st = $pdo->prepare("
        SELECT id, license_number, province, is_primary, created_at
        FROM real_estate_licenses
        WHERE real_estate_id = :id
          AND deleted_at IS NULL
        ORDER BY is_primary DESC, id DESC
    ");
        $st->execute(['id' => (int)$u['real_estate_id']]);
        $licenses = $st->fetchAll();

        return [
            'real_estate' => $re ?: null,
            'licenses' => $licenses ?: [],
        ];
    }

    public static function saveProfile(int $userId, array $data): array
    {
        $u = self::getUser($userId);
        $pdo = self::db();

        // Campos permitidos para crear/editar
        $allowed = [
            'name',
            'legal_name',
            'cuit',
            'address',
            'phone',
            'email',
            'website',
            'instagram',
            'facebook',
        ];

        // ---- CREATE (si no está vinculada) ----
        if (!$u['real_estate_id']) {
            // Para crear, sí exigimos obligatorios
            self::requireKeys($data, ['name', 'legal_name', 'cuit', 'address', 'phone', 'email']);

            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("
                INSERT INTO real_estates
                (name, legal_name, cuit, address, phone, email, website, instagram, facebook, status, validation_status, created_at)
                VALUES
                (:name, :legal_name, :cuit, :address, :phone, :email, :website, :instagram, :facebook, 0, 0, NOW())
            ");

                $st->execute([
                    'name' => $data['name'],
                    'legal_name' => $data['legal_name'],
                    'cuit' => $data['cuit'],
                    'address' => $data['address'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'website' => $data['website'] ?? null,
                    'instagram' => $data['instagram'] ?? null,
                    'facebook' => $data['facebook'] ?? null,
                ]);

                $realEstateId = (int)$pdo->lastInsertId();

                // vincular usuario
                $st = $pdo->prepare("UPDATE users SET real_estate_id = :re WHERE id = :uid LIMIT 1");
                $st->execute(['re' => $realEstateId, 'uid' => $userId]);

                $pdo->commit();

                return [
                    'real_estate_id' => $realEstateId,
                    'created' => true,
                ];
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // ---- UPDATE parcial (si ya existe) ----
        $realEstateId = (int)$u['real_estate_id'];

        // si mandan vacío (sin campos), devolvemos ok sin hacer nada
        $updates = self::pickUpdates($data, $allowed);
        if (count($updates) === 0) {
            return [
                'real_estate_id' => $realEstateId,
                'updated' => false,
                'message' => 'Sin cambios',
            ];
        }

        // armar SQL dinámico SET campo=:campo
        $sets = [];
        $params = ['id' => $realEstateId];

        foreach ($updates as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[$k] = $v;
        }

        $sql = "
        UPDATE real_estates
        SET " . implode(", ", $sets) . "
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        return [
            'real_estate_id' => $realEstateId,
            'updated' => true,
            'updated_fields' => array_keys($updates),
        ];
    }

    public static function addLicense(int $userId, array $data): array
    {
        $u = self::getUser($userId);
        if (!$u['real_estate_id']) {
            throw new Exception("Primero completá el perfil de la inmobiliaria");
        }

        if (empty($data['license_number']) || empty($data['province'])) {
            throw new Exception("license_number y province son requeridos");
        }

        $pdo = self::db();

        $isPrimary = !empty($data['is_primary']) ? 1 : 0;

        $pdo->beginTransaction();
        try {
            if ($isPrimary === 1) {
                $pdo->prepare("
                    UPDATE real_estate_licenses
                    SET is_primary = 0
                    WHERE real_estate_id = :re
                      AND deleted_at IS NULL
                ")->execute(['re' => (int)$u['real_estate_id']]);
            }

            $st = $pdo->prepare("
                INSERT INTO real_estate_licenses
                (real_estate_id, license_number, province, is_primary, created_at)
                VALUES
                (:re, :ln, :prov, :prim, NOW())
            ");
            $st->execute([
                're' => (int)$u['real_estate_id'],
                'ln' => $data['license_number'],
                'prov' => $data['province'],
                'prim' => $isPrimary,
            ]);

            $pdo->commit();
            return ['license_id' => (int)$pdo->lastInsertId()];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // si es unique license+province
            if (str_contains($e->getMessage(), 'uq_license_province') || str_contains($e->getMessage(), 'Duplicate')) {
                throw new Exception("Esa matrícula ya existe para esa provincia");
            }
            throw $e;
        }
    }

    public static function submitReview(int $userId): array
    {
        $u = self::getUser($userId);
        if (!$u['real_estate_id']) {
            throw new \Exception("Primero completá el perfil de la inmobiliaria");
        }

        $pdo = self::db();

        // Debe tener al menos 1 licencia
        $st = $pdo->prepare("
        SELECT COUNT(*) c
        FROM real_estate_licenses
        WHERE real_estate_id = :re
          AND deleted_at IS NULL
    ");
        $st->execute(['re' => (int)$u['real_estate_id']]);
        $count = (int)($st->fetch()['c'] ?? 0);

        if ($count <= 0) {
            throw new \Exception("Tenés que cargar al menos una matrícula/licencia");
        }

        // Marcar envío a revisión (solo setear fecha si todavía es null)
        $st = $pdo->prepare("
  UPDATE real_estates
  SET
    review_requested_at = NOW(),
    validation_status = 0,
    approved_at = NULL,
    approved_by = NULL, 
    validation_note = NULL
  WHERE id = :id
    AND deleted_at IS NULL
  LIMIT 1
");
        $st->execute(['id' => (int)$u['real_estate_id']]);

        return ['submitted' => true];
    }
    private static function requireKeys(array $data, array $required): void
    {
        foreach ($required as $k) {
            if (!isset($data[$k]) || trim((string)$data[$k]) === '') {
                throw new Exception("Falta campo requerido: {$k}");
            }
        }
    }

    private static function pickUpdates(array $data, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $data)) continue;

            // No pisar con string vacío; si querés permitir vaciar, sacá esta condición.
            $v = $data[$k];
            if (is_string($v) && trim($v) === '') continue;

            $out[$k] = $v;
        }
        return $out;
    }
}
