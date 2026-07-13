<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

use Edit\Core\Database\Database;
use Edit\Core\Security\Security;

class Auth
{
    private Database $db;
    private ?int $currentUserId = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, string $name, string $role = 'admin'): int
    {
        if (!in_array($role, ['admin','editor'], true)) throw new \InvalidArgumentException('Invalid role');
        if (trim($name) === '' || strlen($name) > 255) throw new \InvalidArgumentException('Name is required and must be at most 255 bytes');
        if (!Security::validateEmail($email)) {
            throw new \RuntimeException("Invalid email address");
        }

        $passwordValidation = Security::validatePassword($password);
        if (!$passwordValidation['valid']) {
            throw new \RuntimeException($passwordValidation['error']);
        }

        $count = $this->db->table('users')->where('email', $email)->count();
        if ($count > 0) {
            throw new \RuntimeException("Email already registered");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        return $this->db->table('users')->insert([
            'email' => $email,
            'password' => $hashedPassword,
            'name' => trim($name),
            'role' => $role
        ]);
    }

    /**
     * Login user and return user data with session token
     */
    public function login(string $email, string $password): ?array
    {
        $this->db->cleanupExpiredSessions();

        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        $token = Security::generateToken();
        $expiresAt = dateTime('+' . EDIT_SESSION_EXPIRY_HOURS . ' hours');

        // A concurrent password reset must not issue a session for the old hash.
        $inserted = $this->db->execute(
            'INSERT INTO sessions (token, user_id, expires_at) SELECT ?, id, ? FROM users WHERE id = ? AND password = ?',
            [$token, $expiresAt, $user['id'], $user['password']]
        );
        if ($inserted !== 1) return null;

        unset($user['password']);

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function changeRole(int $userId, string $role): void
    {
        if (!in_array($role, ['admin','editor'], true)) throw new \InvalidArgumentException('Invalid role');
        $this->mutateAccount($userId, function (array $user) use ($userId,$role): void {
            if ($user['role'] === $role) return;
            if ($user['role'] === 'admin' && $role !== 'admin') $this->requireAnotherAdmin();
            $this->db->table('users')->where('id',$userId)->update(['role'=>$role]);
            $this->logoutAll($userId);
        });
    }

    public function deleteUser(int $userId): void
    {
        $this->mutateAccount($userId, function (array $user) use ($userId): void {
            if ($user['role'] === 'admin') $this->requireAnotherAdmin();
            $this->db->table('users')->where('id',$userId)->delete();
        });
    }

    private function requireAnotherAdmin(): void
    {
        if ($this->db->table('users')->where('role','admin')->count() <= 1) throw new \DomainException('Cannot remove the last administrator');
    }

    private function mutateAccount(int $userId, callable $operation): void
    {
        $pdo = $this->db->getPdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $user = $this->db->table('users')->where('id',$userId)->first();
            if (!$user) throw new \OutOfBoundsException('User not found');
            $operation($user);
            $pdo->exec('COMMIT');
        } catch (\Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
    }

    /** Password replacement and revocation are one atomic operation. */
    public function changePassword(int $userId, string $password): void
    {
        $validation = Security::validatePassword($password);
        if (!$validation['valid']) throw new \InvalidArgumentException($validation['error']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->beginTransaction();
        try {
            if ($this->db->table('users')->where('id', $userId)->update(['password' => $hash]) !== 1) {
                throw new \OutOfBoundsException('User not found');
            }
            $this->logoutAll($userId);
            $this->db->commit();
            if ($this->currentUserId === $userId) $this->currentUserId = null;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Verify token and return user ID
     */
    public function verifyToken(string $token): ?int
    {
        $this->currentUserId = null;
        if (empty($token)) {
            return null;
        }

        $session = $this->db->table('sessions')
            ->where('token', $token)
            ->first();

        if (!$session) {
            return null;
        }

        if (strtotime($session['expires_at']) <= time()) {
            $this->db->table('sessions')->where('id', $session['id'])->delete();
            return null;
        }

        $this->currentUserId = (int) $session['user_id'];
        return $this->currentUserId;
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->currentUserId) {
            return null;
        }

        return $this->db->table('users')
            ->select(['id', 'email', 'name', 'role', 'created_at'])
            ->where('id', $this->currentUserId)
            ->first();
    }

    /**
     * Verify token from HTTP Authorization header
     */
    public function verifyRequest(): ?int
    {
        $authHeader = getAuthHeader();
        return $this->verifyToken($authHeader);
    }

    /**
     * Set current user (for testing or manual authentication)
     */
    public function setCurrentUser(int $userId): void
    {
        $this->currentUserId = $userId;
    }

    /**
     * Logout user by removing their session
     */
    public function logout(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $deleted = $this->db->table('sessions')
            ->where('token', $token)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Logout all sessions for a user
     */
    public function logoutAll(int $userId): int
    {
        return $this->db->table('sessions')
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUserId !== null;
    }

    /**
     * Get current user ID
     */
    public function getCurrentUserId(): ?int
    {
        return $this->currentUserId;
    }
}
