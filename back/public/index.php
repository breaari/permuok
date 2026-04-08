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
    'permuok.com',
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
use App\Controllers\AdminUserController;
use App\Controllers\AdminBillingController;
use App\Controllers\PropertyController;
use App\Controllers\PropertyImageController;
use App\Controllers\PropertyImageViewController;
use App\Controllers\SearchRequestController;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Normalizar slashes
$uri = preg_replace('#/+#', '/', $uri);

// scriptDir (por ejemplo: /public)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// Detectar el "mount" (primer segmento de la URL), ej: "permuok"
$parts = explode('/', trim($uri, '/'));
$mount = $parts[0] ?? '';

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

    // Admin real estates
    'GET /admin/real-estates/counts'    => [AdminRealEstateController::class, 'counts'],
    'GET /admin/real-estates'           => [AdminRealEstateController::class, 'list'],
    'POST /admin/real-estates/validate' => [AdminRealEstateController::class, 'validate'],

    // Legacy
    'POST /admin/real-estates/approve' => [AdminRealEstateController::class, 'approve'],
    'GET /admin/real-estates/pending'  => [AdminRealEstateController::class, 'pending'],
    'GET /admin/real-estates/approved' => [AdminRealEstateController::class, 'approved'],
    'GET /admin/real-estates/rejected' => [AdminRealEstateController::class, 'rejected'],

    // Billing
    'GET /plans'                        => [BillingController::class, 'listPlans'],
    'POST /billing/create-preference'   => [BillingController::class, 'createPreference'],
    'GET /billing/status'               => [BillingController::class, 'status'],
    'POST /webhooks/mercadopago'        => [WebhookMercadoPagoController::class, 'handle'],
    'POST /dev/billing/approve'         => [DevBillingController::class, 'approve'],
    'POST /billing/change-plan/preview' => [BillingController::class, 'previewPlanChange'],
    'POST /billing/change-plan/confirm' => [BillingController::class, 'confirmPlanChange'],
    'POST /billing/cancel'              => [BillingController::class, 'cancelMembership'],
    'POST /dev/billing/process-cycle'   => [BillingCycleController::class, 'process'],

    // Provinces
    'GET /locations/provinces' => [ProvinceController::class, 'list'],

    // Users
    'GET /users'          => [UserController::class, 'list'],
    'POST /users'         => [UserController::class, 'create'],
    'PATCH /users/status' => [UserController::class, 'updateStatus'],

    // Admin users
    'GET /admin/users/counts'  => [AdminUserController::class, 'counts'],
    'GET /admin/users'         => [AdminUserController::class, 'list'],
    'POST /admin/users/status' => [AdminUserController::class, 'updateStatus'],

    // Admin billing
    'GET /admin/billing/counts' => [AdminBillingController::class, 'counts'],
    'GET /admin/billing'        => [AdminBillingController::class, 'list'],

    // Properties - propias
    'GET /properties'  => [PropertyController::class, 'list'],
    'POST /properties' => [PropertyController::class, 'create'],

    // Properties - explorar
    'GET /explore/properties' => [PropertyController::class, 'explore'],

    // Search requests - propias
    'GET /search-requests'  => [SearchRequestController::class, 'list'],
    'POST /search-requests' => [SearchRequestController::class, 'create'],

    // Search requests - explorar
    'GET /explore/search-requests' => [SearchRequestController::class, 'explore'],
];

$key = $method . ' ' . $uri;

if (isset($routes[$key])) {
    [$cls, $fn] = $routes[$key];
    $cls::$fn();
    exit;
}

// Dynamic routes (regex)

if ($method === 'GET' && preg_match('#^/admin/real-estates/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    AdminRealEstateController::detail();
    exit;
}

if ($method === 'GET' && preg_match('#^/admin/users/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    AdminUserController::detail();
    exit;
}

if ($method === 'GET' && preg_match('#^/properties/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::detail();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/properties/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::update();
    exit;
}

if ($method === 'PUT' && preg_match('#^/properties/(\d+)/requirements$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::saveRequirements();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/publish$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::publish();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/pause$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::pause();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/archive$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::archive();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/close$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::close();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/images$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyImageController::upload();
    exit;
}

if ($method === 'DELETE' && preg_match('#^/properties/images/(\d+)$#', $uri, $m)) {
    $_GET['image_id'] = (int)$m[1];
    PropertyImageController::delete();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/properties/(\d+)/images/reorder$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyImageController::reorder();
    exit;
}

if ($method === 'GET' && preg_match('#^/property-images/(\d+)/view$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyImageViewController::show();
    exit;
}

if ($method === 'POST' && preg_match('#^/properties/(\d+)/delete$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::delete();
    exit;
}

// Search requests dynamic routes

if ($method === 'GET' && preg_match('#^/search-requests/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::detail();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/search-requests/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::update();
    exit;
}

if ($method === 'POST' && preg_match('#^/search-requests/(\d+)/publish$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::publish();
    exit;
}

if ($method === 'POST' && preg_match('#^/search-requests/(\d+)/pause$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::pause();
    exit;
}

if ($method === 'POST' && preg_match('#^/search-requests/(\d+)/archive$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::archive();
    exit;
}

if ($method === 'POST' && preg_match('#^/search-requests/(\d+)/delete$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::delete();
    exit;
}

if ($method === 'GET' && preg_match('#^/explore/properties/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    PropertyController::exploreDetail();
    exit;
}

if ($method === 'GET' && preg_match('#^/explore/search-requests/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    SearchRequestController::exploreDetail();
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);