<?php
// Development server: expose only API front controllers and approved static files.
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($uri === '/' || $uri === '/_edit' || $uri === '/_edit/') {
    header('Location: /_edit/admin/');
    exit;
}
$deny = static function (): never { http_response_code(404); exit('404 Not Found'); };
if (str_contains($uri, "\0") || str_contains($uri, '\\') || preg_match('#(?:^|/)\.#', $uri)) $deny();
foreach (['admin-api', 'api'] as $api) {
    if ($uri === '/_edit/' . $api || str_starts_with($uri, '/_edit/' . $api . '/')) {
        require __DIR__ . '/_edit/' . $api . '/index.php';
        exit;
    }
}
$area = str_starts_with($uri, '/_edit/uploads/') ? 'uploads' : (str_starts_with($uri, '/_edit/admin/') ? 'admin' : null);
if (!$area) $deny();
$base = realpath(__DIR__ . '/_edit/' . $area);
$file = realpath(__DIR__ . $uri);
if ($area === 'admin' && (!$file || is_dir($file))) $file = realpath(__DIR__ . '/_edit/admin/index.html');
if (!$base || !$file || !str_starts_with($file, $base . '/') || !is_file($file)) $deny();
$extensions = $area === 'uploads' ? ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','mp4','webm','ogg','oga','mp3','wav'] : ['html','js','css','json','txt','md','svg','png','jpg','ico','woff','woff2'];
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($extension, $extensions, true) || ($area === 'uploads' && !preg_match('/^[^.]+\.[^.]+$/', basename($file)))) $deny();
// Read explicitly: never hand uploaded files to the PHP execution handler.
$types = ['js'=>'text/javascript','css'=>'text/css','html'=>'text/html','json'=>'application/json','txt'=>'text/plain','md'=>'text/plain','svg'=>'image/svg+xml'];
header('Content-Type: ' . ($types[$extension] ?? mime_content_type($file)));
header('X-Content-Type-Options: nosniff');
if ($area === 'uploads') header("Content-Security-Policy: sandbox; default-src 'none'");
readfile($file);
