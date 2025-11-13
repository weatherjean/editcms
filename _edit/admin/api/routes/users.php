<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;

/**
 * User management routes (requires auth)
 *
 * Routes:
 * - GET /users - List all users
 * - POST /users - Create new user
 * - PUT /users/:id - Update user password
 * - DELETE /users/:id - Delete user
 */
function handleUserRoutes(string $method, string $path, Database $db, Auth $auth, int $userId): bool
{
    // Get all users
    if ($path === '/users' && $method === 'GET') {
        $users = $db->table('users')
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderBy('created_at', 'DESC')
            ->get();
        sendJson($users);
        return true;
    }

    // Create new user (admin only)
    if ($path === '/users' && $method === 'POST') {
        $data = getJsonBody();
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            sendError('Email, password, and name required', 400);
        }

        try {
            $newUserId = $auth->register($data['email'], $data['password'], $data['name']);
            $newUser = $db->table('users')
                ->select(['id', 'name', 'email', 'created_at'])
                ->where('id', $newUserId)
                ->first();
            sendJson($newUser[0]);
        } catch (\Exception $e) {
            sendError($e->getMessage(), 400);
        }
        return true;
    }

    // Update user password
    if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $targetUserId = (int)$matches[1];
        $data = getJsonBody();

        if (!isset($data['password']) || empty($data['password'])) {
            sendError('Password required', 400);
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $db->table('users')
            ->where('id', $targetUserId)
            ->update(['password' => $passwordHash]);

        sendJson(['success' => true, 'message' => 'Password updated successfully']);
        return true;
    }

    // Delete user
    if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $targetUserId = (int)$matches[1];

        // Prevent deleting yourself
        if ($targetUserId === $userId) {
            sendError('Cannot delete your own account', 400);
        }

        // Prevent deleting the last user
        $userCount = $db->table('users')->count();
        if ($userCount <= 1) {
            sendError('Cannot delete the last user', 400);
        }

        $db->table('users')->where('id', $targetUserId)->delete();
        sendJson(['success' => true, 'message' => 'User deleted successfully']);
        return true;
    }

    return false;
}
