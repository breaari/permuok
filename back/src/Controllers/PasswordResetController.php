<?php

namespace App\Controllers;

use App\Helpers\RefreshTokenCookieHelper;
use App\Helpers\ResponseHelper;
use App\Services\PasswordResetService;
use App\Services\SecurityRateLimitService;
use Throwable;

class PasswordResetController
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

    public static function request(): void
    {
        /*
     * Se aplica antes de procesar el JSON
     * para contar también solicitudes inválidas.
     */
        SecurityRateLimitService::hit(
            'password_reset_ip',
            SecurityRateLimitService::clientIp(),
            5,
            60 * 60
        );

        $data =
            self::readJsonObject();

        $unknownFields =
            array_diff(
                array_keys($data),
                ['email']
            );

        if ($unknownFields !== []) {
            ResponseHelper::fail(
                'La solicitud contiene campos no permitidos.',
                422
            );
        }

        $emailValue =
            $data['email']
            ?? null;

        if (!is_string($emailValue)) {
            ResponseHelper::fail(
                'Ingresá un email válido.',
                422
            );
        }

        $email =
            strtolower(
                trim($emailValue)
            );

        if (
            $email === '' ||
            strlen($email) > 254 ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            ResponseHelper::fail(
                'Ingresá un email válido.',
                422
            );
        }

        SecurityRateLimitService::hit(
            'password_reset_email_ip',
            hash(
                'sha256',
                $email .
                    '|' .
                    SecurityRateLimitService::clientIp()
            ),
            3,
            15 * 60
        );

        /*
     * Se aplica también a correos inexistentes
     * para no revelar si están registrados.
     */
        SecurityRateLimitService::hit(
            'password_reset_email',
            hash(
                'sha256',
                $email
            ),
            5,
            60 * 60
        );

        try {
            PasswordResetService::request(
                $email
            );
        } catch (Throwable $e) {
            error_log(
                '[PASSWORD RESET REQUEST] ' .
                    $e->getMessage()
            );

            ResponseHelper::fail(
                'No se pudo procesar la solicitud.',
                500
            );
        }

        ResponseHelper::ok([
            'message' =>
            'Si hay una cuenta asociada a esa dirección, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    public static function reset(): void
    {
        /*
     * Contamos también intentos con JSON
     * inválido o campos incorrectos.
     */
        SecurityRateLimitService::hit(
            'password_reset_attempt_ip',
            SecurityRateLimitService::clientIp(),
            10,
            60 * 60
        );

        $data =
            self::readJsonObject();

        $allowedFields = [
            'token',
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

        $token =
            $data['token']
            ?? null;

        $password =
            $data['password']
            ?? null;

        if (
            !is_string($token) ||
            trim($token) === ''
        ) {
            ResponseHelper::fail(
                'El enlace es inválido o venció.',
                400
            );
        }

        if (!is_string($password)) {
            ResponseHelper::fail(
                'La contraseña no tiene un formato válido.',
                422
            );
        }

        try {
            PasswordResetService::reset(
                trim($token),
                $password
            );

            RefreshTokenCookieHelper::clear();

            ResponseHelper::ok([
                'message' =>
                'La contraseña fue actualizada correctamente.',
            ]);
        } catch (Throwable $e) {
            $status =
                in_array(
                    (int)$e->getCode(),
                    [
                        400,
                        422,
                    ],
                    true
                )
                ? (int)$e->getCode()
                : 500;

            if ($status === 500) {
                error_log(
                    '[PASSWORD RESET] ' .
                        $e->getMessage()
                );
            }

            ResponseHelper::fail(
                $status === 500
                    ? 'No se pudo actualizar la contraseña.'
                    : $e->getMessage(),
                $status
            );
        }
    }
}
