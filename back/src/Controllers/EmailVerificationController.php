<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\EmailVerificationService;
use App\Services\SecurityRateLimitService;
use Throwable;

class EmailVerificationController
{
    private static function readJsonObject(): array
    {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            !is_string($raw) ||
            trim($raw) === ''
        ) {
            ResponseHelper::fail(
                'El cuerpo JSON es obligatorio.',
                422
            );
        }

        $trimmed =
            ltrim($raw);

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
            $data =
                json_decode(
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

    public static function verify(): void
    {
        SecurityRateLimitService::hit(
            'email_verification_ip',
            SecurityRateLimitService::clientIp(),
            20,
            60 * 60
        );

        $data =
            self::readJsonObject();

        $unknownFields =
            array_diff(
                array_keys($data),
                ['token']
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

        if (
            !is_string($token) ||
            trim($token) === ''
        ) {
            ResponseHelper::fail(
                'El enlace es inválido o venció.',
                400
            );
        }

        try {
            EmailVerificationService::verify(
                trim($token)
            );

            ResponseHelper::ok([
                'message' =>
                'Tu email fue verificado correctamente.',
            ]);
        } catch (Throwable $e) {
            $status =
                in_array(
                    (int)$e->getCode(),
                    [
                        400,
                        403,
                        422,
                    ],
                    true
                )
                ? (int)$e->getCode()
                : 500;

            if ($status === 500) {
                error_log(
                    '[EMAIL VERIFICATION] ' .
                        $e->getMessage()
                );
            }

            ResponseHelper::fail(
                $status === 500
                    ? 'No se pudo verificar el email.'
                    : $e->getMessage(),
                $status
            );
        }
    }

    public static function resend(): void
    {
        SecurityRateLimitService::hit(
            'email_verification_resend_ip',
            SecurityRateLimitService::clientIp(),
            10,
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

        /*
         * También limitamos por email + IP.
         */
        SecurityRateLimitService::hit(
            'email_verification_resend_email_ip',
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
            EmailVerificationService::resend(
                $email
            );
        } catch (Throwable $e) {
            error_log(
                '[EMAIL VERIFICATION RESEND] ' .
                    $e->getMessage()
            );

            ResponseHelper::fail(
                'No se pudo procesar la solicitud.',
                500
            );
        }

        /*
         * Respuesta genérica para evitar revelar
         * si la dirección existe o ya está verificada.
         */
        ResponseHelper::ok([
            'message' =>
            'Si la cuenta todavía necesita verificación, recibirás un nuevo enlace por email.',
        ]);
    }
}
