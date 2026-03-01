<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

use App\Controllers\AuthController;
use App\Controllers\MeController;
use App\Controllers\RefreshController;
use App\Controllers\LogoutController;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|--------------------------------------------------------------------------
| Ajuste basePath: /permuok/public/me -> /me
|--------------------------------------------------------------------------
*/
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // /permuok/public
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = $uri === '' ? '/' : $uri;

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
*/
if ($method === 'POST' && $uri === '/auth/register') {
    AuthController::register();
    exit;
}

if ($method === 'POST' && $uri === '/auth/login') {
    AuthController::login();
    exit;
}

if ($method === 'GET' && $uri === '/me') {
    MeController::handle();
    exit;
}

if ($method === 'POST' && $uri === '/refresh') {
    RefreshController::handle();
    exit;
}

if ($method === 'POST' && $uri === '/logout') {
    LogoutController::handle();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);