<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

use Edit\Core\Database\Database;

final class Installation
{
    public function __construct(private Database $db)
    {
    }

    private function transaction(callable $fn): mixed
    {
        $pdo = $this->db->getPdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $fn();
            $pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /** Called only by the local CLI. Only the hash is persisted. */
    public function issueToken(): string
    {
        return $this->transaction(function (): string {
            if ($this->db->table('users')->count() > 0) {
                throw new \RuntimeException('Installation is already claimed');
            }
            $token = bin2hex(random_bytes(32));
            foreach (['setup_token_hash' => hash('sha256', $token), 'setup_token_expires' => (string)(time() + 3600)] as $key => $value) {
                $this->db->execute('INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]);
            }
            return $token;
        });
    }

    public function claim(string $token, string $email, string $password, string $name): int
    {
        return $this->transaction(function () use ($token, $email, $password, $name): int {
            if ($this->db->table('users')->count() > 0) {
                throw new \DomainException('Registration is disabled');
            }
            $hash = $this->db->table('settings')->where('key', 'setup_token_hash')->first()['value'] ?? '';
            $expires = $this->db->table('settings')->where('key', 'setup_token_expires')->first()['value'] ?? '0';
            if (strlen($token) !== 64 || $hash === '' || (int)$expires <= time() || !hash_equals($hash, hash('sha256', $token))) {
                throw new \DomainException('Invalid or expired setup code');
            }
            $id = (new Auth($this->db))->register($email, $password, $name, 'admin');
            $this->db->execute("DELETE FROM settings WHERE key IN ('setup_token_hash','setup_token_expires')");
            return $id;
        });
    }
}
