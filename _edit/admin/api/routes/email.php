<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Email\Email;

/**
 * Public email routes (no auth required)
 *
 * Routes:
 * - GET /send-email/token - Generate single-use email token
 * - POST /send-email - Send email with valid token
 */
function handlePublicEmailRoutes(string $method, string $path, Database $db): bool
{
    // Generate single-use email token (valid for 30 seconds)
    if ($path === '/send-email/token' && $method === 'GET') {
        // Rate limit: 10 tokens per hour
        checkRateLimit($db, 'email-token', 10, 60);

        // Clean up expired tokens as a side effect
        $db->cleanupExpiredEmailTokens();

        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 30); // 30 seconds from now

        // Store token
        $db->execute(
            "INSERT INTO email_tokens (token, expires_at) VALUES (?, ?)",
            [$token, $expiresAt]
        );

        sendJson([
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => 30
        ]);
        return true;
    }

    // Send email (requires valid single-use token)
    if ($path === '/send-email' && $method === 'POST') {
        // Rate limit: 20 emails per hour (backup protection beyond tokens)
        checkRateLimit($db, 'email-send', 20, 60);

        // Clean up expired tokens as a side effect
        $db->cleanupExpiredEmailTokens();

        $data = getJsonBody();

        // Validate required fields
        if (!isset($data['to']) || !isset($data['subject']) || !isset($data['message'])) {
            sendError('Missing required fields: to, subject, message', 400);
        }

        // Validate token
        if (!isset($data['token']) || empty($data['token'])) {
            sendError('Missing email token. Call GET /send-email/token first.', 400);
        }

        // Check token exists and is not expired
        $tokenCheck = $db->query(
            "SELECT expires_at FROM email_tokens WHERE token = ?",
            [$data['token']]
        );

        if (empty($tokenCheck)) {
            sendError('Invalid or already used email token', 403);
        }

        if (strtotime($tokenCheck[0]['expires_at']) < time()) {
            sendError('Email token expired', 403);
        }

        // Delete token (single-use - delete immediately)
        $db->execute(
            "DELETE FROM email_tokens WHERE token = ?",
            [$data['token']]
        );

        // Load email configuration from database
        $fromEmail = $db->query("SELECT value FROM settings WHERE key = 'email_from_address'");
        $fromName = $db->query("SELECT value FROM settings WHERE key = 'email_from_name'");

        // Load SMTP configuration
        $smtpHost = $db->query("SELECT value FROM settings WHERE key = 'smtp_host'");
        $smtpPort = $db->query("SELECT value FROM settings WHERE key = 'smtp_port'");
        $smtpUsername = $db->query("SELECT value FROM settings WHERE key = 'smtp_username'");
        $smtpPassword = $db->query("SELECT value FROM settings WHERE key = 'smtp_password'");
        $smtpEncryption = $db->query("SELECT value FROM settings WHERE key = 'smtp_encryption'");

        $smtpConfig = null;
        if (!empty($smtpHost[0]['value'] ?? '')) {
            $smtpConfig = [
                'host' => $smtpHost[0]['value'] ?? '',
                'port' => $smtpPort[0]['value'] ?? 587,
                'username' => $smtpUsername[0]['value'] ?? '',
                'password' => $smtpPassword[0]['value'] ?? '',
                'encryption' => $smtpEncryption[0]['value'] ?? 'tls'
            ];
        }

        $emailConfig = [
            'from_email' => $fromEmail[0]['value'] ?? '',
            'from_name' => $fromName[0]['value'] ?? ''
        ];

        // Create email instance with SMTP config
        $email = new Email($emailConfig['from_email'], $emailConfig['from_name'], $smtpConfig);

        // Allow overriding from address (if provided)
        if (isset($data['from_email'])) {
            $email->setFrom($data['from_email'], $data['from_name'] ?? '');
        }

        // Send email
        $isHtml = $data['is_html'] ?? true;
        $success = $email->send($data['to'], $data['subject'], $data['message'], $isHtml);

        // Get IP address for logging
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // Log the email send attempt
        if ($success) {
            $db->execute(
                "INSERT INTO email_logs (to_address, subject, success, ip_address) VALUES (?, ?, 1, ?)",
                [$data['to'], $data['subject'], $ipAddress]
            );
            sendJson(['success' => true, 'message' => 'Email sent successfully']);
        } else {
            $errorMessage = $email->getLastError() ?: 'Unknown error';
            $db->execute(
                "INSERT INTO email_logs (to_address, subject, success, error_message, ip_address) VALUES (?, ?, 0, ?, ?)",
                [$data['to'], $data['subject'], $errorMessage, $ipAddress]
            );
            sendError('Failed to send email: ' . $errorMessage, 500);
        }
        return true;
    }

    return false;
}

/**
 * Email admin routes (requires auth)
 *
 * Routes:
 * - GET /email-settings - Get email/SMTP settings
 * - PUT /email-settings - Update email/SMTP settings
 * - GET /email-logs - Get email send logs
 */
function handleEmailAdminRoutes(string $method, string $path, Database $db): bool
{
    // Get email settings
    if ($path === '/email-settings' && $method === 'GET') {
        // Get settings from database
        $fromEmail = $db->query("SELECT value FROM settings WHERE key = 'email_from_address'");
        $fromName = $db->query("SELECT value FROM settings WHERE key = 'email_from_name'");

        // Get SMTP settings
        $smtpHost = $db->query("SELECT value FROM settings WHERE key = 'smtp_host'");
        $smtpPort = $db->query("SELECT value FROM settings WHERE key = 'smtp_port'");
        $smtpUsername = $db->query("SELECT value FROM settings WHERE key = 'smtp_username'");
        $smtpPassword = $db->query("SELECT value FROM settings WHERE key = 'smtp_password'");
        $smtpEncryption = $db->query("SELECT value FROM settings WHERE key = 'smtp_encryption'");

        sendJson([
            'from_email' => $fromEmail[0]['value'] ?? '',
            'from_name' => $fromName[0]['value'] ?? '',
            'smtp_host' => $smtpHost[0]['value'] ?? '',
            'smtp_port' => $smtpPort[0]['value'] ?? '587',
            'smtp_username' => $smtpUsername[0]['value'] ?? '',
            'smtp_password' => $smtpPassword[0]['value'] ?? '',
            'smtp_encryption' => $smtpEncryption[0]['value'] ?? 'tls'
        ]);
        return true;
    }

    // Update email settings
    if ($path === '/email-settings' && $method === 'PUT') {
        $data = getJsonBody();

        // Validate required fields
        $required = ['from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendError("{$field} is required", 400);
            }
        }

        // Validate email
        if (!filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
            sendError('Invalid from_email address', 400);
        }

        // Validate port
        if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
            sendError('Invalid smtp_port', 400);
        }

        // Helper function to save setting
        $saveSetting = function($key, $value) use ($db) {
            $db->execute("
                INSERT INTO settings (key, value, updated_at)
                VALUES (?, ?, datetime('now'))
                ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')
            ", [$key, $value]);
        };

        // Save basic email settings
        $saveSetting('email_from_address', $data['from_email']);
        $saveSetting('email_from_name', $data['from_name']);

        // Save SMTP settings (required)
        $saveSetting('smtp_host', $data['smtp_host']);
        $saveSetting('smtp_port', $data['smtp_port']);
        $saveSetting('smtp_username', $data['smtp_username']);
        $saveSetting('smtp_password', $data['smtp_password']);
        $saveSetting('smtp_encryption', $data['smtp_encryption'] ?? 'tls');

        sendJson([
            'success' => true,
            'message' => 'Email settings updated successfully'
        ]);
        return true;
    }

    // Get email logs
    if ($path === '/email-logs' && $method === 'GET') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $logs = $db->query(
            "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );

        // Get total count
        $totalResult = $db->query("SELECT COUNT(*) as total FROM email_logs");
        $total = $totalResult[0]['total'] ?? 0;

        sendJson([
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        return true;
    }

    return false;
}
