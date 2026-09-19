<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\RealEstateService;
use PDOException;
use Throwable;

class RealEstateController
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
    private static function handleError(
        Throwable $e
    ): void {
        /*
     * Nunca exponemos consultas ni detalles
     * internos de la base de datos.
     */
        if ($e instanceof PDOException) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'RealEstateController'
            );

            return;
        }

        $message =
            trim(
                $e->getMessage()
            );

        $code =
            (int)$e->getCode();

        /*
     * Conserva los códigos funcionales
     * definidos explícitamente en el servicio.
     */
        if (
            $code >= 400 &&
            $code <= 499
        ) {
            ResponseHelper::fail(
                $message,
                $code
            );

            return;
        }

        if ($message === 'No autorizado') {
            ResponseHelper::fail(
                $message,
                403
            );

            return;
        }

        if (
            $message ===
            'Usuario no encontrado' ||
            $message ===
            'Inmobiliaria no encontrada'
        ) {
            ResponseHelper::fail(
                $message,
                404
            );

            return;
        }

        if (
            str_starts_with(
                $message,
                'Ya existe una inmobiliaria'
            ) ||
            str_starts_with(
                $message,
                'Esa matrícula ya existe'
            )
        ) {
            ResponseHelper::fail(
                $message,
                409
            );

            return;
        }

        $validationPrefixes = [
            'Falta ',
            'Ingresá ',
            'Seleccioná ',
            'Primero completá ',
            'Tenés que ',
            'license_number ',
            'is_primary ',
        ];

        foreach (
            $validationPrefixes
            as $prefix
        ) {
            if (
                str_starts_with(
                    $message,
                    $prefix
                )
            ) {
                ResponseHelper::fail(
                    $message,
                    422
                );

                return;
            }
        }

        if (
            $message ===
            'Provincia inválida o inactiva'
        ) {
            ResponseHelper::fail(
                $message,
                422
            );

            return;
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación.',
            'RealEstateController'
        );
    }

    public static function me(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $data =
                RealEstateService::getMyRealEstate(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($data);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function saveProfile(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $result =
                RealEstateService::saveProfile(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function addLicense(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                ) ?? [];

            $result =
                RealEstateService::addLicense(
                    (int)$ctx['id'],
                    $payload
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    public static function submitReview(): void
    {
        try {
            $ctx =
                self::requireRealEstate();

            $result =
                RealEstateService::submitReview(
                    (int)$ctx['id']
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }
}
