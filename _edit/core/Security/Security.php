<?php

declare(strict_types=1);

namespace Edit\Core\Security;

/**
 * Security utilities for input validation, sanitization, and security checks
 */
class Security
{
    // Allowed MIME types for file uploads
    private const ALLOWED_MIME_TYPES = [
        // Images
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Video
        'video/mp4',
        'video/webm',
        'video/ogg',
        // Audio
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
    ];

    public static function uploadExtension(string $mime): ?string
    {
        return [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogg',
            'audio/mpeg' => 'mp3', 'audio/ogg' => 'oga', 'audio/wav' => 'wav',
        ][$mime] ?? null;
    }

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array entry
     * @param int|null $maxSize Maximum file size in bytes (null = use default)
     * @return array ['valid' => bool, 'error' => string|null, 'mime' => string|null]
     */
    public static function validateUpload(array $file, ?int $maxSize = null): array
    {
        $maxSize = $maxSize ?? EDIT_MAX_FILE_SIZE;

        // Check file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File was not uploaded properly', 'mime' => null];
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB", 'mime' => null];
        }

        // Detect actual MIME type (not just from filename)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Validate MIME type
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => "File type '{$mimeType}' is not allowed", 'mime' => $mimeType];
        }

        // Additional validation for images
        if (strpos($mimeType, 'image/') === 0) {
            $imageValidation = self::validateImage($file['tmp_name'], $mimeType);
            if (!$imageValidation['valid']) {
                return $imageValidation;
            }
        }

        return ['valid' => true, 'error' => null, 'mime' => $mimeType];
    }

    /**
     * Validate image file
     */
    private static function validateImage(string $filePath, string $mimeType): array
    {
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'Invalid image file', 'mime' => $mimeType];
        }

        [$width, $height] = $imageInfo;

        // Check dimensions
        if ($width > EDIT_MAX_IMAGE_WIDTH || $height > EDIT_MAX_IMAGE_HEIGHT) {
            return [
                'valid' => false,
                'error' => 'Image dimensions too large (max ' . EDIT_MAX_IMAGE_WIDTH . 'x' . EDIT_MAX_IMAGE_HEIGHT . ')',
                'mime' => $mimeType
            ];
        }

        return ['valid' => true, 'error' => null, 'mime' => $mimeType];
    }

    /**
     * Sanitize HTML content (for email, wysiwyg fields)
     * Strips dangerous tags and attributes while preserving safe formatting
     *
     * @param string $html HTML content to sanitize
     * @return string Sanitized HTML
     */
    public static function sanitizeHTML(string $html): string
    {
        return HtmlSanitizer::clean($html);
    }

    /**
     * Validate email address format
     *
     * @param string $email Email address to validate
     * @return bool True if valid
     */
    public static function validateEmail(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional check: must contain @ and domain
        if (!preg_match('/^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            return false;
        }

        return true;
    }

    /**
     * Validate password strength
     *
     * @param string $password Password to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validatePassword(string $password): array
    {
        // Minimum length
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        // Maximum length (prevent DoS via bcrypt)
        if (strlen($password) > 72) {
            return ['valid' => false, 'error' => 'Password must be less than 72 characters'];
        }

        // Must contain at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
        }

        // Must contain at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
        }

        // Must contain at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one number'];
        }

        // Check against common passwords
        $commonPasswords = [
            'password123', 'Password123', 'Password123!',
            'Admin123456', 'Welcome123', 'Qwerty123',
            '123456789abc', 'Passw0rd!', 'P@ssw0rd',
            'Password1!', 'Admin@123'
        ];

        if (in_array($password, $commonPasswords)) {
            return ['valid' => false, 'error' => 'Password is too common'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate ZIP file size (prevent decompression bombs)
     *
     * @param string $zipPath Path to ZIP file
     * @param int $maxCompressedSize Maximum compressed size in bytes
     * @param int $maxUncompressedSize Maximum uncompressed size in bytes
     * @param int $maxFiles Maximum number of files in ZIP
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateZIP(
        string $zipPath,
        ?int $maxCompressedSize = null,
        ?int $maxUncompressedSize = null,
        ?int $maxFiles = null
    ): array {
        $maxCompressedSize = $maxCompressedSize ?? EDIT_MAX_ZIP_COMPRESSED_SIZE;
        $maxUncompressedSize = $maxUncompressedSize ?? EDIT_MAX_ZIP_UNCOMPRESSED_SIZE;
        $maxFiles = $maxFiles ?? EDIT_MAX_ZIP_FILES;
        // Check compressed file size
        $compressedSize = filesize($zipPath);
        if ($compressedSize > $maxCompressedSize) {
            $maxMB = round($maxCompressedSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "ZIP file exceeds maximum compressed size of {$maxMB}MB"];
        }

        // Open ZIP to check contents
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['valid' => false, 'error' => 'Invalid ZIP file'];
        }

        // Check number of files
        $numFiles = $zip->numFiles;
        if ($numFiles > $maxFiles) {
            $zip->close();
            return ['valid' => false, 'error' => "ZIP contains too many files (max {$maxFiles})"];
        }

        // Check uncompressed size
        $totalUncompressed = 0;
        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $totalUncompressed += $stat['size'];

            // Early exit if too large
            if ($totalUncompressed > $maxUncompressedSize) {
                $zip->close();
                $maxMB = round($maxUncompressedSize / 1024 / 1024, 1);
                return ['valid' => false, 'error' => "ZIP uncompressed size exceeds {$maxMB}MB (possible decompression bomb)"];
            }
        }

        // Check compression ratio (potential bomb if > 100:1)
        $compressionRatio = $totalUncompressed / $compressedSize;
        if ($compressionRatio > 100) {
            $zip->close();
            return ['valid' => false, 'error' => 'Suspicious compression ratio detected (possible ZIP bomb)'];
        }

        $zip->close();
        return ['valid' => true, 'error' => null];
    }

    /**
     * Add security headers to response
     */
    public static function addSecurityHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // XSS protection (legacy but still useful)
        header('X-XSS-Protection: 1; mode=block');

        // Content Security Policy
        // Adjust as needed for your application
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // unsafe-* needed for Vue/Vite in dev
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ]);
        header("Content-Security-Policy: {$csp}");

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS (only if using HTTPS)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Encrypt sensitive data (for storing SMTP passwords, etc.)
     * Uses EDIT_ENCRYPTION_KEY from config.php
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     */
    public static function encrypt(string $data): string
    {
        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $key = EDIT_ENCRYPTION_KEY;

        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        // Combine IV + encrypted data + tag and base64 encode
        return base64_encode($iv . $encrypted . $tag);
    }

    /**
     * Decrypt sensitive data
     * Uses EDIT_ENCRYPTION_KEY from config.php
     *
     * @param string $encrypted Encrypted data (base64 encoded)
     * @return string|null Decrypted data or null on failure
     */
    public static function decrypt(string $encrypted): ?string
    {
        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        $tagLength = 16;
        $key = EDIT_ENCRYPTION_KEY;

        $data = base64_decode($encrypted);
        if ($data === false) {
            return null;
        }

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, -$tagLength);
        $ciphertext = substr($data, $ivLength, -$tagLength);

        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Generate a cryptographically secure random token
     *
     * @param int $length Number of random bytes to generate (default: 32)
     * @return string Hex-encoded token string (length * 2 characters)
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
