<?php

namespace App\Services;

use App\Helpers\JwtHelper;
use PDO;

class AuthService
{
    private const ROLE_SUPER_ADMIN = 1;
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;
    private const ROLE_INVESTOR = 4;

    private const REAL_ESTATE_APPROVED = 1;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTER (solo inmobiliaria)
    |--------------------------------------------------------------------------
    */
    public static function register(array $data): array
    {
        $pdo = self::db();

        $email = $data['email'] ?? '';
        if (!is_string($email) || trim($email) === '') {
            return ['error' => 'Email requerido'];
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute(['email' => trim($email)]);

        if ($check->fetch()) {
            return ['error' => 'El email ya está registrado'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO users 
            (role, first_name, last_name, email, password_hash, is_active, created_at)
            VALUES 
            (:role, :first_name, :last_name, :email, :password, 1, NOW())
        ");

        $stmt->execute([
            'role'       => self::ROLE_REAL_ESTATE,
            'first_name' => $data['first_name'] ?? '',
            'last_name'  => $data['last_name'] ?? '',
            'email'      => trim($email),
            'password'   => password_hash((string)($data['password'] ?? ''), PASSWORD_BCRYPT),
        ]);

        return ['success' => true];
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */
    public static function login(string $email, string $password)
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT id, role, email, password_hash, is_active
            FROM users
            WHERE email = :email
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            return ['error' => 'Usuario inactivo'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // ✅ access token corto (15 min) con typ=access
        $accessToken = JwtHelper::generateAccessToken([
            'id'   => (int)$user['id'],
            'role' => (int)$user['role'],
        ]);

        // ✅ refresh token en DB (hash) con rotación posterior en /refresh
        $refreshToken = RefreshTokenService::issue((int)$user['id']);

        $access = self::buildAccessFromMiddleware((int)$user['id'], (int)$user['role']);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => [
                'id'    => (int)$user['id'],
                'email' => (string)$user['email'],
                'role'  => (int)$user['role'],
            ],
            'access' => $access,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESS BUILDER (NÚCLEO DE PERMISOS)
    |--------------------------------------------------------------------------
    */
    private static function buildAccessData(array $user): array
    {
        switch ((int)$user['role']) {

            case self::ROLE_SUPER_ADMIN:
                return [
                    'level' => 'super_admin',
                    'limits' => null,
                    'features' => ['all' => true],
                ];

            case self::ROLE_REAL_ESTATE:
                return self::realEstateAccess((int)$user['id']);

            case self::ROLE_AGENT:
                return self::agentAccess($user['real_estate_id'] ?? null);

            case self::ROLE_INVESTOR:
                return self::investorAccess($user['real_estate_id'] ?? null);

            default:
                return [
                    'level' => 'unknown',
                    'limits' => null,
                    'features' => [],
                ];
        }
    }

    private static function realEstateAccess(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT r.id, r.validation_status
            FROM real_estates r
            JOIN users u ON u.real_estate_id = r.id
            WHERE u.id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $realEstate = $stmt->fetch();

        if (!$realEstate) {
            return [
                'level' => 'real_estate_not_linked',
                'limits' => null,
                'features' => [],
            ];
        }

        if ((int)$realEstate['validation_status'] !== self::REAL_ESTATE_APPROVED) {
            return [
                'level' => 'real_estate_review',
                'limits' => null,
                'features' => [],
            ];
        }

        $membership = self::getActiveMembership((int)$realEstate['id']);

        if (!$membership) {
            return [
                'level' => 'real_estate_unpaid',
                'limits' => null,
                'features' => [],
            ];
        }

        return [
            'level' => 'real_estate_active',
            'limits' => [
                'agents'    => (int)($membership['max_agents'] ?? 0),
                'investors' => (int)($membership['max_investors'] ?? 0),
            ],
            'features' => [
                'publish_projects' => (bool)($membership['can_publish_projects'] ?? false),
                'view_projects'    => (bool)($membership['can_view_projects'] ?? false),
            ],
        ];
    }

    private static function agentAccess($realEstateId): array
    {
        $realEstateId = $realEstateId !== null ? (int)$realEstateId : null;

        if (!$realEstateId) {
            return [
                'level' => 'agent_unlinked',
                'limits' => null,
                'features' => [],
            ];
        }

        $membership = self::getActiveMembership($realEstateId);

        if (!$membership) {
            return [
                'level' => 'agent_restricted',
                'limits' => null,
                'features' => [],
            ];
        }

        return [
            'level' => 'agent_active',
            'limits' => null,
            'features' => [
                'publish_projects' => (bool)($membership['can_publish_projects'] ?? false),
                'view_projects'    => (bool)($membership['can_view_projects'] ?? false),
            ],
        ];
    }

    private static function investorAccess($realEstateId): array
    {
        $realEstateId = $realEstateId !== null ? (int)$realEstateId : null;

        if (!$realEstateId) {
            return [
                'level' => 'investor_unlinked',
                'limits' => null,
                'features' => [],
            ];
        }

        $membership = self::getActiveMembership($realEstateId);

        if (!$membership) {
            return [
                'level' => 'investor_restricted',
                'limits' => null,
                'features' => [],
            ];
        }

        return [
            'level' => 'investor_active',
            'limits' => null,
            'features' => [
                'view_projects' => (bool)($membership['can_view_projects'] ?? false),
            ],
        ];
    }

    private static function getActiveMembership(int $realEstateId)
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM memberships
            WHERE real_estate_id = :real_estate_id
              AND status = 1
              AND end_date >= CURDATE()
            LIMIT 1
        ");
        $stmt->execute(['real_estate_id' => $realEstateId]);

        return $stmt->fetch();
    }

    public static function buildAccessFromMiddleware(int $userId, int $role): array
    {
        return self::buildAccessData([
            'id' => $userId,
            'role' => $role,
            'real_estate_id' => self::getRealEstateId($userId),
        ]);
    }

    private static function getRealEstateId(int $userId): ?int
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT real_estate_id
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || $row['real_estate_id'] === null) {
            return null;
        }

        return (int)$row['real_estate_id'];
    }
}