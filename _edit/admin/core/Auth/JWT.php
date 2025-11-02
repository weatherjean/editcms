<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

use Edit\Core\Database\Database;

class JWT
{
    private string $secret;
    private string $algorithm = 'HS256';
    private ?Database $db = null;

    public function __construct(?string $secret = null, ?Database $db = null)
    {
        $this->db = $db;
        // Use provided secret or get/create from database
        $this->secret = $secret ?? $this->getOrCreateSecret();
    }

    /**
     * Encode a payload into a JWT token
     */
    public function encode(array $payload, int $expiry = 3600): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Decode and verify a JWT token
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Sign data with secret
     */
    private function sign(string $data): string
    {
        $signature = hash_hmac('sha256', $data, $this->secret, true);
        return $this->base64UrlEncode($signature);
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get or create a secret key from database
     */
    private function getOrCreateSecret(): string
    {
        // If no database available, fall back to a temporary secret (shouldn't happen in practice)
        if (!$this->db) {
            return bin2hex(random_bytes(32));
        }

        // Try to get existing secret from settings table
        $result = $this->db->query("SELECT value FROM settings WHERE key = ?", ['jwt_secret']);

        if (!empty($result)) {
            return $result[0]['value'];
        }

        // Check if old file-based secret exists (migration path)
        $secretFile = dirname(__DIR__, 2) . '/config/jwt-secret.txt';
        if (file_exists($secretFile)) {
            $secret = trim(file_get_contents($secretFile));

            // Migrate to database
            $this->db->execute(
                "INSERT INTO settings (key, value) VALUES (?, ?)",
                ['jwt_secret', $secret]
            );

            // Optionally delete the file after migration
            // Commented out to be safe - can be manually deleted
            // @unlink($secretFile);

            return $secret;
        }

        // Generate a new secret
        $secret = bin2hex(random_bytes(32));

        // Save it to database
        $this->db->execute(
            "INSERT INTO settings (key, value) VALUES (?, ?)",
            ['jwt_secret', $secret]
        );

        return $secret;
    }
}
