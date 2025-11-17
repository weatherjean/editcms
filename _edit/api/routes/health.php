<?php

declare(strict_types=1);

use Edit\Core\Database\Database;

/**
 * Health and system check routes (public, no auth required)
 *
 * Routes:
 * - GET /auth/has-users - Check if any users exist
 * - GET /health - System health check
 */
function handleHealthRoutes(string $method, string $path, Database $db): bool
{
    if ($path === '/auth/has-users' && $method === 'GET') {
        $count = $db->table('users')->count();
        sendJson(['has_users' => $count > 0]);
        return true;
    }

    if ($path === '/health' && $method === 'GET') {
        $checks = [];

        // .htaccess file integrity check
        // Hashes are auto-generated during build process - DO NOT EDIT MANUALLY
        $htaccessFiles = [
            'root' => [
                'path' => EDIT_BASE_PATH . '/.htaccess',
                'hash' => '{{HASH_ROOT}}',
                'label' => '_edit/.htaccess'
            ],
            'admin' => [
                'path' => EDIT_BASE_PATH . '/admin/.htaccess',
                'hash' => '{{HASH_ADMIN}}',
                'label' => 'admin/.htaccess'
            ],
            'admin_api' => [
                'path' => EDIT_BASE_PATH . '/admin-api/.htaccess',
                'hash' => '{{HASH_ADMIN_API}}',
                'label' => 'admin-api/.htaccess'
            ],
            'api' => [
                'path' => EDIT_BASE_PATH . '/api/.htaccess',
                'hash' => '{{HASH_API}}',
                'label' => 'api/.htaccess'
            ],
            'core' => [
                'path' => EDIT_BASE_PATH . '/core/.htaccess',
                'hash' => '{{HASH_CORE}}',
                'label' => 'core/.htaccess'
            ],
            'data' => [
                'path' => EDIT_BASE_PATH . '/data/.htaccess',
                'hash' => '{{HASH_DATA}}',
                'label' => 'data/.htaccess'
            ],
            'uploads' => [
                'path' => EDIT_BASE_PATH . '/uploads/.htaccess',
                'hash' => '{{HASH_UPLOADS}}',
                'label' => 'uploads/.htaccess'
            ]
        ];

        $htaccessStatus = [];
        $htaccessErrors = 0;
        foreach ($htaccessFiles as $key => $file) {
            if (!file_exists($file['path'])) {
                $htaccessStatus[$key] = [
                    'status' => 'error',
                    'message' => 'MISSING',
                    'label' => $file['label']
                ];
                $htaccessErrors++;
            } else {
                $currentHash = hash_file('sha256', $file['path']);
                $isValid = $currentHash === $file['hash'];
                $htaccessStatus[$key] = [
                    'status' => $isValid ? 'ok' : 'error',
                    'message' => $isValid ? 'Valid' : 'MODIFIED',
                    'label' => $file['label']
                ];
                if (!$isValid) {
                    $htaccessErrors++;
                }
            }
        }

        $checks['htaccess_integrity'] = [
            'value' => $htaccessStatus,
            'status' => $htaccessErrors === 0 ? 'ok' : 'error',
            'message' => $htaccessErrors === 0
                ? 'All .htaccess files valid'
                : $htaccessErrors . ' .htaccess file(s) missing or modified - SECURITY RISK',
            'count' => count($htaccessFiles),
            'errors' => $htaccessErrors
        ];

        $checks['php_version'] = [
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error',
            'message' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'PHP 8.1+ ✓' : 'PHP 8.1+ required'
        ];

        $requiredExtensions = ['sqlite3', 'pdo', 'json', 'fileinfo'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        $checks['extensions'] = [
            'value' => $missingExtensions,
            'status' => empty($missingExtensions) ? 'ok' : 'error',
            'message' => empty($missingExtensions) ? 'All required extensions loaded ✓' : 'Missing: ' . implode(', ', $missingExtensions)
        ];

        $dbDir = EDIT_BASE_PATH . '/data/database';
        $checks['database_writable'] = [
            'value' => is_writable($dbDir),
            'status' => is_writable($dbDir) ? 'ok' : 'warning',
            'message' => is_writable($dbDir) ? 'Database directory writable ✓' : 'Database directory not writable (may cause issues)'
        ];

        $uploadsDir = EDIT_BASE_PATH . '/uploads';
        $checks['uploads_writable'] = [
            'value' => is_writable($uploadsDir),
            'status' => is_writable($uploadsDir) ? 'ok' : 'warning',
            'message' => is_writable($uploadsDir) ? 'Uploads directory writable ✓' : 'Uploads directory not writable (media uploads will fail)'
        ];

        $configDir = EDIT_BASE_PATH . '/data/config';
        $checks['config_writable'] = [
            'value' => is_writable($configDir),
            'status' => is_writable($configDir) ? 'ok' : 'warning',
            'message' => is_writable($configDir) ? 'Config directory writable ✓' : 'Config directory not writable (can\'t save post types)'
        ];

        $hasErrors = false;
        $hasWarnings = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'error') $hasErrors = true;
            if ($check['status'] === 'warning') $hasWarnings = true;
        }

        $overall = $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok');

        $userCount = $db->table('users')->count();

        sendJson([
            'status' => $overall,
            'checks' => $checks,
            'message' => $hasErrors ? 'System has errors that need attention' :
                         ($hasWarnings ? 'System is functional but has warnings' : 'All systems operational'),
            'first_time_setup' => $userCount === 0
        ]);
        return true;
    }

    return false;
}
