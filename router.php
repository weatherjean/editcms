<?php
// Router for PHP built-in server
// Enforces /_edit prefix

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Root redirect
if ($uri === '/' || $uri === '') {
    header('Location: /_edit/admin/');
    exit;
}

// Block direct /admin/ access - must use /_edit/admin/
if (preg_match('#^/admin(/|$)#', $uri)) {
    http_response_code(404);
    echo "404 Not Found - Use /_edit/admin/ instead";
    exit;
}

// Block direct /api/ access - must use /_edit/api/
if (preg_match('#^/api(/|$)#', $uri)) {
    http_response_code(404);
    echo "404 Not Found - Use /_edit/api/ instead";
    exit;
}

// Only allow /_edit/ prefix
if (!preg_match('#^/_edit/#', $uri)) {
    http_response_code(404);
    echo "404 Not Found";
    exit;
}

// Remove /_edit prefix for file serving
$file_uri = preg_replace('#^/_edit#', '', $uri);
$file_path = __DIR__ . '/_edit' . $file_uri;

// Special handling for API requests - route everything to admin/api/index.php
if (preg_match('#^/_edit/api/#', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri; // Keep original URI for api/index.php
    require __DIR__ . '/_edit/admin/api/index.php';
    exit;
}

// If it's a directory, try index.html or index.php
if (is_dir($file_path)) {
    if (file_exists($file_path . '/index.html')) {
        $file_path .= '/index.html';
    } elseif (file_exists($file_path . '/index.php')) {
        $file_path .= '/index.php';
    }
}

// If file exists, serve it
if (file_exists($file_path) && is_file($file_path)) {
    // Security: Verify the resolved path is still within _edit directory
    $realPath = realpath($file_path);
    $basePath = realpath(__DIR__ . '/_edit');

    if ($realPath === false || strpos($realPath, $basePath) !== 0) {
        http_response_code(403);
        echo "403 Forbidden - Invalid path";
        exit;
    }

    // For PHP files, execute them
    if (pathinfo($file_path, PATHINFO_EXTENSION) === 'php') {
        require $file_path;
        return true;
    }

    // For other files, let PHP serve them
    return false;
}

// File not found
http_response_code(404);
echo "404 Not Found";
exit;
