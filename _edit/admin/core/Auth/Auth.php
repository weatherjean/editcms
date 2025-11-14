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
    public function register(string $email, string $password, string $name): int
    {
        // Validate email
        if (!Security::validateEmail($email)) {
            throw new \RuntimeException("Invalid email address");
        }

        // Validate password strength
        $passwordValidation = Security::validatePassword($password);
        if (!$passwordValidation['valid']) {
            throw new \RuntimeException($passwordValidation['error']);
        }

        // Check if email already exists
        $count = $this->db->table('users')->where('email', $email)->count();
        if ($count > 0) {
            throw new \RuntimeException("Email already registered");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        return $this->db->table('users')->insert([
            'email' => $email,
            'password' => $hashedPassword,
            'name' => $name
        ]);
    }

    /**
     * Login user and return user data with session token
     */
    public function login(string $email, string $password): ?array
    {
        // Cleanup expired sessions opportunistically
        $this->db->cleanupExpiredSessions();

        // Find user by email
        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user) {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (EDIT_SESSION_EXPIRY_HOURS * 3600));

        // Store session in database
        $this->db->table('sessions')->insert([
            'token' => $token,
            'user_id' => $user['id'],
            'expires_at' => $expiresAt
        ]);

        // Return user data without password
        unset($user['password']);

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    /**
     * Verify token and return user ID
     */
    public function verifyToken(string $token): ?int
    {
        if (empty($token)) {
            return null;
        }

        // Remove "Bearer " prefix if present
        $token = preg_replace('/^Bearer\s+/', '', $token);

        // Look up session in database
        $session = $this->db->table('sessions')
            ->where('token', $token)
            ->first();

        if (!$session) {
            return null; // Session not found
        }

        // Check if session has expired
        if (strtotime($session['expires_at']) < time()) {
            // Clean up expired session
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
            ->select(['id', 'email', 'name', 'created_at'])
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

        // Remove "Bearer " prefix if present
        $token = preg_replace('/^Bearer\s+/', '', $token);

        // Delete session from database
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
