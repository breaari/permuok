<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\ExploreService;

class ExploreController
{
    private static function queryOptionalString(
        string $name,
        int $maxLength
    ): ?string {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return null;
        }

        $value =
            $_GET[$name];

        if (!is_string($value)) {
            throw new \Exception(
                "El parámetro {$name} tiene un formato inválido.",
                422
            );
        }

        $value =
            trim($value);

        if ($value === '') {
            return null;
        }

        $length =
            function_exists('mb_strlen')
            ? mb_strlen(
                $value,
                'UTF-8'
            )
            : strlen($value);

        if ($length > $maxLength) {
            throw new \Exception(
                "El parámetro {$name} es demasiado extenso.",
                422
            );
        }

        return $value;
    }

    private static function queryEnum(
        string $name,
        array $allowed,
        ?string $default = null
    ): ?string {
        $value =
            self::queryOptionalString(
                $name,
                50
            );

        if ($value === null) {
            return $default;
        }

        if (
            !in_array(
                $value,
                $allowed,
                true
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} no es válido.",
                422
            );
        }

        return $value;
    }

    private static function queryPositiveInt(
        string $name,
        int $default,
        int $maximum
    ): int {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return $default;
        }

        $value =
            $_GET[$name];

        if (
            !is_int($value) &&
            !(
                is_string($value) &&
                preg_match(
                    '/^[1-9][0-9]*$/',
                    $value
                ) === 1
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} debe ser un entero positivo.",
                422
            );
        }

        $normalized =
            (int)$value;

        if (
            $normalized < 1 ||
            $normalized > $maximum
        ) {
            throw new \Exception(
                "El parámetro {$name} está fuera del rango permitido.",
                422
            );
        }

        return $normalized;
    }

    private static function queryOptionalInt(
        string $name,
        int $maximum
    ): ?int {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return null;
        }

        $value =
            $_GET[$name];

        if (
            $value === '' ||
            $value === null
        ) {
            return null;
        }

        if (
            !is_int($value) &&
            !(
                is_string($value) &&
                preg_match(
                    '/^[0-9]+$/',
                    $value
                ) === 1
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} debe ser un entero válido.",
                422
            );
        }

        $normalized =
            (int)$value;

        if (
            $normalized < 0 ||
            $normalized > $maximum
        ) {
            throw new \Exception(
                "El parámetro {$name} está fuera del rango permitido.",
                422
            );
        }

        return $normalized;
    }

    private static function queryOptionalNumber(
        string $name,
        float $minimum,
        float $maximum
    ): ?float {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return null;
        }

        $value =
            $_GET[$name];

        if (
            $value === '' ||
            $value === null
        ) {
            return null;
        }

        if (
            !is_int($value) &&
            !is_float($value) &&
            !(
                is_string($value) &&
                is_numeric($value)
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} debe ser numérico.",
                422
            );
        }

        $normalized =
            (float)$value;

        if (
            !is_finite($normalized) ||
            $normalized < $minimum ||
            $normalized > $maximum
        ) {
            throw new \Exception(
                "El parámetro {$name} está fuera del rango permitido.",
                422
            );
        }

        return $normalized;
    }

    private static function queryCsvEnum(
        string $name,
        array $allowed,
        int $maximumItems
    ): array {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return [];
        }

        $value =
            $_GET[$name];

        if (!is_string($value)) {
            throw new \Exception(
                "El parámetro {$name} tiene un formato inválido.",
                422
            );
        }

        $value =
            trim($value);

        if ($value === '') {
            return [];
        }

        if (strlen($value) > 1000) {
            throw new \Exception(
                "El parámetro {$name} es demasiado extenso.",
                422
            );
        }

        $items =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn($item) =>
                            trim($item),
                            explode(
                                ',',
                                $value
                            )
                        ),
                        static fn($item) =>
                        $item !== ''
                    )
                )
            );

        if (
            count($items) >
            $maximumItems
        ) {
            throw new \Exception(
                "El parámetro {$name} contiene demasiados valores.",
                422
            );
        }

        foreach ($items as $item) {
            if (
                !in_array(
                    $item,
                    $allowed,
                    true
                )
            ) {
                throw new \Exception(
                    "El parámetro {$name} contiene un valor inválido.",
                    422
                );
            }
        }

        return $items;
    }

    public static function index(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $allowedQueryFields = [
                'q',
                'opportunity_type',
                'country',
                'province',
                'city',
                'zone',
                'place_id',
                'lat',
                'lng',
                'property_type',
                'currency',
                'value_min',
                'value_max',
                'bedrooms_min',
                'bathrooms_min',
                'garages_min',
                'area_min',
                'exchange_modes',
                'amenities',
                'development_stage',
                'sort',
                'page',
                'limit',
            ];

            $unknownFields =
                array_diff(
                    array_keys($_GET),
                    $allowedQueryFields
                );

            if ($unknownFields !== []) {
                throw new \Exception(
                    'La consulta contiene filtros no permitidos.',
                    422
                );
            }

            $valueMin =
                self::queryOptionalNumber(
                    'value_min',
                    0,
                    999999999999999
                );

            $valueMax =
                self::queryOptionalNumber(
                    'value_max',
                    0,
                    999999999999999
                );

            if (
                $valueMin !== null &&
                $valueMax !== null &&
                $valueMin > $valueMax
            ) {
                throw new \Exception(
                    'El valor mínimo no puede superar al valor máximo.',
                    422
                );
            }

            $latitude =
                self::queryOptionalNumber(
                    'lat',
                    -90,
                    90
                );

            $longitude =
                self::queryOptionalNumber(
                    'lng',
                    -180,
                    180
                );

            if (
                ($latitude === null) !==
                ($longitude === null)
            ) {
                throw new \Exception(
                    'La ubicación debe incluir latitud y longitud.',
                    422
                );
            }

            $filters = [
                'q' =>
                self::queryOptionalString(
                    'q',
                    150
                ),

                'opportunity_type' =>
                self::queryEnum(
                    'opportunity_type',
                    [
                        'all',
                        'property',
                        'search_request',
                        'development',
                    ],
                    'all'
                ),

                'country' =>
                self::queryOptionalString(
                    'country',
                    100
                ),

                'province' =>
                self::queryOptionalString(
                    'province',
                    100
                ),

                'city' =>
                self::queryOptionalString(
                    'city',
                    100
                ),

                'zone' =>
                self::queryOptionalString(
                    'zone',
                    100
                ),

                'place_id' =>
                self::queryOptionalString(
                    'place_id',
                    255
                ),

                'lat' =>
                $latitude,

                'lng' =>
                $longitude,

                'property_type' =>
                self::queryEnum(
                    'property_type',
                    [
                        'apartment',
                        'house',
                        'land',
                        'commercial',
                        'office',
                        'warehouse',
                        'garage',
                        'other',
                    ]
                ),

                'currency' =>
                self::queryEnum(
                    'currency',
                    [
                        'USD',
                        'ARS',
                    ]
                ),

                'value_min' =>
                $valueMin,

                'value_max' =>
                $valueMax,

                'bedrooms_min' =>
                self::queryOptionalInt(
                    'bedrooms_min',
                    1000
                ),

                'bathrooms_min' =>
                self::queryOptionalInt(
                    'bathrooms_min',
                    1000
                ),

                'garages_min' =>
                self::queryOptionalInt(
                    'garages_min',
                    1000
                ),

                'area_min' =>
                self::queryOptionalNumber(
                    'area_min',
                    0,
                    1000000000
                ),

                'exchange_modes' =>
                self::queryCsvEnum(
                    'exchange_modes',
                    [
                        'total_swap',
                        'swap_plus_cash',
                        'multiple_swap',
                        'open_proposals',
                        'cash',
                    ],
                    5
                ),

                'amenities' =>
                self::queryCsvEnum(
                    'amenities',
                    [
                        'balcony',
                        'patio',
                        'terrace',
                        'pool',
                        'quincho',
                        'garden',
                        'barbecue',
                        'sum',
                        'gym',
                        'security',
                        'doorman',
                        'laundry',
                        'elevator',
                        'garage',
                        'storage',
                        'green_area',
                        'cowork',
                        'kids_area',
                        'pet_friendly',
                        'rooftop',
                        'jacuzzi',
                    ],
                    21
                ),

                'development_stage' =>
                self::queryEnum(
                    'development_stage',
                    [
                        'land',
                        'prelaunch',
                        'launch',
                        'presale',
                        'under_construction',
                        'finished',
                    ]
                ),

                'sort' =>
                self::queryEnum(
                    'sort',
                    [
                        'recent',
                        'value_asc',
                        'value_desc',
                    ],
                    'recent'
                ),

                'page' =>
                self::queryPositiveInt(
                    'page',
                    1,
                    1000000
                ),

                'limit' =>
                self::queryPositiveInt(
                    'limit',
                    12,
                    50
                ),
            ];

            $result =
                ExploreService::search(
                    (int)$auth['id'],
                    $filters
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo cargar la exploración.',
                'ExploreController::index'
            );
        }
    }
}
