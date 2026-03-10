<?php

namespace App\Services;

use PDO;
use Exception;

class UserService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;
    private const ROLE_INVESTOR = 4;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getRealEstateUser(int $userId): array
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
        $user = $st->fetch();

        if (!$user || (int)$user['role'] !== self::ROLE_REAL_ESTATE) {
            throw new Exception("No autorizado");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("La inmobiliaria no está vinculada");
        }

        return $user;
    }

    private static function getActiveMembership(int $realEstateId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE real_estate_id = :re
              AND status = 1
              AND end_date >= CURDATE()
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute(['re' => $realEstateId]);
        $membership = $st->fetch();

        if (!$membership) {
            throw new Exception("Necesitás una membresía activa para administrar usuarios");
        }

        return $membership;
    }

    private static function countUsersByRole(int $realEstateId, int $role): int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE real_estate_id = :re
              AND role = :role
              AND deleted_at IS NULL
        ");
        $st->execute([
            're' => $realEstateId,
            'role' => $role,
        ]);

        return (int)($st->fetch()['total'] ?? 0);
    }

    private static function requireAvailableSlot(int $realEstateId, int $role, array $membership): void
    {
        if ($role === self::ROLE_AGENT) {
            $used = self::countUsersByRole($realEstateId, self::ROLE_AGENT);
            $limit = (int)($membership['max_agents'] ?? 0);

            if ($used >= $limit) {
                throw new Exception("Alcanzaste el límite de agentes de tu membresía");
            }

            return;
        }

        if ($role === self::ROLE_INVESTOR) {
            $used = self::countUsersByRole($realEstateId, self::ROLE_INVESTOR);
            $limit = (int)($membership['max_investors'] ?? 0);

            if ($used >= $limit) {
                throw new Exception("Alcanzaste el límite de inversores de tu membresía");
            }

            return;
        }

        throw new Exception("Rol inválido");
    }

    private static function validateCreatePayload(array $data): array
    {
        $role = (int)($data['role'] ?? 0);
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $phone = trim((string)($data['phone'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if (!in_array($role, [self::ROLE_AGENT, self::ROLE_INVESTOR], true)) {
            throw new Exception("Solo podés crear agentes o inversores");
        }

        if ($firstName === '') {
            throw new Exception("first_name requerido");
        }

        if ($lastName === '') {
            throw new Exception("last_name requerido");
        }

        if ($email === '') {
            throw new Exception("email requerido");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }

        if ($phone === '') {
            throw new Exception("phone requerido");
        }

        if (trim($password) === '' || strlen($password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }

        return [
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ];
    }

    public static function listForRealEstate(int $ownerUserId): array
    {
        $owner = self::getRealEstateUser($ownerUserId);
        $membership = self::getActiveMembership((int)$owner['real_estate_id']);
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                id,
                role,
                first_name,
                last_name,
                email,
                phone,
                is_active,
                created_at
            FROM users
            WHERE real_estate_id = :re
              AND role IN (:agent_role, :investor_role)
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
        ");

        // PDO no permite bind array directo, así que se pasan individuales:
        $sql = "
            SELECT
                id,
                role,
                first_name,
                last_name,
                email,
                phone,
                is_active,
                created_at
            FROM users
            WHERE real_estate_id = :re
              AND role IN (" . self::ROLE_AGENT . ", " . self::ROLE_INVESTOR . ")
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
        ";

        $st = $pdo->prepare($sql);
        $st->execute([
            're' => (int)$owner['real_estate_id'],
        ]);

        $items = $st->fetchAll() ?: [];

        $agentsUsed = self::countUsersByRole((int)$owner['real_estate_id'], self::ROLE_AGENT);
        $investorsUsed = self::countUsersByRole((int)$owner['real_estate_id'], self::ROLE_INVESTOR);

        return [
            'items' => $items,
            'summary' => [
                'agents_used' => $agentsUsed,
                'agents_limit' => (int)($membership['max_agents'] ?? 0),
                'investors_used' => $investorsUsed,
                'investors_limit' => (int)($membership['max_investors'] ?? 0),
            ],
        ];
    }

    public static function createForRealEstate(int $ownerUserId, array $data): array
    {
        $owner = self::getRealEstateUser($ownerUserId);
        $membership = self::getActiveMembership((int)$owner['real_estate_id']);
        $payload = self::validateCreatePayload($data);
        $pdo = self::db();

        self::requireAvailableSlot(
            (int)$owner['real_estate_id'],
            (int)$payload['role'],
            $membership
        );

        $stCheck = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stCheck->execute(['email' => $payload['email']]);

        if ($stCheck->fetch()) {
            throw new Exception("El email ya está registrado");
        }

        $st = $pdo->prepare("
            INSERT INTO users
            (
                real_estate_id,
                role,
                first_name,
                last_name,
                email,
                password_hash,
                phone,
                is_active,
                created_at
            )
            VALUES
            (
                :real_estate_id,
                :role,
                :first_name,
                :last_name,
                :email,
                :password_hash,
                :phone,
                1,
                NOW()
            )
        ");
        $st->execute([
            'real_estate_id' => (int)$owner['real_estate_id'],
            'role' => (int)$payload['role'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'password_hash' => password_hash($payload['password'], PASSWORD_BCRYPT),
            'phone' => $payload['phone'],
        ]);

        return [
            'created' => true,
            'user_id' => (int)$pdo->lastInsertId(),
        ];
    }

    public static function updateStatusForRealEstate(int $ownerUserId, array $data): array
    {
        $owner = self::getRealEstateUser($ownerUserId);
        $pdo = self::db();

        $userId = (int)($data['user_id'] ?? 0);
        $isActive = isset($data['is_active']) ? (int)!!$data['is_active'] : null;

        if ($userId <= 0) {
            throw new Exception("user_id requerido");
        }

        if ($isActive === null) {
            throw new Exception("is_active requerido");
        }

        $st = $pdo->prepare("
            SELECT id, role, real_estate_id
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

        if ((int)$target['real_estate_id'] !== (int)$owner['real_estate_id']) {
            throw new Exception("No podés modificar usuarios de otra inmobiliaria");
        }

        if (!in_array((int)$target['role'], [self::ROLE_AGENT, self::ROLE_INVESTOR], true)) {
            throw new Exception("Solo podés modificar agentes o inversores");
        }

        $st = $pdo->prepare("
            UPDATE users
            SET is_active = :is_active
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([
            'is_active' => $isActive,
            'id' => $userId,
        ]);

        return [
            'updated' => true,
            'user_id' => $userId,
            'is_active' => $isActive,
        ];
    }
}