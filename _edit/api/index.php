<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\ContentType;
use Edit\Core\Email\Email;

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
$db = new Database(EDIT_BASE_PATH . '/database/site.sqlite');
$auth = new Auth($db);
$registry = new ContentTypeRegistry();
$registry->load();
$blocks = new Edit\Core\ContentTypes\BlockRegistry(EDIT_BASE_PATH . '/config');
$blocks->load();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/_edit/api', '', $path);
$path = rtrim($path, '/');

/**
 * Send JSON response
 */
function sendJson(mixed $data, int $statusCode = 200): void
{
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function sendError(string $message, int $statusCode = 400): void
{
    sendJson(['error' => $message], $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): ?array
{
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return null;
    }
    return json_decode($body, true);
}

/**
 * Rate limiting with exponential backoff
 *
 * @param Database $db Database instance
 * @param string $endpoint Endpoint identifier (e.g., 'login', 'send-email-token')
 * @param int $maxAttempts Maximum attempts allowed in the window
 * @param int $windowMinutes Time window in minutes (default: 15)
 * @return void Sends error response and exits if rate limited
 */
function checkRateLimit(Database $db, string $endpoint, int $maxAttempts = 10, int $windowMinutes = 15): void
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Clean up old rate limit records (older than 1 hour)
    $db->execute(
        "DELETE FROM rate_limits WHERE window_start < datetime('now', '-1 hour') AND (locked_until IS NULL OR locked_until < datetime('now'))"
    );

    // Check if currently locked out
    $lockCheck = $db->query(
        "SELECT locked_until FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND locked_until > datetime('now')",
        [$ipAddress, $endpoint]
    );

    if (!empty($lockCheck)) {
        $lockedUntil = new DateTime($lockCheck[0]['locked_until']);
        $now = new DateTime();
        $secondsRemaining = $lockedUntil->getTimestamp() - $now->getTimestamp();

        sendError("Rate limit exceeded. Try again in " . ceil($secondsRemaining / 60) . " minutes.", 429);
    }

    // Get current attempts within window
    $record = $db->query(
        "SELECT id, attempts, window_start FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND window_start > datetime('now', '-{$windowMinutes} minutes')",
        [$ipAddress, $endpoint]
    );

    if (empty($record)) {
        // First attempt in this window - create new record
        $db->execute(
            "INSERT INTO rate_limits (ip_address, endpoint, attempts, window_start) VALUES (?, ?, 1, datetime('now'))
             ON CONFLICT(ip_address, endpoint) DO UPDATE SET attempts = 1, window_start = datetime('now'), locked_until = NULL",
            [$ipAddress, $endpoint]
        );
    } else {
        // Increment attempts
        $currentAttempts = $record[0]['attempts'];

        if ($currentAttempts >= $maxAttempts) {
            // Calculate lockout duration with exponential backoff
            // 1st violation: 1 minute, 2nd: 5 minutes, 3rd+: 15 minutes
            $violations = floor($currentAttempts / $maxAttempts);
            $lockoutMinutes = min(15, pow(5, min($violations, 2)));

            $db->execute(
                "UPDATE rate_limits SET locked_until = datetime('now', '+{$lockoutMinutes} minutes') WHERE id = ?",
                [$record[0]['id']]
            );

            sendError("Rate limit exceeded. Locked out for {$lockoutMinutes} minutes.", 429);
        } else {
            // Increment counter
            $db->execute(
                "UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?",
                [$record[0]['id']]
            );
        }
    }
}

// Check if any users exist (for first-time setup detection)
if ($path === '/auth/has-users' && $method === 'GET') {
    $users = $db->query("SELECT COUNT(*) as count FROM users");
    sendJson(['has_users' => $users[0]['count'] > 0]);
}

// System health check (public, no auth)
if ($path === '/health' && $method === 'GET') {
    $checks = [];

    // PHP version
    $checks['php_version'] = [
        'value' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error',
        'message' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'PHP 8.1+ ✓' : 'PHP 8.1+ required'
    ];

    // Required extensions
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

    // Database writable
    $dbDir = EDIT_BASE_PATH . '/database';
    $checks['database_writable'] = [
        'value' => is_writable($dbDir),
        'status' => is_writable($dbDir) ? 'ok' : 'warning',
        'message' => is_writable($dbDir) ? 'Database directory writable ✓' : 'Database directory not writable (may cause issues)'
    ];

    // Uploads writable
    $uploadsDir = EDIT_BASE_PATH . '/uploads';
    $checks['uploads_writable'] = [
        'value' => is_writable($uploadsDir),
        'status' => is_writable($uploadsDir) ? 'ok' : 'warning',
        'message' => is_writable($uploadsDir) ? 'Uploads directory writable ✓' : 'Uploads directory not writable (media uploads will fail)'
    ];

    // Config writable
    $configDir = EDIT_BASE_PATH . '/config';
    $checks['config_writable'] = [
        'value' => is_writable($configDir),
        'status' => is_writable($configDir) ? 'ok' : 'warning',
        'message' => is_writable($configDir) ? 'Config directory writable ✓' : 'Config directory not writable (can\'t save post types)'
    ];

    // Overall status
    $hasErrors = false;
    $hasWarnings = false;
    foreach ($checks as $check) {
        if ($check['status'] === 'error') $hasErrors = true;
        if ($check['status'] === 'warning') $hasWarnings = true;
    }

    $overall = $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok');

    sendJson([
        'status' => $overall,
        'checks' => $checks,
        'message' => $hasErrors ? 'System has errors that need attention' :
                     ($hasWarnings ? 'System is functional but has warnings' : 'All systems operational')
    ]);
}

// Auth endpoints (no auth required)
if ($path === '/auth/login') {
    if ($method !== 'POST') {
        sendError('Method not allowed', 405);
    }

    // Rate limit: 5 attempts per 15 minutes
    checkRateLimit($db, 'login', 5, 15);

    $data = getJsonBody();
    if (!isset($data['email']) || !isset($data['password'])) {
        sendError('Email and password required', 400);
    }

    $result = $auth->login($data['email'], $data['password']);
    if (!$result) {
        sendError('Invalid credentials', 401);
    }

    sendJson($result);
}

if ($path === '/auth/register') {
    if ($method !== 'POST') {
        sendError('Method not allowed', 405);
    }

    // Rate limit: 3 attempts per 15 minutes
    checkRateLimit($db, 'register', 3, 15);

    // Only allow registration if no users exist (first-time setup)
    $users = $db->query("SELECT COUNT(*) as count FROM users");
    if ($users[0]['count'] > 0) {
        sendError('Registration is disabled. Please contact an administrator.', 403);
    }

    $data = getJsonBody();
    if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
        sendError('Email, password, and name required', 400);
    }

    try {
        $userId = $auth->register($data['email'], $data['password'], $data['name']);

        // Auto-login after registration
        $result = $auth->login($data['email'], $data['password']);
        sendJson($result);
    } catch (\Exception $e) {
        sendError($e->getMessage(), 400);
    }
}

if ($path === '/auth/me') {
    $userId = $auth->verifyRequest();
    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    $user = $auth->getCurrentUser();
    sendJson(['user' => $user]);
}

// ============================================
// USER MANAGEMENT API (requires auth)
// ============================================

// Get all users
if ($path === '/users' && $method === 'GET') {
    $userId = $auth->verifyRequest();
    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    $users = $db->query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC");
    sendJson($users);
}

// Create new user (admin only)
if ($path === '/users' && $method === 'POST') {
    $userId = $auth->verifyRequest();
    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    $data = getJsonBody();
    if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
        sendError('Email, password, and name required', 400);
    }

    try {
        $newUserId = $auth->register($data['email'], $data['password'], $data['name']);
        $newUser = $db->query("SELECT id, name, email, created_at FROM users WHERE id = ?", [$newUserId]);
        sendJson($newUser[0]);
    } catch (\Exception $e) {
        sendError($e->getMessage(), 400);
    }
}

// Update user password
if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $userId = $auth->verifyRequest();
    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    $targetUserId = (int)$matches[1];
    $data = getJsonBody();

    if (!isset($data['password']) || empty($data['password'])) {
        sendError('Password required', 400);
    }

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $targetUserId]);

    sendJson(['success' => true, 'message' => 'Password updated successfully']);
}

// Delete user
if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $userId = $auth->verifyRequest();
    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    $targetUserId = (int)$matches[1];

    // Prevent deleting yourself
    if ($targetUserId === $userId) {
        sendError('Cannot delete your own account', 400);
    }

    // Prevent deleting the last user
    $userCount = $db->query("SELECT COUNT(*) as count FROM users");
    if ($userCount[0]['count'] <= 1) {
        sendError('Cannot delete the last user', 400);
    }

    $db->execute("DELETE FROM users WHERE id = ?", [$targetUserId]);
    sendJson(['success' => true, 'message' => 'User deleted successfully']);
}

// ============================================
// EMAIL API (public - for contact forms)
// ============================================

// Generate single-use email token (valid for 30 seconds)
if ($path === '/send-email/token' && $method === 'GET') {
    // Rate limit: 10 tokens per hour
    checkRateLimit($db, 'email-token', 10, 60);

    // Clean up expired tokens as a side effect
    $db->cleanupExpiredEmailTokens();

    // Generate cryptographically secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 30); // 30 seconds from now

    // Store token
    $db->execute(
        "INSERT INTO email_tokens (token, expires_at) VALUES (?, ?)",
        [$token, $expiresAt]
    );

    sendJson([
        'token' => $token,
        'expires_at' => $expiresAt,
        'expires_in' => 30
    ]);
}

// Send email (requires valid single-use token)
if ($path === '/send-email' && $method === 'POST') {
    // Rate limit: 20 emails per hour (backup protection beyond tokens)
    checkRateLimit($db, 'email-send', 20, 60);

    // Clean up expired tokens as a side effect
    $db->cleanupExpiredEmailTokens();

    $data = getJsonBody();

    // Validate required fields
    if (!isset($data['to']) || !isset($data['subject']) || !isset($data['message'])) {
        sendError('Missing required fields: to, subject, message', 400);
    }

    // Validate token
    if (!isset($data['token']) || empty($data['token'])) {
        sendError('Missing email token. Call GET /send-email/token first.', 400);
    }

    // Check token exists and is not expired
    $tokenCheck = $db->query(
        "SELECT expires_at FROM email_tokens WHERE token = ?",
        [$data['token']]
    );

    if (empty($tokenCheck)) {
        sendError('Invalid or already used email token', 403);
    }

    if (strtotime($tokenCheck[0]['expires_at']) < time()) {
        sendError('Email token expired', 403);
    }

    // Delete token (single-use - delete immediately)
    $db->execute(
        "DELETE FROM email_tokens WHERE token = ?",
        [$data['token']]
    );

    // Load email configuration from database
    $fromEmail = $db->query("SELECT value FROM settings WHERE key = 'email_from_address'");
    $fromName = $db->query("SELECT value FROM settings WHERE key = 'email_from_name'");

    // Load SMTP configuration
    $smtpHost = $db->query("SELECT value FROM settings WHERE key = 'smtp_host'");
    $smtpPort = $db->query("SELECT value FROM settings WHERE key = 'smtp_port'");
    $smtpUsername = $db->query("SELECT value FROM settings WHERE key = 'smtp_username'");
    $smtpPassword = $db->query("SELECT value FROM settings WHERE key = 'smtp_password'");
    $smtpEncryption = $db->query("SELECT value FROM settings WHERE key = 'smtp_encryption'");

    $smtpConfig = null;
    if (!empty($smtpHost[0]['value'] ?? '')) {
        $smtpConfig = [
            'host' => $smtpHost[0]['value'] ?? '',
            'port' => $smtpPort[0]['value'] ?? 587,
            'username' => $smtpUsername[0]['value'] ?? '',
            'password' => $smtpPassword[0]['value'] ?? '',
            'encryption' => $smtpEncryption[0]['value'] ?? 'tls'
        ];
    }

    $emailConfig = [
        'from_email' => $fromEmail[0]['value'] ?? '',
        'from_name' => $fromName[0]['value'] ?? ''
    ];

    // Create email instance with SMTP config
    $email = new Email($emailConfig['from_email'], $emailConfig['from_name'], $smtpConfig);

    // Allow overriding from address (if provided)
    if (isset($data['from_email'])) {
        $email->setFrom($data['from_email'], $data['from_name'] ?? '');
    }

    // Send email
    $isHtml = $data['is_html'] ?? true;
    $success = $email->send($data['to'], $data['subject'], $data['message'], $isHtml);

    // Get IP address for logging
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    // Log the email send attempt
    if ($success) {
        $db->execute(
            "INSERT INTO email_logs (to_address, subject, success, ip_address) VALUES (?, ?, 1, ?)",
            [$data['to'], $data['subject'], $ipAddress]
        );
        sendJson(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        $errorMessage = $email->getLastError() ?: 'Unknown error';
        $db->execute(
            "INSERT INTO email_logs (to_address, subject, success, error_message, ip_address) VALUES (?, ?, 0, ?, ?)",
            [$data['to'], $data['subject'], $errorMessage, $ipAddress]
        );
        sendError('Failed to send email: ' . $errorMessage, 500);
    }
}

// Verify authentication for all other endpoints
$userId = $auth->verifyRequest();
if (!$userId) {
    sendError('Unauthorized', 401);
}

// ============================================
// EMAIL SETTINGS API (admin only)
// ============================================

// Get email settings
if ($path === '/email-settings' && $method === 'GET') {
    // Get settings from database
    $fromEmail = $db->query("SELECT value FROM settings WHERE key = 'email_from_address'");
    $fromName = $db->query("SELECT value FROM settings WHERE key = 'email_from_name'");

    // Get SMTP settings
    $smtpHost = $db->query("SELECT value FROM settings WHERE key = 'smtp_host'");
    $smtpPort = $db->query("SELECT value FROM settings WHERE key = 'smtp_port'");
    $smtpUsername = $db->query("SELECT value FROM settings WHERE key = 'smtp_username'");
    $smtpPassword = $db->query("SELECT value FROM settings WHERE key = 'smtp_password'");
    $smtpEncryption = $db->query("SELECT value FROM settings WHERE key = 'smtp_encryption'");

    sendJson([
        'from_email' => $fromEmail[0]['value'] ?? '',
        'from_name' => $fromName[0]['value'] ?? '',
        'smtp_host' => $smtpHost[0]['value'] ?? '',
        'smtp_port' => $smtpPort[0]['value'] ?? '587',
        'smtp_username' => $smtpUsername[0]['value'] ?? '',
        'smtp_password' => $smtpPassword[0]['value'] ?? '',
        'smtp_encryption' => $smtpEncryption[0]['value'] ?? 'tls'
    ]);
}

// Update email settings
if ($path === '/email-settings' && $method === 'PUT') {
    $data = getJsonBody();

    // Validate required fields
    $required = ['from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("{$field} is required", 400);
        }
    }

    // Validate email
    if (!filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid from_email address', 400);
    }

    // Validate port
    if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
        sendError('Invalid smtp_port', 400);
    }

    // Helper function to save setting
    $saveSetting = function($key, $value) use ($db) {
        $db->execute("
            INSERT INTO settings (key, value, updated_at)
            VALUES (?, ?, datetime('now'))
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')
        ", [$key, $value]);
    };

    // Save basic email settings
    $saveSetting('email_from_address', $data['from_email']);
    $saveSetting('email_from_name', $data['from_name']);

    // Save SMTP settings (required)
    $saveSetting('smtp_host', $data['smtp_host']);
    $saveSetting('smtp_port', $data['smtp_port']);
    $saveSetting('smtp_username', $data['smtp_username']);
    $saveSetting('smtp_password', $data['smtp_password']);
    $saveSetting('smtp_encryption', $data['smtp_encryption'] ?? 'tls');

    sendJson([
        'success' => true,
        'message' => 'Email settings updated successfully'
    ]);
}

// ============================================
// EMAIL LOGS API (admin only)
// ============================================

// Get email logs
if ($path === '/email-logs' && $method === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $logs = $db->query(
        "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    // Get total count
    $totalResult = $db->query("SELECT COUNT(*) as total FROM email_logs");
    $total = $totalResult[0]['total'] ?? 0;

    sendJson([
        'logs' => $logs,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

// ============================================
// CONTENT TYPES API (read-only, loaded from JSON)
// ============================================

// Get all active post types (for sidebar)
if ($path === '/post-types' && $method === 'GET') {
    $postTypes = $registry->getPostTypes();
    sendJson($postTypes);
}

// Get all active field groups
if ($path === '/field-groups' && $method === 'GET') {
    $fieldGroups = $registry->getFieldGroups();
    sendJson($fieldGroups);
}

// ============================================
// CONFIG API (Modular configuration management)
// ============================================

/**
 * Helper function to validate JSON structure
 */
function validateConfigJson(string $json, string $type): ?string {
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'Invalid JSON: ' . json_last_error_msg();
    }

    if ($type === 'module') {
        if (!isset($data['post_types']) || !is_array($data['post_types'])) {
            return 'Module must contain "post_types" array';
        }
        if (!isset($data['field_groups']) || !is_array($data['field_groups'])) {
            return 'Module must contain "field_groups" array';
        }
    } elseif ($type === 'field-group') {
        $required = ['key', 'title', 'locations', 'fields'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return "Field group must contain '{$field}' property";
            }
        }
    } elseif ($type === 'block') {
        $required = ['key', 'label', 'fields'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return "Block must contain '{$field}' property";
            }
        }
    }

    return null;
}

// List all config files by type
if ($path === '/config' && $method === 'GET') {
    $configPath = EDIT_BASE_PATH . '/config';
    $result = [
        'modules' => [],
        'field_groups' => [],
        'blocks' => []
    ];

    // Get modules
    $modulesPath = $configPath . '/modules';
    if (is_dir($modulesPath)) {
        foreach (glob($modulesPath . '/*.json') as $file) {
            $result['modules'][] = [
                'name' => basename($file, '.json'),
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
    }

    // Get field groups
    $fieldGroupsPath = $configPath . '/field-groups';
    if (is_dir($fieldGroupsPath)) {
        foreach (glob($fieldGroupsPath . '/*.json') as $file) {
            $result['field_groups'][] = [
                'name' => basename($file, '.json'),
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
    }

    // Get blocks
    $blocksPath = $configPath . '/blocks';
    if (is_dir($blocksPath)) {
        foreach (glob($blocksPath . '/*.json') as $file) {
            $result['blocks'][] = [
                'name' => basename($file, '.json'),
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
    }

    sendJson($result);
}

// Get specific config file content
if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches) && $method === 'GET') {
    $type = $matches[1];
    $name = $matches[2];
    $file = EDIT_BASE_PATH . "/config/{$type}/{$name}.json";

    if (!file_exists($file)) {
        sendError('Config file not found', 404);
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    echo file_get_contents($file);
    exit;
}

// Upload/Replace config file
if (preg_match('#^/config/(modules|field-groups|blocks)$#', $path, $matches) && $method === 'POST') {
    $type = $matches[1];
    $typeLabel = str_replace('-', ' ', $type);

    // Get uploaded file
    if (!isset($_FILES['file'])) {
        sendError('No file uploaded', 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendError('File upload failed', 400);
    }

    // Validate file extension
    if (!str_ends_with($file['name'], '.json')) {
        sendError('File must be a JSON file', 400);
    }

    // Read and validate content
    $content = file_get_contents($file['tmp_name']);
    $validationType = $type === 'field-groups' ? 'field-group' : rtrim($type, 's');
    $error = validateConfigJson($content, $validationType);
    if ($error) {
        sendError($error, 400);
    }

    // Save file
    $targetPath = EDIT_BASE_PATH . "/config/{$type}";
    if (!is_dir($targetPath)) {
        mkdir($targetPath, 0755, true);
    }

    $targetFile = $targetPath . '/' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        sendError('Failed to save file', 500);
    }

    // Reload configuration
    $registry->reload();
    $blocks->reload();

    sendJson([
        'success' => true,
        'message' => ucfirst($typeLabel) . ' uploaded successfully',
        'filename' => basename($file['name'])
    ]);
}

// Delete config file
if (preg_match('#^/config/(modules|field-groups|blocks)/([a-z0-9_-]+)$#', $path, $matches) && $method === 'DELETE') {
    $type = $matches[1];
    $name = $matches[2];
    $file = EDIT_BASE_PATH . "/config/{$type}/{$name}.json";

    if (!file_exists($file)) {
        sendError('Config file not found', 404);
    }

    if (!unlink($file)) {
        sendError('Failed to delete file', 500);
    }

    // Reload configuration
    $registry->reload();
    $blocks->reload();

    sendJson(['success' => true, 'message' => 'Config file deleted successfully']);
}

// Get all blocks (for block editor)
if ($path === '/blocks' && $method === 'GET') {
    sendJson($blocks->getBlocks());
}

// Export all configuration as ZIP
if ($path === '/config/export' && $method === 'GET') {
    $configPath = EDIT_BASE_PATH . '/config';
    $zipFile = sys_get_temp_dir() . '/edit-config-' . time() . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        sendError('Failed to create ZIP archive', 500);
    }

    // Add all modules
    if (is_dir($configPath . '/modules')) {
        foreach (glob($configPath . '/modules/*.json') as $file) {
            $zip->addFile($file, 'modules/' . basename($file));
        }
    }

    // Add all field groups
    if (is_dir($configPath . '/field-groups')) {
        foreach (glob($configPath . '/field-groups/*.json') as $file) {
            $zip->addFile($file, 'field-groups/' . basename($file));
        }
    }

    // Add all blocks
    if (is_dir($configPath . '/blocks')) {
        foreach (glob($configPath . '/blocks/*.json') as $file) {
            $zip->addFile($file, 'blocks/' . basename($file));
        }
    }

    $zip->close();

    // Send file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="edit-config-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
    exit;
}

// Import all configuration from ZIP
if ($path === '/config/import' && $method === 'POST') {
    if (!isset($_FILES['file'])) {
        sendError('No file uploaded', 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendError('File upload failed', 400);
    }

    // Validate file is a ZIP
    if (!str_ends_with($file['name'], '.zip')) {
        sendError('File must be a ZIP archive', 400);
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        sendError('Failed to open ZIP archive', 400);
    }

    $configPath = EDIT_BASE_PATH . '/config';

    // Create backup before importing
    $backupDir = $configPath . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $backupZip = new ZipArchive();
    $backupFile = $backupDir . '/' . time() . '.zip';

    if ($backupZip->open($backupFile, ZipArchive::CREATE) === true) {
        // Add all current modules
        if (is_dir($configPath . '/modules')) {
            foreach (glob($configPath . '/modules/*.json') as $file) {
                $backupZip->addFile($file, 'modules/' . basename($file));
            }
        }

        // Add all current field groups
        if (is_dir($configPath . '/field-groups')) {
            foreach (glob($configPath . '/field-groups/*.json') as $file) {
                $backupZip->addFile($file, 'field-groups/' . basename($file));
            }
        }

        // Add all current blocks
        if (is_dir($configPath . '/blocks')) {
            foreach (glob($configPath . '/blocks/*.json') as $file) {
                $backupZip->addFile($file, 'blocks/' . basename($file));
            }
        }

        $backupZip->close();
    }

    $errors = [];
    $imported = ['modules' => 0, 'field_groups' => 0, 'blocks' => 0];

    // Extract and validate each file
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);

        // Determine type from path
        if (preg_match('#^(modules|field-groups|blocks)/([^/]+\.json)$#', $filename, $matches)) {
            $type = $matches[1];
            $basename = $matches[2];

            // Get file content
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $errors[] = "Failed to read {$filename}";
                continue;
            }

            // Validate JSON
            $validationType = $type === 'field-groups' ? 'field-group' : rtrim($type, 's');
            $error = validateConfigJson($content, $validationType);
            if ($error) {
                $errors[] = "{$filename}: {$error}";
                continue;
            }

            // Save file
            $targetDir = $configPath . '/' . $type;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $targetFile = $targetDir . '/' . $basename;
            if (file_put_contents($targetFile, $content) === false) {
                $errors[] = "Failed to write {$filename}";
                continue;
            }

            // Count successful imports
            $key = str_replace('-', '_', $type);
            $imported[$key]++;
        }
    }

    $zip->close();

    // Reload configuration
    $registry->reload();
    $blocks->reload();

    $backupFilename = basename($backupFile);

    if (!empty($errors)) {
        sendJson([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'backup' => $backupFilename,
            'message' => 'Import completed with some errors. Backup saved to: ' . $backupFilename
        ]);
    } else {
        sendJson([
            'success' => true,
            'imported' => $imported,
            'backup' => $backupFilename,
            'message' => 'All configuration files imported successfully. Backup saved to: ' . $backupFilename
        ]);
    }
}

// Media upload endpoint
if ($path === '/media' && $method === 'POST') {
    if (!isset($_FILES['file'])) {
        sendError('No file uploaded', 400);
    }

    $file = $_FILES['file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
        sendError($errorMsg, 400);
    }

    // Create upload directory structure
    $uploadDir = EDIT_BASE_PATH . '/uploads/' . date('Y/m');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        sendError('Failed to save file', 500);
    }

    // Save to database
    $relativePath = date('Y/m') . '/' . $filename;
    $db->execute(
        "INSERT INTO media (filename, path, mime_type, size) VALUES (?, ?, ?, ?)",
        [$file['name'], $relativePath, $file['type'], $file['size']]
    );

    $mediaId = $db->lastInsertId();
    $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId])[0];
    $media['url'] = '/_edit/uploads/' . $media['path'];

    sendJson($media);
}

// Media list/get/delete endpoints
if (preg_match('#^/media(/(\d+))?$#', $path, $mediaMatches)) {
    $mediaId = $mediaMatches[2] ?? null;

    if ($method === 'GET') {
        if ($mediaId) {
            // Get single media
            $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId]);
            if (empty($media)) {
                sendError('Media not found', 404);
            }
            $media[0]['url'] = '/_edit/uploads/' . $media[0]['path'];
            sendJson($media[0]);
        } else {
            // List all media
            $media = $db->query("SELECT * FROM media ORDER BY created_at DESC");
            foreach ($media as &$item) {
                $item['url'] = '/_edit/uploads/' . $item['path'];
            }
            sendJson($media);
        }
    }

    if ($method === 'DELETE' && $mediaId) {
        // Delete media file and database record
        $media = $db->query("SELECT * FROM media WHERE id = ?", [$mediaId]);
        if (!empty($media)) {
            $filePath = EDIT_BASE_PATH . '/uploads/' . $media[0]['path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $db->execute("DELETE FROM media WHERE id = ?", [$mediaId]);
        }
        sendJson(['success' => true]);
    }
}

// Content type endpoints
if (preg_match('#^/([a-z_-]+)(/(\d+))?$#', $path, $matches)) {
    $type = $matches[1];
    $id = isset($matches[3]) ? (int) $matches[3] : null;

    // Skip if it's an auth or media route
    if (in_array($type, ['auth', 'media'])) {
        sendError('Not found', 404);
    }

    // Check if content type exists
    if (!$registry->exists($type)) {
        sendError("Content type '{$type}' not found", 404);
    }

    $contentType = new ContentType($db, $type, $registry->get($type));

    // Handle different HTTP methods
    try {
        switch ($method) {
            case 'GET':
                if ($id) {
                    // Get single item
                    $result = $contentType->find($id);
                    if (!$result) {
                        sendError('Content not found', 404);
                    }
                    sendJson($result);
                } else {
                    // Get all items
                    $filters = [];
                    if (isset($_GET['status'])) {
                        $filters['status'] = $_GET['status'];
                    }
                    if (isset($_GET['limit'])) {
                        $filters['limit'] = (int) $_GET['limit'];
                    }
                    if (isset($_GET['offset'])) {
                        $filters['offset'] = (int) $_GET['offset'];
                    }

                    $result = $contentType->all($filters);
                    sendJson($result);
                }
                break;

            case 'POST':
                // Create new item
                $data = getJsonBody();
                if (!$data) {
                    sendError('Invalid JSON data', 400);
                }

                // Set author to current user
                $data['author_id'] = $userId;

                $newId = $contentType->create($data);
                $result = $contentType->find($newId);
                sendJson($result, 201);
                break;

            case 'PUT':
                // Update item
                if (!$id) {
                    sendError('ID required for update', 400);
                }

                $data = getJsonBody();
                if (!$data) {
                    sendError('Invalid JSON data', 400);
                }

                $contentType->update($id, $data);
                $result = $contentType->find($id);
                sendJson($result);
                break;

            case 'DELETE':
                // Delete item
                if (!$id) {
                    sendError('ID required for delete', 400);
                }

                $contentType->delete($id);
                sendJson(['success' => true]);
                break;

            default:
                sendError('Method not allowed', 405);
        }
    } catch (\Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

// If no route matched
sendError('Not found', 404);
