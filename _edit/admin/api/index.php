<?php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/helpers.php';

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\BlockRegistry;

// Handle CORS - Allow requests from Vite dev server
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize services
$db = new Database(EDIT_BASE_PATH . '/data/database/site.sqlite');
$auth = new Auth($db);
$registry = new ContentTypeRegistry();
$registry->load();
$blocks = new BlockRegistry(EDIT_BASE_PATH . '/data/config');
$blocks->load();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/_edit/api', '', $path);
$path = rtrim($path, '/');

// ============================================
// PUBLIC ROUTES (no auth required)
// ============================================

require_once __DIR__ . '/routes/health.php';
if (handleHealthRoutes($method, $path, $db)) exit;

require_once __DIR__ . '/routes/public.php';
if (handlePublicRoutes($method, $path, $db, $registry, $blocks)) exit;

require_once __DIR__ . '/routes/email.php';
if (handlePublicEmailRoutes($method, $path, $db)) exit;

// ============================================
// AUTH ROUTES (no auth required)
// ============================================

require_once __DIR__ . '/routes/auth.php';
if (handleAuthRoutes($method, $path, $auth, $db)) exit;

// ============================================
// AUTH CHECKPOINT
// ============================================

$userId = $auth->verifyRequest();
if (!$userId) {
    sendError('Unauthorized', 401);
}

// ============================================
// AUTHENTICATED ROUTES
// ============================================

require_once __DIR__ . '/routes/users.php';
if (handleUserRoutes($method, $path, $db, $auth, $userId)) exit;

if (handleEmailAdminRoutes($method, $path, $db)) exit;

require_once __DIR__ . '/routes/config.php';
if (handleConfigRoutes($method, $path, $registry, $blocks)) exit;

require_once __DIR__ . '/routes/media.php';
if (handleMediaRoutes($method, $path, $db, $userId)) exit;

require_once __DIR__ . '/routes/content.php';
if (handleContentRoutes($method, $path, $db, $registry, $userId)) exit;

// ============================================
// NO ROUTE MATCHED
// ============================================

sendError('Not found', 404);
