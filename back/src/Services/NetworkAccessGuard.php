<?php

namespace App\Services;

use Exception;

class NetworkAccessGuard
{
    private const ALLOWED_LEVELS = [
        'super_admin',
        'real_estate_active',
        'real_estate_active_changes_pending',
        'agent_active',
        'investor_active',
    ];

    public static function requireAccess(
        int $userId,
        int $role
    ): array {
        $access =
            AuthService::buildAccessFromMiddleware(
                $userId,
                $role
            );

        $level =
            (string)($access['level'] ?? '');

        if (
            !in_array(
                $level,
                self::ALLOWED_LEVELS,
                true
            )
        ) {
            throw new Exception(
                'Tu cuenta todavía no tiene acceso completo a la red.',
                403
            );
        }

        return $access;
    }

    public static function hasAccess(
        int $userId,
        int $role
    ): bool {
        try {
            self::requireAccess(
                $userId,
                $role
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}