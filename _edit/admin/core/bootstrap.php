<?php

declare(strict_types=1);

/**
 * Bootstrap file for _edit CMS
 * Sets up autoloading and basic configuration
 */

// Define base path - points to _edit/ root (parent of admin/)
define('EDIT_BASE_PATH', dirname(__DIR__, 2));

// Load configuration
$configFile = EDIT_BASE_PATH . '/config.php';
if (!file_exists($configFile)) {
    // Auto-generate secure config.php with random encryption key
    $encryptionKey = bin2hex(random_bytes(32));
    $configContent = <<<PHP
<?php
/**
 * _edit CMS Configuration
 *
 * This file was auto-generated on first startup.
 * You can customize these settings for your environment.
 */

// Security: Encryption key for sensitive data (SMTP passwords, etc.)
// This key was automatically generated - keep it secure!
define('EDIT_ENCRYPTION_KEY', '{$encryptionKey}');

// CORS: Allowed origins for API requests (development only)
// In production, leave empty array - Vue app served from same origin
define('EDIT_CORS_ORIGINS', [
    'http://localhost:5173',  // Vite dev server
    'http://localhost:3000',
    'http://127.0.0.1:5173',
]);

// Database path (relative to _edit directory)
// Default: /data/database/site.sqlite
define('EDIT_DATABASE_PATH', EDIT_BASE_PATH . '/data/database/site.sqlite');

// Debug mode (set to false in production)
define('EDIT_DEBUG', true);

// Session token expiry (in hours)
define('EDIT_SESSION_EXPIRY_HOURS', 24);

// Pagination defaults
define('EDIT_DEFAULT_PAGE_LIMIT', 10);
define('EDIT_MAX_PAGE_LIMIT', 100);

// File upload limits
// IMPORTANT: Ensure php.ini has upload_max_filesize and post_max_size >= these values
define('EDIT_MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('EDIT_MAX_IMAGE_WIDTH', 4000);
define('EDIT_MAX_IMAGE_HEIGHT', 4000);

// ZIP validation limits (for config import/export)
define('EDIT_MAX_ZIP_COMPRESSED_SIZE', 10 * 1024 * 1024); // 10MB
define('EDIT_MAX_ZIP_UNCOMPRESSED_SIZE', 50 * 1024 * 1024); // 50MB
define('EDIT_MAX_ZIP_FILES', 1000);

PHP;
    file_put_contents($configFile, $configContent);
}

require_once $configFile;

// Validate encryption key is not the default in production
if (EDIT_ENCRYPTION_KEY === 'CHANGE_THIS_IN_PRODUCTION_USE_RANDOM_32_BYTE_HEX_STRING') {
    if (!EDIT_DEBUG) {
        // Production: fatal error
        trigger_error('CRITICAL: EDIT_ENCRYPTION_KEY must be changed! Delete config.php to auto-generate a secure key.', E_USER_ERROR);
    } else {
        // Development: just warn
        error_log('WARNING: Using default EDIT_ENCRYPTION_KEY. Change this in production!');
    }
}

// Simple PSR-4 autoloader
spl_autoload_register(function ($class) {
    // Namespace mappings
    $prefixes = [
        'Edit\\Core\\' => EDIT_BASE_PATH . '/admin/core/',
    ];

    // Check each prefix
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Error reporting - disable display in production, log to file
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Timezone
date_default_timezone_set('UTC');
