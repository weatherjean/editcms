<?php

declare(strict_types=1);

namespace Edit\Core\Auth;

class JWT
{
    private string $secret;
    private string $algorithm = 'HS256';

    public function __construct(?string $secret = null)
    {
        // Use provided secret or generate one (should be stored in config)
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
     * Get or create a secret key
     */
    private function getOrCreateSecret(): string
    {
        $secretFile = dirname(__DIR__, 2) . '/config/jwt-secret.txt';

        if (file_exists($secretFile)) {
            return trim(file_get_contents($secretFile));
        }

        // Generate a new secret
        $secret = bin2hex(random_bytes(32));

        // Save it
        $dir = dirname($secretFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($secretFile, $secret);
        chmod($secretFile, 0600);

        return $secret;
    }
}
