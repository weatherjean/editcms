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
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
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
}
