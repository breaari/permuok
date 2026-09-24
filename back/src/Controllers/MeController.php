<?php

namespace App\Controllers;

use App\Helpers\QueryParamHelper;
use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

class MeController
{
    public static function handle(): void
    {
        QueryParamHelper::rejectUnknown([]);

        $user =
            AuthMiddleware::handle();

        $access =
            AuthService::buildAccessFromMiddleware(
                (int)$user['id'],
                (int)$user['role']
            );

        ResponseHelper::ok([
            'user' => $user,
            'access' => $access,
            'real_estate' =>
            $access['real_estate'] ?? null,
        ]);
    }
}
