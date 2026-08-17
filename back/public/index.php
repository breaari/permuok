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
    'https://permuok.com',
    'http://permuok.com',
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
use App\Controllers\DevelopmentController;
use App\Controllers\DevelopmentImageController;
use App\Controllers\DevelopmentImageViewController;
use App\Controllers\DevelopmentUnitTypeController;
use App\Controllers\DevelopmentAmenityController;
use App\Controllers\ExploreController;
use App\Controllers\ConversationController;
use App\Controllers\NotificationController;
use App\Controllers\RealtimeController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AiEnrichmentController;
use App\Controllers\AiCompatibilityController;
use App\Controllers\CompatibilityController;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

$isImageRequest =
    preg_match('#^/property-images/\d+/view$#', $uri) ||
    preg_match('#^/development-images/\d+/view$#', $uri);

if (!$isImageRequest) {
    header('Content-Type: application/json; charset=utf-8');
}

$uri = preg_replace('#/+#', '/', $uri);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = rtrim(dirname($scriptName), '/');

/*
|--------------------------------------------------------------------------
| Normalización de URI
|--------------------------------------------------------------------------
| Ejemplos:
| /permuok/public/auth/login      => /auth/login
| /permuok/public/explore         => /explore
| /permuok/public/index.php/login => /login
|--------------------------------------------------------------------------
*/
if ($scriptName && str_starts_with($uri, $scriptName)) {
    $uri = substr($uri, strlen($scriptName));
} elseif ($scriptDir && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir));
} else {
    $publicPos = strpos($uri, '/public/');
    if ($publicPos !== false) {
        $uri = substr($uri, $publicPos + strlen('/public'));
    } elseif (str_ends_with($uri, '/public')) {
        $uri = '/';
    }
}

$uri = '/' . ltrim($uri, '/');
$uri = preg_replace('#/+#', '/', $uri);
$uri = rtrim($uri, '/');
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

    // Explore unificado
    'GET /explore' => [ExploreController::class, 'index'],

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

    // Developments - propios
    'GET /developments'  => [DevelopmentController::class, 'list'],
    'POST /developments' => [DevelopmentController::class, 'create'],

    // Developments - explorar
    'GET /explore/developments' => [DevelopmentController::class, 'explore'],

    // Conversations
    'GET /conversations'        => [ConversationController::class, 'index'],
    'POST /conversations/start' => [ConversationController::class, 'start'],
    'GET /conversations/unread-count' => [ConversationController::class, 'unreadCount'],

    // Notifications
    'GET /notifications' => [NotificationController::class, 'index'],
    'GET /notifications/unread-count' => [NotificationController::class, 'unreadCount'],
    'POST /notifications/read-all' => [NotificationController::class, 'markAllAsRead'],

    'GET /stream' => [RealtimeController::class, 'stream'],

    'GET /admin/dashboard/stats' => [AdminDashboardController::class, 'stats'],
    // Compatibilities / recomendaciones
    'GET /compatibilities/recommendations' =>
    [CompatibilityController::class, 'recommendations'],

];

$key = $method . ' ' . $uri;

if (isset($routes[$key])) {
    [$cls, $fn] = $routes[$key];
    $cls::$fn();
    exit;
}

// Dynamic routes

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

if ($method === 'GET' && preg_match('#^/admin/billing/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    AdminBillingController::detail();
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
if (
    $method === 'POST' &&
    preg_match(
        '#^/properties/(\d+)/ai-analysis$#',
        $uri,
        $m
    )
) {
    $_GET['id'] =
        (int)$m[1];

    PropertyController::requestAIAnalysis();

    exit;
}

if (
    $method === 'GET' &&
    preg_match(
        '#^/properties/(\d+)/ai-analysis$#',
        $uri,
        $m
    )
) {
    $_GET['id'] =
        (int)$m[1];

    PropertyController::getAIAnalysis();

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

if ($method === 'GET' && preg_match('#^/explore/developments/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::detail();
    exit;
}

// Developments dynamic routes

if ($method === 'GET' && preg_match('#^/developments/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::detail();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/developments/(\d+)$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::update();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/publish$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::publish();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/pause$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::pause();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/archive$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::archive();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/delete$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::delete();
    exit;
}



if ($method === 'POST' && preg_match('#^/developments/(\d+)/close$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentController::close();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/images$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentImageController::upload();
    exit;
}

if ($method === 'DELETE' && preg_match('#^/developments/images/(\d+)$#', $uri, $m)) {
    $_GET['image_id'] = (int)$m[1];
    DevelopmentImageController::delete();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/developments/(\d+)/images/reorder$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentImageController::reorder();
    exit;
}

if ($method === 'GET' && preg_match('#^/development-images/(\d+)/view$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentImageViewController::show();
    exit;
}

if ($method === 'GET' && preg_match('#^/developments/(\d+)/unit-types$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentUnitTypeController::list();
    exit;
}

if ($method === 'POST' && preg_match('#^/developments/(\d+)/unit-types$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentUnitTypeController::create();
    exit;
}

if ($method === 'PATCH' && preg_match('#^/developments/unit-types/(\d+)$#', $uri, $m)) {
    $_GET['unit_type_id'] = (int)$m[1];
    DevelopmentUnitTypeController::update();
    exit;
}

if ($method === 'DELETE' && preg_match('#^/developments/unit-types/(\d+)$#', $uri, $m)) {
    $_GET['unit_type_id'] = (int)$m[1];
    DevelopmentUnitTypeController::delete();
    exit;
}

if ($method === 'GET' && preg_match('#^/developments/(\d+)/amenities$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentAmenityController::list();
    exit;
}

if ($method === 'PUT' && preg_match('#^/developments/(\d+)/amenities$#', $uri, $m)) {
    $_GET['id'] = (int)$m[1];
    DevelopmentAmenityController::replaceAll();
    exit;
}

// Conversations detail
if ($method === 'GET' && preg_match('#^/conversations/(\d+)$#', $uri, $m)) {
    ConversationController::show((int)$m[1]);
    exit;
}

// Send message
if ($method === 'POST' && preg_match('#^/conversations/(\d+)/messages$#', $uri, $m)) {
    ConversationController::sendMessage((int)$m[1]);
    exit;
}

// Request contact share
if ($method === 'POST' && preg_match('#^/conversations/(\d+)/share-contact/request$#', $uri, $m)) {
    ConversationController::requestContactShare((int)$m[1]);
    exit;
}

// Respond contact share
if ($method === 'POST' && preg_match('#^/conversations/(\d+)/share-contact/respond$#', $uri, $m)) {
    ConversationController::respondContactShare((int)$m[1]);
    exit;
}

if ($method === 'PATCH' && preg_match('#^/conversations/(\d+)/status$#', $uri, $m)) {
    ConversationController::updateStatus((int)$m[1]);
    exit;
}

// Mark notification as read
if ($method === 'POST' && preg_match('#^/notifications/(\d+)/read$#', $uri, $m)) {
    NotificationController::markAsRead((int)$m[1]);
    exit;
}
// Archive conversation
if ($method === 'POST' && preg_match('#^/conversations/(\d+)/archive$#', $uri, $m)) {
    ConversationController::archive((int)$m[1]);
    exit;
}

// Unarchive conversation
if ($method === 'POST' && preg_match('#^/conversations/(\d+)/unarchive$#', $uri, $m)) {
    ConversationController::unarchive((int)$m[1]);
    exit;
}
if (
    $method === 'POST' &&
    preg_match(
        '#^/ai/properties/(\d+)/analyze$#',
        $uri,
        $matches
    )
) {
    AiEnrichmentController::analyzeProperty(
        (int)$matches[1]
    );
    exit;
}
if (
    $method === 'GET' &&
    preg_match(
        '#^/ai/properties/(\d+)/analysis$#',
        $uri,
        $matches
    )
) {
    AiEnrichmentController::propertyAnalysis(
        (int)$matches[1]
    );
    exit;
}
if (
    $method === 'POST' &&
    preg_match(
        '#^/ai/search-requests/(\d+)/analyze$#',
        $uri,
        $matches
    )
) {
    AiEnrichmentController::analyzeSearchRequest(
        (int)$matches[1]
    );
    exit;
}
if (
    $method === 'GET' &&
    preg_match(
        '#^/ai/search-requests/(\d+)/analysis$#',
        $uri,
        $matches
    )
) {
    AiEnrichmentController::searchRequestAnalysis(
        (int)$matches[1]
    );
    exit;
}
if (
    $method === 'GET' &&
    preg_match(
        '#^/compatibilities/(\d+)$#',
        $uri,
        $m
    )
) {
    CompatibilityController::detail(
        (int)$m[1]
    );
    exit;
}
if (
    $method === 'POST' &&
    preg_match(
        '#^/compatibilities/(\d+)/respond$#',
        $uri,
        $m
    )
) {
    CompatibilityController::respond(
        (int)$m[1]
    );
    exit;
}

if (
    $method === 'POST' &&
    preg_match(
        '#^/compatibilities/(\d+)/feedback$#',
        $uri,
        $m
    )
) {
    CompatibilityController::feedback(
        (int)$m[1]
    );
    exit;
}
if (
    $method === 'POST' &&
    preg_match(
        '#^/compatibilities/(\d+)/seen$#',
        $uri,
        $m
    )
) {
    CompatibilityController::seen(
        (int)$m[1]
    );
    exit;
}

if (
    $method === 'POST' &&
    preg_match(
        '#^/ai/compatibilities/search-requests/(\d+)/calculate$#',
        $uri,
        $matches
    )
) {
    AiCompatibilityController::calculateForSearchRequest(
        (int)$matches[1]
    );
    exit;
}
http_response_code(404);
echo json_encode([
    'error' => 'Ruta no encontrada',
    'debug' => [
        'method' => $method,
        'uri' => $uri,
        'key' => $key,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    ],
]);
