<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

class MeController
{
    public static function handle(): void
    {
        // 1) valida JWT + usuario real en DB
        $user = AuthMiddleware::handle();

        // 2) recalcula access actual (plan/validación)
        $access = AuthService::buildAccessFromMiddleware($user['id'], $user['role']);

        // 3) respuesta uniforme
        ResponseHelper::ok([
            'user' => $user,
            'access' => $access
        ]);
    }
}