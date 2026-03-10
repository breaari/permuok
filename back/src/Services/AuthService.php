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

        $password = (string)($data['password'] ?? '');
        if (trim($password) === '' || strlen($password) < 6) {
            return ['error' => 'Password inválida (mínimo 6 caracteres)'];
        }

        $first = trim((string)($data['first_name'] ?? ''));
        $last  = trim((string)($data['last_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));

        if ($first === '') return ['error' => 'first_name requerido'];
        if ($last === '')  return ['error' => 'last_name requerido'];
        if ($phone === '') return ['error' => 'phone requerido'];

        $emailNorm = strtolower(trim($email));

        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute(['email' => $emailNorm]);
        if ($check->fetch()) {
            return ['error' => 'El email ya está registrado'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO users
            (role, first_name, last_name, email, phone, password_hash, is_active, created_at)
            VALUES
            (:role, :first_name, :last_name, :email, :phone, :password, 1, NOW())
        ");

        $stmt->execute([
            'role'       => self::ROLE_REAL_ESTATE,
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $emailNorm,
            'phone'      => $phone,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
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

        $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id LIMIT 1");
        $upd->execute(['id' => (int)$user['id']]);

        $accessToken = JwtHelper::generateAccessToken([
            'id'   => (int)$user['id'],
            'role' => (int)$user['role'],
        ]);

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
    | ACCESS BUILDER
    |--------------------------------------------------------------------------
    */
    private static function buildAccessData(array $user): array
    {
        switch ((int)$user['role']) {
            case self::ROLE_SUPER_ADMIN:
                return [
                    'level' => 'super_admin',
                    'limits' => null,
                    'usage' => null,
                    'features' => ['all' => true],
                    'real_estate' => null,
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
                    'usage' => null,
                    'features' => [],
                    'real_estate' => null,
                ];
        }
    }

    private static function realEstateAccess(int $userId): array
    {
        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.status,
                r.profile_status,
                r.validation_note,
                r.review_requested_at,
                r.changes_requested_at
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
                'usage' => null,
                'features' => [],
                'real_estate' => null,
            ];
        }

        $realEstateSummary = self::getRealEstateSummary((int)$realEstate['id']);
        $profileStatus = (int)($realEstate['profile_status'] ?? self::PROFILE_DRAFT);

        if ($profileStatus === self::PROFILE_DRAFT) {
            return [
                'level' => 'real_estate_draft',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => $realEstateSummary,
            ];
        }

        if ($profileStatus === self::PROFILE_INITIAL_REVIEW) {
            return [
                'level' => 'real_estate_review',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => $realEstateSummary,
            ];
        }

        if ($profileStatus === self::PROFILE_REJECTED) {
            return [
                'level' => 'real_estate_rejected',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => $realEstateSummary,
            ];
        }

        $membership = self::getActiveMembership((int)$realEstate['id']);

        if (!$membership) {
            return [
                'level' => $profileStatus === self::PROFILE_CHANGES_PENDING
                    ? 'real_estate_unpaid_changes_pending'
                    : 'real_estate_unpaid',
                'limits' => null,
                'usage' => null,
                'features' => [
                    'profile_changes_pending' => $profileStatus === self::PROFILE_CHANGES_PENDING,
                ],
                'real_estate' => $realEstateSummary,
            ];
        }

        $usage = self::getRealEstateUsage((int)$realEstate['id']);

        return [
            'level' => $profileStatus === self::PROFILE_CHANGES_PENDING
                ? 'real_estate_active_changes_pending'
                : 'real_estate_active',
            'limits' => [
                'real_estate_users' => (int)($membership['max_users'] ?? 1),
                'agents' => (int)$membership['max_agents'],
                'investors' => (int)$membership['max_investors'],
            ],
            'usage' => $usage,
            'features' => [
                'publish_projects' => (bool)$membership['can_publish_projects'],
                'view_projects' => (bool)$membership['can_view_projects'],
                'profile_changes_pending' => $profileStatus === self::PROFILE_CHANGES_PENDING,
            ],
            'membership' => [
                'id' => (int)$membership['id'],
                'plan_id' => isset($membership['plan_id']) ? (int)$membership['plan_id'] : null,
                'scheduled_plan_id' => isset($membership['scheduled_plan_id']) && $membership['scheduled_plan_id'] !== null
                    ? (int)$membership['scheduled_plan_id']
                    : null,
                'start_date' => $membership['start_date'] ?? null,
                'end_date' => $membership['end_date'] ?? null,
                'status' => (int)($membership['status'] ?? 0),
                'cancel_at_period_end' => (int)($membership['cancel_at_period_end'] ?? 0),
                'cancelled_at' => $membership['cancelled_at'] ?? null,
                'scheduled_change_at' => $membership['scheduled_change_at'] ?? null,
            ],
            'real_estate' => $realEstateSummary,
        ];
    }

    private static function agentAccess($realEstateId): array
    {
        $realEstateId = $realEstateId !== null ? (int)$realEstateId : null;

        if (!$realEstateId) {
            return [
                'level' => 'agent_unlinked',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => null,
            ];
        }

        $membership = self::getActiveMembership($realEstateId);

        if (!$membership) {
            return [
                'level' => 'agent_restricted',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => self::getRealEstateSummary($realEstateId),
            ];
        }

        return [
            'level' => 'agent_active',
            'limits' => null,
            'usage' => null,
            'features' => [
                'publish_projects' => (bool)($membership['can_publish_projects'] ?? false),
                'view_projects'    => (bool)($membership['can_view_projects'] ?? false),
            ],
            'real_estate' => self::getRealEstateSummary($realEstateId),
        ];
    }

    private static function investorAccess($realEstateId): array
    {
        $realEstateId = $realEstateId !== null ? (int)$realEstateId : null;

        if (!$realEstateId) {
            return [
                'level' => 'investor_unlinked',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => null,
            ];
        }

        $membership = self::getActiveMembership($realEstateId);

        if (!$membership) {
            return [
                'level' => 'investor_restricted',
                'limits' => null,
                'usage' => null,
                'features' => [],
                'real_estate' => self::getRealEstateSummary($realEstateId),
            ];
        }

        return [
            'level' => 'investor_active',
            'limits' => null,
            'usage' => null,
            'features' => [
                'view_projects' => (bool)($membership['can_view_projects'] ?? false),
            ],
            'real_estate' => self::getRealEstateSummary($realEstateId),
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
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute(['real_estate_id' => $realEstateId]);
        return $stmt->fetch();
    }

    private static function getRealEstateUsage(int $realEstateId): array
    {
        $pdo = self::db();

        $stAgents = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE real_estate_id = :real_estate_id
              AND role = :role
              AND deleted_at IS NULL
        ");
        $stAgents->execute([
            'real_estate_id' => $realEstateId,
            'role' => self::ROLE_AGENT,
        ]);
        $agents = (int)($stAgents->fetch()['total'] ?? 0);

        $stInvestors = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE real_estate_id = :real_estate_id
              AND role = :role
              AND deleted_at IS NULL
        ");
        $stInvestors->execute([
            'real_estate_id' => $realEstateId,
            'role' => self::ROLE_INVESTOR,
        ]);
        $investors = (int)($stInvestors->fetch()['total'] ?? 0);

        $stRealEstateUsers = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE real_estate_id = :real_estate_id
              AND role = :role
              AND deleted_at IS NULL
        ");
        $stRealEstateUsers->execute([
            'real_estate_id' => $realEstateId,
            'role' => self::ROLE_REAL_ESTATE,
        ]);
        $realEstateUsers = (int)($stRealEstateUsers->fetch()['total'] ?? 0);

        return [
            'real_estate_users' => $realEstateUsers,
            'agents' => $agents,
            'investors' => $investors,
        ];
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

    private static function getRealEstateSummary(?int $realEstateId): ?array
    {
        $realEstateId = $realEstateId !== null ? (int)$realEstateId : null;

        if (!$realEstateId) {
            return null;
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                legal_name,
                cuit,
                address,
                phone,
                email,
                website,
                instagram,
                facebook,
                profile_status
            FROM real_estates
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $realEstateId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? null,
            'legal_name' => $row['legal_name'] ?? null,
            'cuit' => $row['cuit'] ?? null,
            'address' => $row['address'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'website' => $row['website'] ?? null,
            'instagram' => $row['instagram'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'profile_status' => isset($row['profile_status']) ? (int)$row['profile_status'] : null,
        ];
    }
}