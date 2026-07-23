<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;

/**
 * Authentication routes (no auth required)
 *
 * Routes:
 * - POST /auth/login - User login
 * - POST /auth/register - User registration (first-time setup only)
 * - GET /auth/me - Get current user (requires auth)
 */
function handleAuthRoutes(string $method, string $path, Auth $auth, Database $db): bool
{
    if ($path === '/auth/login') {
        requireMethod($method, 'POST');

        // Rate limit: 5 attempts per 15 minutes
        checkRateLimit($db, 'login', 5, 15);

        $data = getAuthJsonBody();
        requireFields($data, ['email', 'password']);

        $result = $auth->login($data['email'], $data['password']);
        if (!$result) {
            sendError('Invalid credentials', 401);
        }

        sendJson($result);
        return true;
    }

    if ($path === '/auth/register') {
        requireMethod($method, 'POST');

        // Rate limit: 3 attempts per 15 minutes
        checkRateLimit($db, 'register', 3, 15);

        // Only allow registration if no users exist (first-time setup)
        $users = $db->table('users')->count();
        if ($users > 0) {
            sendError('Registration is disabled. Please contact an administrator.', 403);
        }

        $data = getAuthJsonBody();
        requireFields($data, ['email', 'password', 'name']);

        try {
            (new \Edit\Core\Auth\Installation($db))->claim($data['setup_code'] ?? '', $data['email'], $data['password'], $data['name']);

            $result = $auth->login($data['email'], $data['password']);
            sendJson($result);
        } catch (\DomainException $e) {
            sendError($e->getMessage(), 403);
        } catch (\Exception $e) {
            sendError($e->getMessage(), 400);
        }
        return true;
    }

    if ($path === '/auth/logout' && $method === 'POST') {
        $authHeader = getAuthHeader();

        if (empty($authHeader)) {
            sendError('No token provided', 400);
        }

        $success = $auth->logout($authHeader);
        sendJson(['success' => $success, 'message' => $success ? 'Logged out successfully' : 'Session not found']);
        return true;
    }

    return false;
}

function getAuthJsonBody(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if (strlen($raw) > 8192) sendError('Authentication request is too large', 413);
    try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (\JsonException $e) { sendError('Invalid JSON data', 400); }
    if (!is_array($data) || array_is_list($data)) sendError('Authentication request must be an object', 400);
    foreach (['email','password','name','setup_code'] as $key) {
        if (array_key_exists($key,$data) && !is_string($data[$key])) sendError("{$key} must be a string", 400);
    }
    return $data;
}
