<?php

namespace App\Controllers;

use App\Helpers\RefreshTokenCookieHelper;
use App\Helpers\ResponseHelper;
use App\Services\RefreshTokenService;

class LogoutController
{
    public static function handle(): void
    {
        /*
         * Guardamos el token antes de borrar
         * la cookie del navegador.
         */
        $refreshToken =
            RefreshTokenCookieHelper::read();

        /*
         * La cookie debe eliminarse incluso
         * si el token ya venció o fue revocado.
         */
        RefreshTokenCookieHelper::clear();

        if ($refreshToken !== null) {
            $stored =
                RefreshTokenService::findValid(
                    $refreshToken
                );

            if ($stored) {
                RefreshTokenService::revokeById(
                    (int)$stored['id']
                );
            }
        }

        ResponseHelper::ok([
            'message' =>
                'Sesión cerrada correctamente.',
        ]);
    }
}