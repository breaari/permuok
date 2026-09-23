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
                self::readJsonObject();

            $allowedFields = [
                'name',
                'legal_name',
                'cuit',
                'address',
                'address_place_id',
                'address_lat',
                'address_lng',
                'address_locality',
                'address_province',
                'address_postal_code',
                'phone',
                'email',
                'website',
                'instagram',
                'facebook',
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

            $textFields = [
                'name',
                'legal_name',
                'cuit',
                'address',
                'address_place_id',
                'address_locality',
                'address_province',
                'address_postal_code',
                'phone',
                'email',
                'website',
                'instagram',
                'facebook',
            ];

            foreach ($textFields as $field) {
                if (
                    !array_key_exists($field, $payload)
                ) {
                    continue;
                }

                if (
                    $payload[$field] !== null &&
                    !is_string($payload[$field])
                ) {
                    throw new \Exception(
                        "El campo {$field} tiene un formato inválido.",
                        422
                    );
                }
            }

            foreach (
                [
                    'address_lat',
                    'address_lng',
                ] as $field
            ) {
                if (
                    !array_key_exists($field, $payload)
                ) {
                    continue;
                }

                $value = $payload[$field];

                if (
                    $value !== null &&
                    $value !== '' &&
                    !is_int($value) &&
                    !is_float($value) &&
                    !(
                        is_string($value) &&
                        is_numeric($value)
                    )
                ) {
                    throw new \Exception(
                        'Seleccioná una dirección válida desde Google Maps.',
                        422
                    );
                }
            }

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
                self::readJsonObject();

            $allowedFields = [
                'license_number',
                'province_id',
                'is_primary',
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

            $licenseNumber =
                $payload['license_number']
                ?? null;

            if (!is_string($licenseNumber)) {
                throw new \Exception(
                    'Ingresá un número de matrícula válido.',
                    422
                );
            }

            $licenseNumber =
                trim($licenseNumber);

            $licenseLength =
                function_exists('mb_strlen')
                ? mb_strlen(
                    $licenseNumber,
                    'UTF-8'
                )
                : strlen($licenseNumber);

            if (
                $licenseLength < 1 ||
                $licenseLength > 100
            ) {
                throw new \Exception(
                    'La matrícula debe tener entre 1 y 100 caracteres.',
                    422
                );
            }

            $provinceValue =
                $payload['province_id']
                ?? null;

            if (
                !is_int($provinceValue) &&
                !(
                    is_string($provinceValue) &&
                    preg_match(
                        '/^[1-9][0-9]*$/',
                        $provinceValue
                    ) === 1
                )
            ) {
                throw new \Exception(
                    'Seleccioná una provincia válida.',
                    422
                );
            }

            $provinceId =
                (int)$provinceValue;

            if ($provinceId <= 0) {
                throw new \Exception(
                    'Seleccioná una provincia válida.',
                    422
                );
            }

            $rawIsPrimary =
                $payload['is_primary']
                ?? false;

            if (
                !in_array(
                    $rawIsPrimary,
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
                    'is_primary debe ser 0 o 1.',
                    422
                );
            }

            $result =
                RealEstateService::addLicense(
                    (int)$ctx['id'],
                    [
                        'license_number' =>
                        $licenseNumber,

                        'province_id' =>
                        $provinceId,

                        'is_primary' =>
                        in_array(
                            $rawIsPrimary,
                            [
                                1,
                                true,
                                '1',
                            ],
                            true
                        ),
                    ]
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
