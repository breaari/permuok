<?php

namespace App\Services;

class PasswordPolicyService
{
    public static function validationError(
        string $password
    ): ?string {
        $length = strlen($password);

        if (
            $length < 8 ||
            $length > 72
        ) {
            return
                'La contraseña debe tener entre 8 y 72 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return
                'La contraseña debe incluir al menos una mayúscula.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return
                'La contraseña debe incluir al menos una minúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return
                'La contraseña debe incluir al menos un número.';
        }

        return null;
    }
}