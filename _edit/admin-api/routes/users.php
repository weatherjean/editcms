<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Auth\Auth;
use Edit\Core\Security\Security;

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
    if ($path === '/users' && $method === 'GET') {
        $users = $db->table('users')
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->orderBy('created_at', 'DESC')
            ->get();
        sendJson($users);
        return true;
    }

    if ($path === '/users' && $method === 'POST') {
        $data = getAuthJsonBody();
        requireFields($data, ['email', 'password', 'name']);

        try {
            $role = $data['role'] ?? 'editor';
            if (!is_string($role)) sendError('Role must be a string', 400);
            $newUserId = $auth->register($data['email'], $data['password'], $data['name'], $role);
            $newUser = $db->table('users')
                ->select(['id', 'name', 'email', 'role', 'created_at'])
                ->where('id', $newUserId)
                ->first();
            sendJson($newUser);
        } catch (\Exception $e) {
            sendError($e->getMessage(), 400);
        }
        return true;
    }

    if (preg_match('#^/users/(\d+)/role$#', $path, $matches) && $method === 'PUT') {
        $targetUserId = (int)$matches[1];
        $data = getAuthJsonBody();
        if (!is_string($data['role'] ?? null)) sendError('Role must be a string', 400);
        try {
            $auth->changeRole($targetUserId, $data['role']);
        } catch (\OutOfBoundsException $e) { sendError($e->getMessage(), 404); }
        catch (\DomainException $e) { sendError($e->getMessage(), 409); }
        catch (\InvalidArgumentException $e) { sendError($e->getMessage(), 400); }
        catch (\Throwable $e) { sendError('Unable to change role', 500); }
        sendJson(['success'=>true,'reauthenticate'=>$targetUserId === $userId]);
        return true;
    }

    if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $targetUserId = (int)$matches[1];
        $raw = file_get_contents('php://input', false, null, 0, 8193);
        if (strlen($raw) > 8192) sendError('Password request is too large', 413);
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            sendError('Invalid JSON data', 400);
        }


        if (!is_array($data) || !is_string($data['password'] ?? null)) sendError('Password must be a string', 400);
        try {
            $auth->changePassword($targetUserId, $data['password']);
        } catch (\OutOfBoundsException $e) {
            sendError($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            sendError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('Password update failed');
            sendError('Unable to update password', 500);
        }
        sendJson(['success' => true, 'reauthenticate' => $targetUserId === $userId,
            'message' => 'Password updated; all sessions for this user have been revoked']);
        return true;
    }

    if (preg_match('#^/users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $targetUserId = (int)$matches[1];

        // Prevent deleting yourself
        if ($targetUserId === $userId) {
            sendError('Cannot delete your own account', 400);
        }

        try {
            $auth->deleteUser($targetUserId);
        } catch (\OutOfBoundsException $e) { sendError($e->getMessage(), 404); }
        catch (\DomainException $e) { sendError($e->getMessage(), 409); }
        catch (\Throwable $e) { sendError('Unable to delete user', 500); }
        sendJson(['success' => true, 'message' => 'User deleted successfully']);
        return true;
    }

    return false;
}
