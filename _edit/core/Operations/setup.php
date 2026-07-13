<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../bootstrap.php';
try {
    $db = new Edit\Core\Database\Database(EDIT_DATABASE_PATH);
    $token = (new Edit\Core\Auth\Installation($db))->issueToken();
    echo "One-time setup code (expires in one hour):\n{$token}\nEnter this in the CMS setup form. A new code invalidates the previous code.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
