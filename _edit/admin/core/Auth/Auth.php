<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

use Edit\Core\Database\Database;

class Auth
{
    private Database $db;
    private JWT $jwt;
    private ?int $currentUserId = null;

    public function __construct(Database $db, ?JWT $jwt = null)
    {
        $this->db = $db;
        $this->jwt = $jwt ?? new JWT(null, $db);
    }

    /**
     * Register a new user
     */
    public function register(string $email, string $password, string $name): int
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("Invalid email address");
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
     * Login user and return user data with token
     */
    public function login(string $email, string $password): ?array
    {
        // Find user by email
        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user) {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Generate token
        $token = $this->jwt->encode([
            'user_id' => $user['id'],
            'email' => $user['email']
        ], 86400); // 24 hours

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

        $payload = $this->jwt->decode($token);

        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        $this->currentUserId = (int) $payload['user_id'];
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
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            // Also check for alternative header
            $authHeader = apache_request_headers()['Authorization'] ?? '';
        }

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
