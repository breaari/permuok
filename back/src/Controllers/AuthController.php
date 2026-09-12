<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AuthService;
use App\Services\SecurityRateLimitService;

class AuthController
{
    public static function register(): void
    {
        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

        /*
         * Máximo 5 intentos de registro
         * por dirección IP cada hora.
         */
        SecurityRateLimitService::hit(
            'auth_register_ip',
            SecurityRateLimitService::clientIp(),
            5,
            60 * 60
        );

        $result =
            AuthService::register($data);

        if (isset($result['error'])) {
            ResponseHelper::fail(
                $result['error'],
                422
            );
        }

        ResponseHelper::ok(
            ['message' => 'Usuario creado'],
            201
        );
    }

    public static function login(): void
    {
        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (
            !is_string($email) ||
            trim($email) === '' ||
            !is_string($password) ||
            $password === ''
        ) {
            ResponseHelper::fail(
                'Email y password son requeridos',
                422
            );
        }

        $emailNormalized =
            strtolower(trim($email));

        $clientIp =
            SecurityRateLimitService::clientIp();

        /*
         * Primer límite:
         * máximo 5 intentos para la misma combinación
         * de cuenta e IP cada 15 minutos.
         */
        $loginIdentifier =
            $emailNormalized . '|' . $clientIp;

        $loginRateLimit =
            SecurityRateLimitService::hit(
                'auth_login_email_ip',
                $loginIdentifier,
                5,
                15 * 60
            );

        /*
         * Segundo límite:
         * máximo 30 intentos totales sobre una cuenta
         * cada 15 minutos, aunque se utilicen distintas IP.
         */
        SecurityRateLimitService::hit(
            'auth_login_account',
            $emailNormalized,
            30,
            15 * 60
        );

        $result =
            AuthService::login(
                $emailNormalized,
                $password
            );

        if ($result === false) {
            $remainingAttempts =
                (int)($loginRateLimit['remaining'] ?? 0);

            if ($remainingAttempts === 1) {
                $message =
                    'El email o la contraseña no coinciden. Por seguridad, te queda 1 intento antes de pausar temporalmente el acceso durante 15 minutos.';
            } elseif ($remainingAttempts === 2) {
                $message =
                    'El email o la contraseña no coinciden. Te quedan 2 intentos antes de que el acceso se pause temporalmente durante 15 minutos.';
            } else {
                $message =
                    'El email o la contraseña no coinciden. Revisá los datos ingresados y volvé a intentar.';
            }

            ResponseHelper::fail(
                $message,
                401,
                [
                    'code' => 'INVALID_CREDENTIALS',
                    'remaining_attempts' =>
                    $remainingAttempts,
                ]
            );
        }



        if (
            is_array($result) &&
            isset($result['error'])
        ) {
            ResponseHelper::fail(
                $result['error'],
                403
            );
        }

        /*
         * Un login correcto reinicia los contadores
         * asociados a esa cuenta.
         */
        SecurityRateLimitService::clear(
            'auth_login_email_ip',
            $loginIdentifier
        );

        SecurityRateLimitService::clear(
            'auth_login_account',
            $emailNormalized
        );

        ResponseHelper::ok($result);
    }
}
