<?php

namespace App\Helpers;

class ResponseHelper
{
    public static function ok($data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => $status,
            'data' => $data
        ]);
        exit;
    }

    public static function fail(string $message, int $status = 400, $errors = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'errors' => $errors
        ]);
        exit;
    }
}