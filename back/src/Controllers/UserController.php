<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\UserService;
use PDOException;
use Throwable;

class UserController
{
    private static function requireRealEstate(): array
    {
        $ctx = AuthMiddleware::handle();

        if (
            (int)($ctx['role'] ?? 0) !== 2
        ) {
            ResponseHelper::fail(
                'No autorizado',
                403
            );
        }

        return $ctx;
    }

    private static function readJsonObject(): array
    {
        $raw = file_get_contents('php://input');

        if (
            !is_string($raw) ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo JSON es obligatorio.',
                422
            );
        }

        $trimmed = ltrim($raw);

        if (
            $trimmed === '' ||
            $trimmed[0] !== '{'
        ) {
            throw new \Exception(
                'El cuerpo debe ser un objeto JSON.',
                422
            );
        }

        try {
            $payload = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON no es válido.',
                422
            );
        }

        if (!is_array($payload)) {
            throw new \Exception(
                'El cuerpo debe ser un objeto JSON.',
                422
            );
        }

        return $payload;
    }

    private static function handleError(
        Throwable $e
    ): void {
        /*
     * Los errores internos nunca deben
     * enviarse directamente al cliente.
     */
        if (
            $e instanceof PDOException ||
            $e->getMessage() ===
            'No se pudo generar la contraseña'
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'UserController'
            );

            return;
        }

        $message =
            trim(
                $e->getMessage()
            );
        $code =
            (int)$e->getCode();

        if (
            $code >= 400 &&
            $code <= 499
        ) {
            ResponseHelper::fail(
                $message !== ''
                    ? $message
                    : 'No se pudo completar la operación.',
                $code
            );

            return;
        }
        if (
            $message === 'No autorizado' ||
            $message ===
            'No podés modificar usuarios de otra inmobiliaria' ||
            $message ===
            'Solo podés modificar agentes o inversores'
        ) {
            ResponseHelper::fail(
                $message,
                403
            );

            return;
        }

        if (
            $message ===
            'La inmobiliaria no está vinculada' ||
            $message ===
            'Inmobiliaria no encontrada' ||
            $message ===
            'Usuario no encontrado'
        ) {
            ResponseHelper::fail(
                $message,
                404
            );

            return;
        }

        if (
            $message ===
            'Necesitás una membresía activa para administrar usuarios'
        ) {
            ResponseHelper::fail(
                $message,
                402
            );

            return;
        }

        ResponseHelper::fail(
            $message !== ''
                ? $message
                : 'No se pudo completar la operación.',
            422
        );
    }

    public static function list(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $data =
                UserService::listForRealEstate(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function create(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                self::readJsonObject();

            $allowedFields = [
                'role',
                'first_name',
                'last_name',
                'email',
                'phone',
                'password',
            ];

            $unknownFields =
                array_diff(
                    array_keys($payload),
                    $allowedFields
                );

            if ($unknownFields !== []) {
                throw new \Exception(
                    'La solicitud contiene campos no permitidos.',
                    422
                );
            }

            $requiredFields = [
                'role',
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
                        $payload
                    )
                ) {
                    throw new \Exception(
                        "Falta el campo requerido: {$field}.",
                        422
                    );
                }
            }

            foreach (
                [
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'password',
                ] as $field
            ) {
                if (!is_string($payload[$field])) {
                    throw new \Exception(
                        "El campo {$field} tiene un formato inválido.",
                        422
                    );
                }
            }

            $roleValue =
                $payload['role'];

            if (
                !is_int($roleValue) &&
                !(
                    is_string($roleValue) &&
                    preg_match(
                        '/^[0-9]+$/',
                        $roleValue
                    ) === 1
                )
            ) {
                throw new \Exception(
                    'Solo podés crear agentes o inversores.',
                    422
                );
            }

            $data =
                UserService::createForRealEstate(
                    (int)$ctx['id'],
                    [
                        'role' =>
                        (int)$roleValue,

                        'first_name' =>
                        $payload['first_name'],

                        'last_name' =>
                        $payload['last_name'],

                        'email' =>
                        $payload['email'],

                        'phone' =>
                        $payload['phone'],

                        'password' =>
                        $payload['password'],
                    ]
                );

            ResponseHelper::ok(
                $data,
                201
            );
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function updateStatus(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                self::readJsonObject();

            $allowedFields = [
                'user_id',
                'is_active',
            ];

            $unknownFields =
                array_diff(
                    array_keys($payload),
                    $allowedFields
                );

            if ($unknownFields !== []) {
                throw new \Exception(
                    'La solicitud contiene campos no permitidos.',
                    422
                );
            }

            $userIdValue =
                $payload['user_id']
                ?? null;

            if (
                !is_int($userIdValue) &&
                !(
                    is_string($userIdValue) &&
                    preg_match(
                        '/^[1-9][0-9]*$/',
                        $userIdValue
                    ) === 1
                )
            ) {
                throw new \Exception(
                    'user_id inválido.',
                    422
                );
            }

            $userId =
                (int)$userIdValue;

            if ($userId <= 0) {
                throw new \Exception(
                    'user_id inválido.',
                    422
                );
            }

            $rawIsActive =
                $payload['is_active']
                ?? null;

            if (
                !in_array(
                    $rawIsActive,
                    [
                        0,
                        1,
                        false,
                        true,
                        '0',
                        '1',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'is_active debe ser 0 o 1.',
                    422
                );
            }

            $isActive =
                in_array(
                    $rawIsActive,
                    [
                        1,
                        true,
                        '1',
                    ],
                    true
                )
                ? 1
                : 0;

            $data =
                UserService::updateStatusForRealEstate(
                    (int)$ctx['id'],
                    [
                        'user_id' =>
                        $userId,

                        'is_active' =>
                        $isActive,
                    ]
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }
}
