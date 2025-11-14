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
    // Login
    if ($path === '/auth/login') {
        if ($method !== 'POST') {
            sendError('Method not allowed', 405);
        }

        // Rate limit: 5 attempts per 15 minutes
        checkRateLimit($db, 'login', 5, 15);

        $data = getJsonBody();
        requireFields($data, ['email', 'password']);

        $result = $auth->login($data['email'], $data['password']);
        if (!$result) {
            sendError('Invalid credentials', 401);
        }

        sendJson($result);
        return true;
    }

    // Register (first-time setup only)
    if ($path === '/auth/register') {
        if ($method !== 'POST') {
            sendError('Method not allowed', 405);
        }

        // Rate limit: 3 attempts per 15 minutes
        checkRateLimit($db, 'register', 3, 15);

        // Only allow registration if no users exist (first-time setup)
        $users = $db->table('users')->count();
        if ($users > 0) {
            sendError('Registration is disabled. Please contact an administrator.', 403);
        }

        $data = getJsonBody();
        requireFields($data, ['email', 'password', 'name']);

        try {
            $userId = $auth->register($data['email'], $data['password'], $data['name']);

            // Auto-login after registration
            $result = $auth->login($data['email'], $data['password']);
            sendJson($result);
        } catch (\Exception $e) {
            sendError($e->getMessage(), 400);
        }
        return true;
    }

    // Get current user (requires auth)
    if ($path === '/auth/me') {
        $userId = $auth->verifyRequest();
        if (!$userId) {
            sendError('Unauthorized', 401);
        }

        $user = $auth->getCurrentUser();
        sendJson(['user' => $user]);
        return true;
    }

    // Logout (requires auth)
    if ($path === '/auth/logout' && $method === 'POST') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            $authHeader = apache_request_headers()['Authorization'] ?? '';
        }

        if (empty($authHeader)) {
            sendError('No token provided', 400);
        }

        $success = $auth->logout($authHeader);
        sendJson(['success' => $success, 'message' => $success ? 'Logged out successfully' : 'Session not found']);
        return true;
    }

    return false;
}
