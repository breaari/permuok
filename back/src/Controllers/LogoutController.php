<?php

namespace App\Controllers;

use App\Helpers\RefreshTokenCookieHelper;
use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RefreshTokenService;

class LogoutController
{
    public static function handle(): void
    {
        /*
         * La eliminamos antes de validar el access
         * token. Incluso una sesión vencida debe
         * poder borrar su cookie del navegador.
         */
        RefreshTokenCookieHelper::clear();

        $user = AuthMiddleware::handle();

        RefreshTokenService::revokeAllByUserId(
                (int)$user['id']
            );

        ResponseHelper::ok([
            'message' => 'Logout OK',
        ]);
    }
}
