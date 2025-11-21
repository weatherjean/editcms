<?php
// Router for PHP built-in server
// Enforces /_edit prefix

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

$file_uri = preg_replace('#^/_edit#', '', $uri);
$file_path = __DIR__ . '/_edit' . $file_uri;

if (preg_match('#^/_edit/admin-api/#', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri;
    require __DIR__ . '/_edit/admin-api/index.php';
    exit;
}

if (preg_match('#^/_edit/api/#', $uri)) {
    $_SERVER['REQUEST_URI'] = $uri;
    require __DIR__ . '/_edit/api/index.php';
    exit;
}

// For _edit root (Vue app), serve from Vite dev server during development
if (preg_match('#^/_edit(/|$)#', $uri) && !preg_match('#^/_edit/(admin-api|api|uploads|data)/#', $uri)) {
    // In dev, Vite serves the Vue app
    echo "<!-- Dev mode: Vite should be serving this at http://localhost:5173 -->\n";
    echo "<!-- Make sure to access via http://localhost:5173/_edit/ -->\n";
    http_response_code(404);
    exit;
}

if (is_dir($file_path)) {
    if (file_exists($file_path . '/index.html')) {
        $file_path .= '/index.html';
    } elseif (file_exists($file_path . '/index.php')) {
        $file_path .= '/index.php';
    }
}

if (file_exists($file_path) && is_file($file_path)) {
    // Security: Verify the resolved path is still within _edit directory
    $realPath = realpath($file_path);
    $basePath = realpath(__DIR__ . '/_edit');

    if ($realPath === false || strpos($realPath, $basePath) !== 0) {
        http_response_code(403);
        echo "403 Forbidden - Invalid path";
        exit;
    }

    if (pathinfo($file_path, PATHINFO_EXTENSION) === 'php') {
        require $file_path;
        return true;
    }

    return false;
}

http_response_code(404);
echo "404 Not Found";
exit;
