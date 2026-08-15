<?php

namespace App\Services;

use PDO;
use Exception;

class DevelopmentAmenityService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getValidPublisherUser(int $userId): array
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
        $user = $st->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT], true)) {
            throw new Exception("No tenés permisos para administrar amenities");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        return $user;
    }

    private static function assertMembershipAllowsPublishing(int $realEstateId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, status, can_publish_projects, end_date
            FROM memberships
            WHERE real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute(['real_estate_id' => $realEstateId]);
        $membership = $st->fetch();

        if (!$membership) {
            throw new Exception("La inmobiliaria no tiene una membresía activa");
        }

        if ((int)($membership['status'] ?? -1) !== 1) {
            throw new Exception("La membresía de la inmobiliaria no está activa");
        }

        if ((int)($membership['can_publish_projects'] ?? 0) !== 1) {
            throw new Exception("Tu plan no permite publicar desarrollos");
        }

        if (
            !empty($membership['end_date']) &&
            strtotime((string)$membership['end_date']) < strtotime(date('Y-m-d'))
        ) {
            throw new Exception("La membresía de la inmobiliaria está vencida");
        }
    }

    private static function getOwnedDevelopment(int $userId, int $developmentId): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            'id' => $developmentId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $development = $st->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        return [$user, $development];
    }

    public static function listByDevelopment(int $userId, int $developmentId): array
    {
        [, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT amenity_code
            FROM development_amenities
            WHERE development_id = :development_id
            ORDER BY id ASC
        ");
        $st->execute(['development_id' => (int)$development['id']]);

        return [
            'items' => $st->fetchAll(PDO::FETCH_COLUMN) ?: [],
        ];
    }

    public static function replaceAll(int $userId, int $developmentId, array $amenities): array
    {
        [, $development] = self::getOwnedDevelopment($userId, $developmentId);

        if (!in_array($development['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden guardar amenities en el estado actual del desarrollo");
        }

        if (!is_array($amenities)) {
            throw new Exception("Amenities inválidas");
        }

        $normalized = [];
        foreach ($amenities as $amenity) {
            $value = trim((string)$amenity);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare("
                DELETE FROM development_amenities
                WHERE development_id = :development_id
            ")->execute(['development_id' => $developmentId]);

            if ($normalized) {
                $st = $pdo->prepare("
                    INSERT INTO development_amenities (
                        development_id,
                        amenity_code
                    ) VALUES (
                        :development_id,
                        :amenity_code
                    )
                ");

                foreach ($normalized as $amenityCode) {
                    $st->execute([
                        'development_id' => $developmentId,
                        'amenity_code' => $amenityCode,
                    ]);
                }
            }

            $pdo->commit();
            return self::listByDevelopment($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}