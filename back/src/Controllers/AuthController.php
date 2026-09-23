<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AuthService;
use App\Services\SecurityRateLimitService;
use App\Helpers\RefreshTokenCookieHelper;
use App\Services\RefreshTokenService;

class AuthController
{

    private static function readJsonObject(): array
    {
        $raw = file_get_contents('php://input');

        if (
            !is_string($raw) ||
            trim($raw) === ''
        ) {
            ResponseHelper::fail(
                'El cuerpo JSON es obligatorio.',
                422
            );
        }

        $trimmed = ltrim($raw);

        if (
            $trimmed === '' ||
            $trimmed[0] !== '{'
        ) {
            ResponseHelper::fail(
                'El cuerpo debe ser un objeto JSON.',
                422
            );
        }

        try {
            $data = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            ResponseHelper::fail(
                'El cuerpo JSON no es válido.',
                422
            );
        }

        if (!is_array($data)) {
            ResponseHelper::fail(
                'El cuerpo debe ser un objeto JSON.',
                422
            );
        }

        return $data;
    }

    public static function register(): void
    {
        /*
     * El límite por IP se aplica incluso
     * cuando el JSON recibido es inválido.
     */
        SecurityRateLimitService::hit(
            'auth_register_ip',
            SecurityRateLimitService::clientIp(),
            5,
            60 * 60
        );

        $data =
            self::readJsonObject();

        $allowedFields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
        ];

        $unknownFields =
            array_diff(
                array_keys($data),
                $allowedFields
            );

        if ($unknownFields !== []) {
            ResponseHelper::fail(
                'La solicitud contiene campos no permitidos.',
                422
            );
        }

        $requiredFields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $data
                )
            ) {
                ResponseHelper::fail(
                    "Falta el campo requerido: {$field}.",
                    422
                );
            }

            if (!is_string($data[$field])) {
                ResponseHelper::fail(
                    "El campo {$field} tiene un formato inválido.",
                    422
                );
            }
        }

        $result =
            AuthService::register($data);

        if (isset($result['error'])) {
            ResponseHelper::fail(
                $result['error'],
                422
            );
        }

        ResponseHelper::ok(
            [
                'message' =>
                'Usuario creado',
            ],
            201
        );
    }

    public static function login(): void
    {
        $data =
            self::readJsonObject();

        $allowedFields = [
            'email',
            'password',
        ];

        $unknownFields =
            array_diff(
                array_keys($data),
                $allowedFields
            );

        if ($unknownFields !== []) {
            ResponseHelper::fail(
                'La solicitud contiene campos no permitidos.',
                422
            );
        }

        $email =
            $data['email']
            ?? null;

        $password =
            $data['password']
            ?? null;

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
            strtolower(
                trim($email)
            );

        if (
            strlen($emailNormalized) > 254 ||
            !filter_var(
                $emailNormalized,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            ResponseHelper::fail(
                'Ingresá un email válido.',
                422
            );
        }

        $clientIp =
            SecurityRateLimitService::clientIp();

        $loginIdentifier =
            $emailNormalized .
            '|' .
            $clientIp;

        /*
     * Estas comprobaciones no incrementan
     * todavía la cantidad de intentos.
     */
        SecurityRateLimitService::check(
            'auth_login_email_ip',
            $loginIdentifier
        );

        SecurityRateLimitService::check(
            'auth_login_account',
            $emailNormalized
        );

        $result =
            AuthService::login(
                $emailNormalized,
                $password
            );

        if ($result === false) {
            SecurityRateLimitService::recordFailure(
                'auth_login_account',
                $emailNormalized,
                30,
                [15 * 60]
            );

            $loginRateLimit =
                SecurityRateLimitService::recordFailure(
                    'auth_login_email_ip',
                    $loginIdentifier,
                    5,
                    [
                        30,
                        5 * 60,
                        15 * 60,
                    ]
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
                    'code' =>
                    'INVALID_CREDENTIALS',

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

        SecurityRateLimitService::clear(
            'auth_login_email_ip',
            $loginIdentifier
        );

        SecurityRateLimitService::clear(
            'auth_login_account',
            $emailNormalized
        );

        /*
     * El refresh token se entrega solamente
     * mediante la cookie HttpOnly.
     */
        if (
            is_array($result) &&
            !empty($result['refresh_token'])
        ) {
            $refreshToken =
                (string)$result['refresh_token'];

            try {
                RefreshTokenCookieHelper::write(
                    $refreshToken
                );
            } catch (\Throwable $e) {
                try {
                    $stored =
                        RefreshTokenService::findValid(
                            $refreshToken
                        );

                    if ($stored) {
                        RefreshTokenService::revokeById(
                            (int)$stored['id']
                        );
                    }
                } catch (\Throwable $cleanupError) {
                    error_log(
                        'No se pudo limpiar el refresh token ' .
                            'después de un login fallido: ' .
                            $cleanupError->getMessage()
                    );
                }

                RefreshTokenCookieHelper::clear();

                throw $e;
            }

            unset($result['refresh_token']);
        }

        ResponseHelper::ok($result);
    }
    
}
