<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

class MeController
{
    public static function handle(): void
    {
        $user = AuthMiddleware::handle();
        $access = AuthService::buildAccessFromMiddleware($user['id'], $user['role']);

        ResponseHelper::ok([
            'user' => $user,
            'access' => $access,
            'real_estate' => $access['real_estate'] ?? null,
        ]);
    }
}