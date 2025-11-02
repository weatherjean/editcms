<?php

declare(strict_types=1);

/**
 * Bootstrap file for _edit CMS
 * Sets up autoloading and basic configuration
 */

// Define base path
define('EDIT_BASE_PATH', dirname(__DIR__));

// Simple PSR-4 autoloader
spl_autoload_register(function ($class) {
    // Namespace mappings
    $prefixes = [
        'Edit\\Core\\' => EDIT_BASE_PATH . '/core/',
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
