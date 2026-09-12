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
                'Email y contraseña son requeridos.',
                422
            );
        }

        $emailNormalized =
            strtolower(trim($email));

        $clientIp =
            SecurityRateLimitService::clientIp();

        $loginIdentifier =
            $emailNormalized . '|' . $clientIp;

        /*
         * Primero comprobamos si existe un bloqueo.
         * Estas funciones no incrementan intentos.
         */
        SecurityRateLimitService::check(
            'auth_login_email_ip',
            $loginIdentifier
        );

        SecurityRateLimitService::check(
            'auth_login_account',
            $emailNormalized
        );

        /*
         * Recién ahora comprobamos las credenciales.
         */
        $result =
            AuthService::login(
                $emailNormalized,
                $password
            );

        if ($result === false) {
            /*
             * Protección general de la cuenta:
             * 30 errores desde distintas IP generan
             * una pausa de 15 minutos.
             */
            SecurityRateLimitService::recordFailure(
                'auth_login_account',
                $emailNormalized,
                30,
                [15 * 60]
            );

            /*
             * Protección progresiva para email + IP:
             *
             * Primer bloqueo: 30 segundos.
             * Segundo bloqueo: 5 minutos.
             * Tercero y siguientes: 15 minutos.
             */
            $loginRateLimit =
                SecurityRateLimitService::recordFailure(
                    'auth_login_email_ip',
                    $loginIdentifier,
                    5,
                    [30, 5 * 60, 15 * 60]
                );

            $remainingAttempts =
                (int)(
                    $loginRateLimit['remaining']
                    ?? 0
                );

            if ($remainingAttempts === 1) {
                $message =
                    'Los datos ingresados no coinciden. Revisalos antes de volver a intentar: si el próximo intento tampoco es correcto, deberás esperar un momento para probar nuevamente.';
            } elseif ($remainingAttempts === 2) {
                $message =
                    'Los datos ingresados no coinciden. Revisalos con atención antes de volver a intentar. Te quedan 2 intentos disponibles.';
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
         * Si el ingreso es correcto, eliminamos
         * los intentos y penalizaciones acumulados.
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
