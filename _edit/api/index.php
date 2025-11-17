<?php

declare(strict_types=1);

// Public API - No authentication required
// Handles: health checks, public content queries

// Bootstrap
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/helpers.php';

use Edit\Core\Database\Database;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\Security\Security;

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request early for health checks
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove /_edit/api prefix
$path = preg_replace('#^/_edit/api/?#', '', $uri);
$path = '/' . ltrim($path, '/');

// Initialize basic services for health checks
$db = new Database(EDIT_DATABASE_PATH);

// Load route handlers
require_once __DIR__ . '/routes/health.php';
require_once __DIR__ . '/routes/public.php';

// Route health check first (before heavy initialization)
if (handleHealthRoutes($method, $path, $db)) {
    exit;
}

// Initialize remaining services for public routes
$registry = new ContentTypeRegistry(EDIT_BASE_PATH . '/data/config');
$registry->load();
$blocks = new \Edit\Core\ContentTypes\BlockRegistry(EDIT_BASE_PATH . '/data/config');
$blocks->load();

// Route public content requests
if (handlePublicRoutes($method, $path, $db, $registry, $blocks)) {
    exit;
}

// No route matched
http_response_code(404);
sendJson(['error' => 'Endpoint not found']);
