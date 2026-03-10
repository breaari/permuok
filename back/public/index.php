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
use App\Controllers\ProvinceController;
use App\Controllers\BillingCycleController;
use App\Controllers\UserController;



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
$routes = [
    'POST /auth/register' => [AuthController::class, 'register'],
    'POST /auth/login'    => [AuthController::class, 'login'],
    'GET /me'             => [MeController::class, 'handle'],
    'POST /refresh'       => [RefreshController::class, 'handle'],
    'POST /logout'        => [LogoutController::class, 'handle'],

    'GET /real-estate/me'             => [RealEstateController::class, 'me'],
    'POST /real-estate/profile'       => [RealEstateController::class, 'saveProfile'],
    'POST /real-estate/licenses'      => [RealEstateController::class, 'addLicense'],
    'POST /real-estate/submit-review' => [RealEstateController::class, 'submitReview'],

    // Admin nuevo
    'GET /admin/real-estates/counts'    => [AdminRealEstateController::class, 'counts'],
    'GET /admin/real-estates'           => [AdminRealEstateController::class, 'list'],
    // 'GET /admin/real-estates/detail'    => [AdminRealEstateController::class, 'detail'],
    'POST /admin/real-estates/validate' => [AdminRealEstateController::class, 'validate'],

    // Legacy (si querés mantenerlos)
    'POST /admin/real-estates/approve' => [AdminRealEstateController::class, 'approve'],
    'GET /admin/real-estates/pending'  => [AdminRealEstateController::class, 'pending'],
    'GET /admin/real-estates/approved' => [AdminRealEstateController::class, 'approved'],
    'GET /admin/real-estates/rejected' => [AdminRealEstateController::class, 'rejected'],

    'GET /plans'                      => [BillingController::class, 'listPlans'],
    'POST /billing/create-preference' => [BillingController::class, 'createPreference'],
    'GET /billing/status'             => [BillingController::class, 'status'],
    'POST /webhooks/mercadopago'      => [WebhookMercadoPagoController::class, 'handle'],
    'POST /dev/billing/approve'       => [DevBillingController::class, 'approve'],

    'GET /provinces' => [ProvinceController::class, 'list'],

    'POST /billing/change-plan/preview' => [BillingController::class, 'previewPlanChange'],
    'POST /billing/change-plan/confirm' => [BillingController::class, 'confirmPlanChange'],
    'POST /billing/cancel'              => [BillingController::class, 'cancelMembership'],
    'POST /dev/billing/process-cycle' => [BillingCycleController::class, 'process'],

    'GET /users'          => [UserController::class, 'list'],
    'POST /users'         => [UserController::class, 'create'],
    'PATCH /users/status' => [UserController::class, 'updateStatus'],
];

$key = $method . ' ' . $uri;

if (isset($routes[$key])) {
    [$cls, $fn] = $routes[$key];
    $cls::$fn();
    exit;
}

// Dynamic routes (regex)
if ($method === 'GET' && preg_match('#^/admin/real-estates/(\d+)$#', $uri, $m)) {
    // Reutilizamos el mismo handler, pasando id por $_GET para no reescribir controller
    $_GET['id'] = (int)$m[1];
    AdminRealEstateController::detail();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);
