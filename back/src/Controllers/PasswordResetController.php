<?php

namespace App\Controllers;

use App\Helpers\RefreshTokenCookieHelper;
use App\Helpers\ResponseHelper;
use App\Services\PasswordResetService;
use App\Services\SecurityRateLimitService;
use Throwable;

class PasswordResetController
{
    public static function request(): void
    {
        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

        $email =
            strtolower(
                trim(
                    (string)(
                        $data['email']
                        ?? ''
                    )
                )
            );

        if (
            $email === '' ||
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

        /*
         * Evita envíos masivos desde una misma IP.
         */
        SecurityRateLimitService::hit(
            'password_reset_ip',
            SecurityRateLimitService::clientIp(),
            5,
            60 * 60
        );

        /*
         * Evita repetir solicitudes para la misma
         * cuenta desde una misma dirección.
         */
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

        /*
         * No revelamos si el email existe.
         */
        ResponseHelper::ok([
            'message' =>
            'Si existe una cuenta habilitada con ese email, te enviaremos un enlace para crear una nueva contraseña. Puede tardar algunos minutos. Revisá también la carpeta de correo no deseado. Por seguridad, no podemos confirmar si el email está registrado. Si no recibís el mensaje, verificá la dirección ingresada o contactá al administrador de tu inmobiliaria.',
        ]);
    }

    public static function reset(): void
    {
        $data =
            json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

        $token =
            (string)(
                $data['token']
                ?? ''
            );

        $password =
            (string)(
                $data['password']
                ?? ''
            );

        SecurityRateLimitService::hit(
            'password_reset_attempt_ip',
            SecurityRateLimitService::clientIp(),
            10,
            60 * 60
        );

        try {
            PasswordResetService::reset(
                $token,
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
                    [400, 422],
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
