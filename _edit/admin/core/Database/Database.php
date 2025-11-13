<?php

declare(strict_types=1);

namespace Edit\Core\Database;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private PDO $pdo;
    private string $dbPath;

    public function __construct(string $path)
    {
        $this->dbPath = $path;
        $this->connect();
        $this->initialize();
    }

    private function connect(): void
    {
        try {
            // Ensure directory exists
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO(
                "sqlite:{$this->dbPath}",
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Enable foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: {$e->getMessage()}");
        }
    }

    private function initialize(): void
    {
        // Check if tables exist
        $tableCheck = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='content'"
        );

        $isNewDatabase = empty($tableCheck);

        if ($isNewDatabase) {
            $this->createSchema();
        } else {
            // For existing databases, ensure new tables exist (migrations)
            $this->runMigrations();
        }
    }

    private function runMigrations(): void
    {
        // Check if settings table exists
        $settingsCheck = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='settings'"
        );

        if (empty($settingsCheck)) {
            // Create settings table
            $this->execute("
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }

        // Check if email_tokens table exists
        $emailTokensCheck = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='email_tokens'"
        );

        if (empty($emailTokensCheck)) {
            // Create email_tokens table for single-use email sending tokens
            $this->execute("
                CREATE TABLE IF NOT EXISTS email_tokens (
                    token TEXT PRIMARY KEY,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Add index for cleanup queries
            $this->execute("CREATE INDEX IF NOT EXISTS idx_email_tokens_expires ON email_tokens(expires_at)");
        }

        // Check if email_logs table exists
        $emailLogsCheck = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='email_logs'"
        );

        if (empty($emailLogsCheck)) {
            // Create email_logs table to track all email sends
            $this->execute("
                CREATE TABLE IF NOT EXISTS email_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    to_address TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    success INTEGER DEFAULT 0,
                    error_message TEXT,
                    ip_address TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Add index for querying logs
            $this->execute("CREATE INDEX IF NOT EXISTS idx_email_logs_created ON email_logs(created_at DESC)");
        }

        // Check if rate_limits table exists
        $rateLimitsCheck = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='rate_limits'"
        );

        if (empty($rateLimitsCheck)) {
            // Create rate_limits table for tracking request attempts
            $this->execute("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    endpoint TEXT NOT NULL,
                    attempts INTEGER DEFAULT 1,
                    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
                    locked_until DATETIME,
                    UNIQUE(ip_address, endpoint)
                )
            ");

            // Add indexes for rate limit queries
            $this->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_endpoint ON rate_limits(ip_address, endpoint)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_window ON rate_limits(window_start)");
        }
    }

    private function createSchema(): void
    {
        $this->beginTransaction();

        try {
            // Content table - single table for all content types
            // Only stores core fields: id, type, slug, status, author_id, dates
            // Everything else goes in content_meta
            $this->execute("
                CREATE TABLE IF NOT EXISTS content (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    slug TEXT NOT NULL CHECK(length(slug) > 0),
                    status TEXT DEFAULT 'draft',
                    author_id INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(type, slug),
                    FOREIGN KEY (author_id) REFERENCES users(id)
                )
            ");

            // Content meta - WordPress-style meta table
            $this->execute("
                CREATE TABLE IF NOT EXISTS content_meta (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    content_id INTEGER NOT NULL,
                    meta_key TEXT NOT NULL,
                    meta_value TEXT,
                    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
                )
            ");

            // Indexes for content
            $this->execute("CREATE INDEX IF NOT EXISTS idx_content_type ON content(type)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_content_slug ON content(slug)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_content_status ON content(status)");

            // Indexes for content_meta
            $this->execute("CREATE INDEX IF NOT EXISTS idx_meta_content_id ON content_meta(content_id)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_meta_key ON content_meta(meta_key)");

            // Users table
            $this->execute("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    name TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Media table
            $this->execute("
                CREATE TABLE IF NOT EXISTS media (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    filename TEXT NOT NULL,
                    path TEXT NOT NULL,
                    mime_type TEXT,
                    size INTEGER,
                    alt_text TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Settings table - key-value pairs for system settings
            $this->execute("
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Email tokens table - single-use tokens for email sending
            $this->execute("
                CREATE TABLE IF NOT EXISTS email_tokens (
                    token TEXT PRIMARY KEY,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->execute("CREATE INDEX IF NOT EXISTS idx_email_tokens_expires ON email_tokens(expires_at)");

            // Email logs table - track all email sends
            $this->execute("
                CREATE TABLE IF NOT EXISTS email_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    to_address TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    success INTEGER DEFAULT 0,
                    error_message TEXT,
                    ip_address TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->execute("CREATE INDEX IF NOT EXISTS idx_email_logs_created ON email_logs(created_at DESC)");

            // Rate limits table - track API request attempts
            $this->execute("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    endpoint TEXT NOT NULL,
                    attempts INTEGER DEFAULT 1,
                    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
                    locked_until DATETIME,
                    UNIQUE(ip_address, endpoint)
                )
            ");

            $this->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_endpoint ON rate_limits(ip_address, endpoint)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_rate_limits_window ON rate_limits(window_start)");

            $this->commit();
        } catch (PDOException $e) {
            $this->rollback();
            throw new \RuntimeException("Schema creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Execute a SELECT query and return results
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new \RuntimeException("Query failed: {$e->getMessage()}");
        }
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query
     * Returns the number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new \RuntimeException("Execute failed: {$e->getMessage()}");
        }
    }

    /**
     * Get the ID of the last inserted row
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Get the underlying PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Clean up expired email tokens
     * This is called opportunistically as a side effect of email operations
     */
    public function cleanupExpiredEmailTokens(): void
    {
        try {
            $this->execute(
                "DELETE FROM email_tokens WHERE expires_at < datetime('now')"
            );
        } catch (\Exception $e) {
            // Silently fail - this is a cleanup operation, not critical
            // Log if you have logging system
        }
    }

    /**
     * Create a new query builder instance for the given table
     *
     * Example usage:
     *   $users = $db->table('users')
     *       ->where('status', 'active')
     *       ->orderBy('created_at', 'DESC')
     *       ->limit(10)
     *       ->get();
     */
    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this))->table($table);
    }
}
