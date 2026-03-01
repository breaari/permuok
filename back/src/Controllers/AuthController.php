<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AuthService;

class AuthController
{
    public static function register(): void
    {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $result = AuthService::register($data);

        if (isset($result['error'])) {
            ResponseHelper::fail($result['error'], 422);
        }

        ResponseHelper::ok(['message' => 'Usuario creado'], 201);
    }

    public static function login(): void
    {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            ResponseHelper::fail('Email y password son requeridos', 422);
        }

        $result = AuthService::login(trim($email), $password);

        // AuthService puede devolver false (credenciales inválidas) o ['error'=>...]
        if ($result === false) {
            ResponseHelper::fail('Credenciales inválidas', 401);
        }

        if (is_array($result) && isset($result['error'])) {
            ResponseHelper::fail($result['error'], 403);
        }

        ResponseHelper::ok($result);
    }
}