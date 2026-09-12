<?php

namespace App\Helpers;

use App\Middleware\AuthMiddleware;

/**
 * Punto de entrada compatible para los controllers existentes.
 *
 * Centraliza la autenticación para validar contra la base:
 * - firma y tipo del access token;
 * - existencia y estado actual del usuario;
 * - rol actual;
 * - pertenencia y estado de la inmobiliaria.
 */
class AuthHelper
{
    public static function requireUser(): array
    {
        return AuthMiddleware::handle();
    }
}
