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
use App\Controllers\RealEstateController;
use App\Controllers\AdminRealEstateController;
use App\Controllers\BillingController;
use App\Controllers\WebhookMercadoPagoController;
use App\Controllers\DevBillingController;



$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Normalizar slashes
$uri = preg_replace('#/+#', '/', $uri);

// scriptDir (por ejemplo: /public)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // "/public"

// Detectar el "mount" (primer segmento de la URL), ej: "permuok"
$parts = explode('/', trim($uri, '/'));
$mount = $parts[0] ?? ''; // "permuok" o ""

$baseCandidates = [];
if ($scriptDir !== '') {
    // Caso normal: /public
    $baseCandidates[] = $scriptDir;

    // Caso con mount: /permuok/public
    if ($mount !== '') {
        $baseCandidates[] = '/' . $mount . $scriptDir;
    }
}

// Recortar el primer basePath que matchee
foreach ($baseCandidates as $base) {
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
        break;
    }
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

if ($method === 'GET' && $uri === '/real-estate/me') {
    RealEstateController::me();
    exit;
}

if ($method === 'POST' && $uri === '/real-estate/profile') {
    RealEstateController::saveProfile();
    exit;
}

if ($method === 'POST' && $uri === '/real-estate/licenses') {
    RealEstateController::addLicense();
    exit;
}

if ($method === 'POST' && $uri === '/real-estate/submit-review') {
    RealEstateController::submitReview();
    exit;
}

if ($method === 'POST' && $uri === '/admin/real-estates/approve') {
    AdminRealEstateController::approve();
    exit;
}

if ($method === 'GET' && $uri === '/admin/real-estates/pending') {
    AdminRealEstateController::pending();
    exit;
}

if ($method === 'GET' && $uri === '/admin/real-estates/rejected') {
    AdminRealEstateController::rejected();
    exit;
}

if ($method === 'GET' && $uri === '/admin/real-estates/approved') {
    AdminRealEstateController::approved();
    exit;
}


if ($method === 'GET' && $uri === '/plans') {
    BillingController::listPlans();
    exit;
}

if ($method === 'POST' && $uri === '/billing/create-preference') {
    BillingController::createPreference();
    exit;
}

if ($method === 'GET' && $uri === '/billing/status') {
    BillingController::status();
    exit;
}

if ($method === 'POST' && $uri === '/webhooks/mercadopago') {
    WebhookMercadoPagoController::handle();
    exit;
}
if ($method === 'POST' && $uri === '/dev/billing/approve') {
    DevBillingController::approve();
    exit;
}


http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);

