<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RefreshTokenService;

class LogoutController
{
    public static function handle(): void
    {
        // usuario autenticado (access token)
        $user = AuthMiddleware::handle();

        // revocar todos los refresh tokens del usuario
        RefreshTokenService::revokeAllByUserId((int)$user['id']);

        ResponseHelper::ok(['message' => 'Logout OK']);
    }
}