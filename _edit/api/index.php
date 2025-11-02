<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;
use Edit\Core\ContentTypes\ContentTypeRegistry;
use Edit\Core\ContentTypes\ContentType;

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

// Check if any users exist (for first-time setup detection)
if ($path === '/auth/has-users' && $method === 'GET') {
    $users = $db->query("SELECT COUNT(*) as count FROM users");
    sendJson(['has_users' => $users[0]['count'] > 0]);
}

// Auth endpoints (no auth required)
if ($path === '/auth/login') {
    if ($method !== 'POST') {
        sendError('Method not allowed', 405);
    }

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

// Verify authentication for all other endpoints
$userId = $auth->verifyRequest();
if (!$userId) {
    sendError('Unauthorized', 401);
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
// CONFIG API (JSON editing)
// ============================================

// Get full config
if ($path === '/config' && $method === 'GET') {
    try {
        $config = $registry->getConfig();
        sendJson($config);
    } catch (\Exception $e) {
        sendError($e->getMessage(), 500);
    }
}

// Save full config
if ($path === '/config' && $method === 'PUT') {
    $data = getJsonBody();
    if (!is_array($data)) {
        sendError('Invalid JSON object', 400);
    }

    // Validate structure
    if (!isset($data['post_types']) || !is_array($data['post_types'])) {
        sendError('Config must contain "post_types" array', 400);
    }
    if (!isset($data['field_groups']) || !is_array($data['field_groups'])) {
        sendError('Config must contain "field_groups" array', 400);
    }

    try {
        $registry->saveConfig($data);
        $registry->load(); // Reload
        sendJson(['success' => true, 'message' => 'Configuration saved successfully']);
    } catch (\Exception $e) {
        sendError($e->getMessage(), 500);
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
