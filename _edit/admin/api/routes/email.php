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
        $db->table('email_tokens')->insert([
            'token' => $token,
            'expires_at' => $expiresAt
        ]);

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
        $tokenCheck = $db->table('email_tokens')
            ->select(['expires_at'])
            ->where('token', $data['token'])
            ->first();

        if (empty($tokenCheck)) {
            sendError('Invalid or already used email token', 403);
        }

        if (strtotime($tokenCheck['expires_at']) < time()) {
            sendError('Email token expired', 403);
        }

        // Delete token (single-use - delete immediately)
        $db->table('email_tokens')
            ->where('token', $data['token'])
            ->delete();

        // Load email configuration from database
        $fromEmail = $db->table('settings')
            ->select(['value'])
            ->where('key', 'email_from_address')
            ->first();
        $fromName = $db->table('settings')
            ->select(['value'])
            ->where('key', 'email_from_name')
            ->first();

        // Load SMTP configuration
        $smtpHost = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_host')
            ->first();
        $smtpPort = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_port')
            ->first();
        $smtpUsername = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_username')
            ->first();
        $smtpPassword = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_password')
            ->first();
        $smtpEncryption = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_encryption')
            ->first();

        $smtpConfig = null;
        if (!empty($smtpHost['value'] ?? '')) {
            $smtpConfig = [
                'host' => $smtpHost['value'] ?? '',
                'port' => $smtpPort['value'] ?? 587,
                'username' => $smtpUsername['value'] ?? '',
                'password' => $smtpPassword['value'] ?? '',
                'encryption' => $smtpEncryption['value'] ?? 'tls'
            ];
        }

        $emailConfig = [
            'from_email' => $fromEmail['value'] ?? '',
            'from_name' => $fromName['value'] ?? ''
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
            $db->table('email_logs')->insert([
                'to_address' => $data['to'],
                'subject' => $data['subject'],
                'success' => 1,
                'ip_address' => $ipAddress
            ]);
            sendJson(['success' => true, 'message' => 'Email sent successfully']);
        } else {
            $errorMessage = $email->getLastError() ?: 'Unknown error';
            $db->table('email_logs')->insert([
                'to_address' => $data['to'],
                'subject' => $data['subject'],
                'success' => 0,
                'error_message' => $errorMessage,
                'ip_address' => $ipAddress
            ]);
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
        $fromEmail = $db->table('settings')
            ->select(['value'])
            ->where('key', 'email_from_address')
            ->first();
        $fromName = $db->table('settings')
            ->select(['value'])
            ->where('key', 'email_from_name')
            ->first();

        // Get SMTP settings
        $smtpHost = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_host')
            ->first();
        $smtpPort = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_port')
            ->first();
        $smtpUsername = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_username')
            ->first();
        $smtpPassword = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_password')
            ->first();
        $smtpEncryption = $db->table('settings')
            ->select(['value'])
            ->where('key', 'smtp_encryption')
            ->first();

        sendJson([
            'from_email' => $fromEmail['value'] ?? '',
            'from_name' => $fromName['value'] ?? '',
            'smtp_host' => $smtpHost['value'] ?? '',
            'smtp_port' => $smtpPort['value'] ?? '587',
            'smtp_username' => $smtpUsername['value'] ?? '',
            'smtp_password' => $smtpPassword['value'] ?? '',
            'smtp_encryption' => $smtpEncryption['value'] ?? 'tls'
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
            $existing = $db->table('settings')
                ->where('key', $key)
                ->first();

            if ($existing) {
                $db->table('settings')
                    ->where('key', $key)
                    ->update([
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                $db->table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
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

        $logs = $db->table('email_logs')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        // Get total count
        $total = $db->table('email_logs')->count();

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
