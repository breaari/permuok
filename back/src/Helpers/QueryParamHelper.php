<?php

namespace App\Helpers;

class QueryParamHelper
{
    public static function rejectUnknown(
        array $allowed
    ): void {
        $unknown =
            array_diff(
                array_keys($_GET),
                $allowed
            );

        if ($unknown !== []) {
            throw new \Exception(
                'La consulta contiene parámetros no permitidos.',
                422
            );
        }
    }

    public static function optionalString(
        string $name,
        int $maximumLength = 150
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

        if ($length > $maximumLength) {
            throw new \Exception(
                "El parámetro {$name} es demasiado extenso.",
                422
            );
        }

        return $value;
    }

    public static function enum(
        string $name,
        array $allowed,
        string $default
    ): string {
        $value =
            self::optionalString(
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

    public static function positiveInt(
        string $name,
        int $default,
        int $maximum = 1000000
    ): int {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            return $default;
        }

        return self::parsePositiveInt(
            $_GET[$name],
            $name,
            $maximum
        );
    }

    public static function requiredPositiveInt(
        string $name,
        int $maximum = PHP_INT_MAX
    ): int {
        if (
            !array_key_exists(
                $name,
                $_GET
            )
        ) {
            throw new \Exception(
                "El parámetro {$name} es requerido.",
                422
            );
        }

        return self::parsePositiveInt(
            $_GET[$name],
            $name,
            $maximum
        );
    }

    public static function requiredPositiveIntFromAliases(
        array $names,
        string $label,
        int $maximum = PHP_INT_MAX
    ): int {
        $values = [];

        foreach ($names as $name) {
            if (
                !array_key_exists(
                    $name,
                    $_GET
                )
            ) {
                continue;
            }

            $values[] =
                self::parsePositiveInt(
                    $_GET[$name],
                    $name,
                    $maximum
                );
        }

        if ($values === []) {
            throw new \Exception(
                "El parámetro {$label} es requerido.",
                422
            );
        }

        $uniqueValues =
            array_values(
                array_unique(
                    $values
                )
            );

        if (count($uniqueValues) !== 1) {
            throw new \Exception(
                "El parámetro {$label} es ambiguo.",
                422
            );
        }

        return $uniqueValues[0];
    }

    private static function parsePositiveInt(
        $value,
        string $name,
        int $maximum
    ): int {
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
}