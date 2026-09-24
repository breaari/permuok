<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Helpers\QueryParamHelper;
use App\Middleware\AuthMiddleware;
use App\Services\BillingService;

class BillingController
{

    private static function error(
        \Throwable $e
    ): void {
        $message =
            trim($e->getMessage());

        $status =
            (int)$e->getCode();

        /*
     * Conservamos los códigos de validación,
     * autorización y conflicto generados
     * explícitamente por el controlador.
     */
        if (
            !($e instanceof \PDOException) &&
            $status >= 400 &&
            $status <= 499
        ) {
            ResponseHelper::fail(
                $message !== ''
                    ? $message
                    : 'La solicitud no es válida.',
                $status
            );

            return;
        }

        $conflictMessages = [
            'Ya se está procesando otra operación',
            'Ya se está generando una suscripción',
            'Ya tenés una membresía activa',
            'La suscripción ya fue autorizada',
            'Ese plan ya es el plan actual',
            'La renovación de la membresía está cancelada',
        ];

        foreach (
            $conflictMessages as $fragment
        ) {
            if (
                str_contains(
                    $message,
                    $fragment
                )
            ) {
                ResponseHelper::fail(
                    $message,
                    409
                );

                return;
            }
        }

        $validationMessages = [
            'Plan no encontrado',
            'Plan destino no encontrado',
            'Tu perfil todavía no está habilitado',
            'Los upgrades deben aplicarse de inmediato',
            'Los downgrades deben programarse',
            'Falta preference_id o external_reference',
            'Inmobiliaria no vinculada',
            'La inmobiliaria debe estar aprobada',
            'No hay una membresía activa para operar este cambio',
        ];

        foreach (
            $validationMessages as $fragment
        ) {
            if (
                str_contains(
                    $message,
                    $fragment
                )
            ) {
                ResponseHelper::fail(
                    $message,
                    422
                );

                return;
            }
        }

        if ($message === 'Usuario inválido') {
            ResponseHelper::fail(
                'No autorizado',
                403
            );

            return;
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación de facturación.',
            'BillingController'
        );
    }

    private static function readJsonObject(): array
    {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            $raw === false ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío',
                422
            );
        }

        if (strlen($raw) > 65536) {
            throw new \Exception(
                'El cuerpo de la solicitud es demasiado extenso',
                413
            );
        }

        $raw =
            trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto',
                422
            );
        }

        try {
            $payload =
                json_decode(
                    $raw,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        if (!is_array($payload)) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        return $payload;
    }

    private static function rejectUnknownFields(
        array $payload,
        array $allowedFields
    ): void {
        $unknownFields =
            array_diff(
                array_keys($payload),
                $allowedFields
            );

        if ($unknownFields !== []) {
            throw new \Exception(
                'La solicitud contiene campos no permitidos',
                422
            );
        }
    }

    private static function requiredString(
        array $payload,
        string $field,
        int $maxLength = 100
    ): string {
        if (
            !array_key_exists(
                $field,
                $payload
            ) ||
            !is_string(
                $payload[$field]
            )
        ) {
            throw new \Exception(
                $field . ' requerido',
                422
            );
        }

        $value =
            trim($payload[$field]);

        if ($value === '') {
            throw new \Exception(
                $field . ' requerido',
                422
            );
        }

        if (
            mb_strlen($value) >
            $maxLength
        ) {
            throw new \Exception(
                $field . ' es demasiado extenso',
                422
            );
        }

        return $value;
    }

    public static function listPlans(): void
    {
        try {
            QueryParamHelper::rejectUnknown([]);

            $plans =
                BillingService::listPlans();

            ResponseHelper::ok([
                'plans' => $plans,
            ]);
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function createPreference(): void
    {
        try {
            $ctx =
                AuthMiddleware::handle();

            if (
                (int)($ctx['role'] ?? 0)
                !== 2
            ) {
                throw new \Exception(
                    'No autorizado',
                    403
                );
            }

            QueryParamHelper::rejectUnknown([]);

            $payload =
                self::readJsonObject();

            self::rejectUnknownFields(
                $payload,
                [
                    'plan_code',
                ]
            );

            $planCode =
                self::requiredString(
                    $payload,
                    'plan_code',
                    100
                );

            $result =
                BillingService::createPreference(
                    (int)$ctx['id'],
                    $planCode
                );

            ResponseHelper::ok(
                $result,
                201
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function status(): void
    {
        try {
            $ctx =
                AuthMiddleware::handle();

            QueryParamHelper::rejectUnknown([
                'preference_id',
                'external_reference',
            ]);

            $preferenceId =
                QueryParamHelper::optionalString(
                    'preference_id',
                    255
                );

            $externalReference =
                QueryParamHelper::optionalString(
                    'external_reference',
                    255
                );

            if (
                $preferenceId === null &&
                $externalReference === null
            ) {
                throw new \Exception(
                    'Debés enviar preference_id o external_reference',
                    422
                );
            }

            if (
                $preferenceId !== null &&
                $externalReference !== null
            ) {
                throw new \Exception(
                    'Enviá solamente preference_id o external_reference',
                    422
                );
            }

            $data =
                BillingService::getPaymentStatus(
                    (int)$ctx['id'],
                    $preferenceId,
                    $externalReference
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function previewPlanChange(): void
    {
        try {
            $ctx =
                AuthMiddleware::handle();

            if (
                (int)($ctx['role'] ?? 0)
                !== 2
            ) {
                throw new \Exception(
                    'No autorizado',
                    403
                );
            }

            QueryParamHelper::rejectUnknown([]);

            $payload =
                self::readJsonObject();

            self::rejectUnknownFields(
                $payload,
                [
                    'target_plan_code',
                ]
            );

            $planCode =
                self::requiredString(
                    $payload,
                    'target_plan_code',
                    100
                );

            $result =
                BillingService::previewPlanChange(
                    (int)$ctx['id'],
                    $planCode
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function confirmPlanChange(): void
    {
        try {
            $ctx =
                AuthMiddleware::handle();

            if (
                (int)($ctx['role'] ?? 0)
                !== 2
            ) {
                throw new \Exception(
                    'No autorizado',
                    403
                );
            }

            QueryParamHelper::rejectUnknown([]);

            $payload =
                self::readJsonObject();

            self::rejectUnknownFields(
                $payload,
                [
                    'target_plan_code',
                    'mode',
                ]
            );

            $planCode =
                self::requiredString(
                    $payload,
                    'target_plan_code',
                    100
                );

            $mode =
                self::requiredString(
                    $payload,
                    'mode',
                    30
                );

            if (
                !in_array(
                    $mode,
                    [
                        'immediate',
                        'next_cycle',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'mode inválido',
                    422
                );
            }

            $result =
                BillingService::confirmPlanChange(
                    (int)$ctx['id'],
                    $planCode,
                    $mode
                );

            ResponseHelper::ok(
                $result,
                201
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }

    public static function cancelMembership(): void
    {
        try {
            $ctx =
                AuthMiddleware::handle();

            if (
                (int)($ctx['role'] ?? 0)
                !== 2
            ) {
                throw new \Exception(
                    'No autorizado',
                    403
                );
            }

            QueryParamHelper::rejectUnknown([]);

            $payload =
                self::readJsonObject();

            self::rejectUnknownFields(
                $payload,
                []
            );

            $result =
                BillingService::cancelMembership(
                    (int)$ctx['id']
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::error($e);
        }
    }
}
