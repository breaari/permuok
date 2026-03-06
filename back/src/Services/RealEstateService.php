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
        $st = $pdo->prepare("
            SELECT id, role, real_estate_id
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $u = $st->fetch();

        if (!$u) {
            throw new Exception("Usuario no encontrado");
        }

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

        $st = $pdo->prepare("
            SELECT
                r.id,
                r.name,
                r.legal_name,
                r.cuit,
                r.address,
                r.address_place_id,
                r.address_lat,
                r.address_lng,
                r.address_locality,
                r.address_province,
                r.address_postal_code,
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

        $st = $pdo->prepare("
            SELECT
                l.id,
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

        self::normalizeAddressMapFields($data);

        $allowed = [
            'name',
            'legal_name',
            'cuit',
            'address',
            'address_place_id',
            'address_lat',
            'address_lng',
            'address_locality',
            'address_province',
            'address_postal_code',
            'phone',
            'email',
            'website',
            'instagram',
            'facebook',
        ];

        // CREATE
        if (!$u['real_estate_id']) {
            self::requireKeys($data, [
                'name',
                'legal_name',
                'cuit',
                'address',
                'phone',
                'email',
                'website',
            ]);

            self::validateProfilePayload($data, true);

            $pdo->beginTransaction();

            try {
                $st = $pdo->prepare("
                    INSERT INTO real_estates
                    (
                        name,
                        legal_name,
                        cuit,
                        address,
                        address_place_id,
                        address_lat,
                        address_lng,
                        address_locality,
                        address_province,
                        address_postal_code,
                        phone,
                        email,
                        website,
                        instagram,
                        facebook,
                        status,
                        validation_status,
                        created_at
                    )
                    VALUES
                    (
                        :name,
                        :legal_name,
                        :cuit,
                        :address,
                        :address_place_id,
                        :address_lat,
                        :address_lng,
                        :address_locality,
                        :address_province,
                        :address_postal_code,
                        :phone,
                        :email,
                        :website,
                        :instagram,
                        :facebook,
                        0,
                        0,
                        NOW()
                    )
                ");

                $st->execute([
                    'name' => $data['name'],
                    'legal_name' => $data['legal_name'],
                    'cuit' => $data['cuit'],
                    'address' => $data['address'],
                    'address_place_id' => $data['address_place_id'] ?? null,
                    'address_lat' => $data['address_lat'] ?? null,
                    'address_lng' => $data['address_lng'] ?? null,
                    'address_locality' => $data['address_locality'] ?? null,
                    'address_province' => $data['address_province'] ?? null,
                    'address_postal_code' => $data['address_postal_code'] ?? null,
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'website' => $data['website'],
                    'instagram' => $data['instagram'] ?? null,
                    'facebook' => $data['facebook'] ?? null,
                ]);

                $realEstateId = (int)$pdo->lastInsertId();

                $st = $pdo->prepare("
                    UPDATE users
                    SET real_estate_id = :re
                    WHERE id = :uid
                    LIMIT 1
                ");
                $st->execute([
                    're' => $realEstateId,
                    'uid' => $userId,
                ]);

                $pdo->commit();

                return [
                    'real_estate_id' => $realEstateId,
                    'created' => true,
                ];
            } catch (\Throwable $e) {
                $pdo->rollBack();

                if (
                    str_contains($e->getMessage(), 'Duplicate entry') &&
                    str_contains($e->getMessage(), 'cuit')
                ) {
                    throw new Exception("Ya existe una inmobiliaria registrada con ese CUIT");
                }

                throw $e;
            }
        }

        // UPDATE
        $realEstateId = (int)$u['real_estate_id'];

        $updates = self::pickUpdates($data, $allowed);

        if (count($updates) === 0) {
            return [
                'real_estate_id' => $realEstateId,
                'updated' => false,
                'message' => 'Sin cambios',
            ];
        }

        $stCurrent = $pdo->prepare("
            SELECT
                name,
                legal_name,
                cuit,
                address,
                address_place_id,
                address_lat,
                address_lng,
                address_locality,
                address_province,
                address_postal_code,
                phone,
                email,
                website,
                instagram,
                facebook
            FROM real_estates
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stCurrent->execute(['id' => $realEstateId]);
        $current = $stCurrent->fetch();

        if (!$current) {
            throw new Exception("Inmobiliaria no encontrada");
        }

        $merged = array_merge($current ?: [], $updates);
        self::validateProfilePayload($merged, true);

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

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            return [
                'real_estate_id' => $realEstateId,
                'updated' => true,
                'updated_fields' => array_keys($updates),
            ];
        } catch (\Throwable $e) {
            if (
                str_contains($e->getMessage(), 'Duplicate entry') &&
                str_contains($e->getMessage(), 'cuit')
            ) {
                throw new Exception("Ya existe una inmobiliaria registrada con ese CUIT");
            }

            throw $e;
        }
    }

    public static function addLicense(int $userId, array $data): array
    {
        $u = self::getUser($userId);

        if (!$u['real_estate_id']) {
            throw new Exception("Primero completá el perfil de la inmobiliaria");
        }

        $licenseNumber = trim((string)($data['license_number'] ?? ''));
        $provinceId = (int)($data['province_id'] ?? 0);
        $isPrimary = !empty($data['is_primary']) ? 1 : 0;

        if ($licenseNumber === '' || $provinceId <= 0) {
            throw new Exception("license_number y province_id son requeridos");
        }

        $pdo = self::db();

        $stProv = $pdo->prepare("
            SELECT id
            FROM provinces
            WHERE id = :id
              AND is_active = 1
            LIMIT 1
        ");
        $stProv->execute(['id' => $provinceId]);

        if (!$stProv->fetch()) {
            throw new Exception("Provincia inválida o inactiva");
        }

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
                (
                    real_estate_id,
                    license_number,
                    province_id,
                    is_primary,
                    created_at
                )
                VALUES
                (
                    :re,
                    :ln,
                    :pid,
                    :prim,
                    NOW()
                )
            ");
            $st->execute([
                're' => (int)$u['real_estate_id'],
                'ln' => $licenseNumber,
                'pid' => $provinceId,
                'prim' => $isPrimary,
            ]);

            $pdo->commit();

            return [
                'license_id' => (int)$pdo->lastInsertId(),
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();

            if (
                str_contains($e->getMessage(), 'Duplicate') ||
                str_contains($e->getMessage(), 'unique_license_per_province_id') ||
                str_contains($e->getMessage(), 'unique_license_per_province')
            ) {
                throw new Exception("Esa matrícula ya existe para esa provincia");
            }

            throw $e;
        }
    }

    public static function submitReview(int $userId): array
    {
        $u = self::getUser($userId);

        if (!$u['real_estate_id']) {
            throw new Exception("Primero completá el perfil de la inmobiliaria");
        }

        $pdo = self::db();

        $stRe = $pdo->prepare("
            SELECT
                id,
                name,
                legal_name,
                cuit,
                address,
                address_place_id,
                address_lat,
                address_lng,
                phone,
                email,
                website
            FROM real_estates
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stRe->execute(['id' => (int)$u['real_estate_id']]);
        $re = $stRe->fetch();

        if (!$re) {
            throw new Exception("Inmobiliaria no encontrada");
        }

        $requiredFields = [
            'name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'cuit' => 'CUIT',
            'address' => 'dirección',
            'phone' => 'teléfono',
            'email' => 'email',
            'website' => 'sitio web',
        ];

        foreach ($requiredFields as $key => $label) {
            if (!isset($re[$key]) || trim((string)$re[$key]) === '') {
                throw new Exception("Falta completar {$label}");
            }
        }

        if (!self::validateCuit((string)$re['cuit'])) {
            throw new Exception("Ingresá un CUIT válido de 11 dígitos");
        }

        if (!self::validatePhone((string)$re['phone'])) {
            throw new Exception("Ingresá un teléfono válido");
        }

        if (!self::validateWebsite((string)$re['website'])) {
            throw new Exception("Ingresá un sitio web válido");
        }

        if (
            empty($re['address_place_id']) ||
            $re['address_lat'] === null ||
            $re['address_lng'] === null
        ) {
            throw new Exception("Seleccioná una dirección válida desde Google Maps");
        }

        $st = $pdo->prepare("
            SELECT COUNT(*) c
            FROM real_estate_licenses
            WHERE real_estate_id = :re
              AND deleted_at IS NULL
              AND province_id IS NOT NULL
              AND license_number <> ''
        ");
        $st->execute(['re' => (int)$u['real_estate_id']]);
        $count = (int)($st->fetch()['c'] ?? 0);

        if ($count <= 0) {
            throw new Exception("Tenés que cargar al menos una matrícula/licencia");
        }

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
            if (!array_key_exists($k, $data)) {
                continue;
            }

            $v = $data[$k];

            if (is_string($v) && trim($v) === '') {
                continue;
            }

            $out[$k] = $v;
        }

        return $out;
    }

    private static function normalizeAddressMapFields(array &$data): void
    {
        if (array_key_exists('address_place_id', $data)) {
            $data['address_place_id'] = trim((string)$data['address_place_id']);
            if ($data['address_place_id'] === '') {
                $data['address_place_id'] = null;
            }
        }

        if (array_key_exists('address_lat', $data)) {
            $data['address_lat'] = $data['address_lat'] === '' || $data['address_lat'] === null
                ? null
                : (float)$data['address_lat'];
        }

        if (array_key_exists('address_lng', $data)) {
            $data['address_lng'] = $data['address_lng'] === '' || $data['address_lng'] === null
                ? null
                : (float)$data['address_lng'];
        }

        foreach (
            [
                'address_locality',
                'address_province',
                'address_postal_code',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string)$data[$field]);
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }
    }

    private static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string)$value);
    }

    private static function validateCuit(string $value): bool
    {
        return strlen(self::digitsOnly($value)) === 11;
    }

    private static function validatePhone(string $value): bool
    {
        return strlen(self::digitsOnly($value)) >= 8;
    }

    private static function validateWebsite(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function validateNameText(string $value): bool
    {
        $value = trim($value);

        if (mb_strlen($value) < 3 || mb_strlen($value) > 150) {
            return false;
        }

        return preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s.&,'\-()\/]+$/u", $value) === 1;
    }

    private static function validateProfilePayload(array $data, bool $requireWebsite = true): void
    {
        if (!self::validateNameText((string)($data['name'] ?? ''))) {
            throw new Exception("Ingresá un nombre comercial válido");
        }

        if (!self::validateNameText((string)($data['legal_name'] ?? ''))) {
            throw new Exception("Ingresá una razón social válida");
        }

        if (!self::validateCuit((string)($data['cuit'] ?? ''))) {
            throw new Exception("Ingresá un CUIT válido de 11 dígitos");
        }

        if (!self::validatePhone((string)($data['phone'] ?? ''))) {
            throw new Exception("Ingresá un teléfono válido");
        }

        if ($requireWebsite && !self::validateWebsite((string)($data['website'] ?? ''))) {
            throw new Exception("Ingresá un sitio web válido");
        }
    }
}
