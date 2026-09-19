<?php

namespace App\Helpers;

use Throwable;

class ResponseHelper
{
    public static function ok(
        $data = null,
        int $status = 200
    ): void {
        http_response_code($status);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode([
            'success' => true,
            'status' => $status,
            'data' => $data,
        ]);

        exit;
    }

    public static function fail(
        string $message,
        int $status = 400,
        $errors = null
    ): void {
        http_response_code($status);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ]);

        exit;
    }

    public static function error(
        string $message,
        int $status = 400,
        $errors = null
    ): void {
        self::fail(
            $message,
            $status,
            $errors
        );
    }

    public static function fromThrowable(
        Throwable $e,
        string $fallbackMessage =
        'No se pudo procesar la solicitud.',
        string $context =
        'Application'
    ): void {
        $status =
            (int)$e->getCode();

        /*
         * Los errores 4xx representan validaciones
         * o reglas de negocio que el usuario puede
         * corregir y cuyo mensaje sí es seguro.
         */
        if (
            $status >= 400 &&
            $status <= 499
        ) {
            self::fail(
                $e->getMessage()
                    ?: $fallbackMessage,
                $status
            );

            return;
        }

        /*
         * Los errores internos se registran
         * únicamente en el servidor.
         */
        error_log(
            '[' . $context . '] ' .
                $e->getMessage() .
                ' in ' .
                $e->getFile() .
                ':' .
                $e->getLine()
        );

        self::fail(
            $fallbackMessage,
            500
        );
    }
}
