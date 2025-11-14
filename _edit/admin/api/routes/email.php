<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Email\Email;
use Edit\Core\Security\Security;

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
        requireFields($data, ['to', 'subject', 'message']);

        // Validate token
        requireFields($data, ['token']);

        // Validate email address
        if (!Security::validateEmail($data['to'])) {
            sendError('Invalid email address', 400);
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

        // Load email configuration from database (1 query instead of 7!)
        $settings = getSettings($db, [
            'email_from_address',
            'email_from_name',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption'
        ]);

        $smtpConfig = null;
        if (!empty($settings['smtp_host'] ?? '')) {
            // Decrypt password from database
            $decryptedPassword = '';
            if (!empty($settings['smtp_password'])) {
                $decryptedPassword = Security::decrypt($settings['smtp_password']) ?? '';
            }

            $smtpConfig = [
                'host' => $settings['smtp_host'] ?? '',
                'port' => $settings['smtp_port'] ?? 587,
                'username' => $settings['smtp_username'] ?? '',
                'password' => $decryptedPassword,
                'encryption' => $settings['smtp_encryption'] ?? 'tls'
            ];
        }

        $emailConfig = [
            'from_email' => $settings['email_from_address'] ?? '',
            'from_name' => $settings['email_from_name'] ?? ''
        ];

        // Create email instance with SMTP config
        $email = new Email($emailConfig['from_email'], $emailConfig['from_name'], $smtpConfig);

        // Determine if HTML and sanitize if needed
        $isHtml = $data['is_html'] ?? true;
        $message = $data['message'];

        // Sanitize HTML content to prevent XSS
        if ($isHtml) {
            $message = Security::sanitizeHTML($message);
        }

        // Send email (from address is always from settings, not user-controlled)
        $success = $email->send($data['to'], $data['subject'], $message, $isHtml);

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
        // Get settings from database (1 query instead of 7!)
        $settings = getSettings($db, [
            'email_from_address',
            'email_from_name',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption'
        ]);

        // Mask the password for security (don't send decrypted password to frontend)
        $maskedPassword = !empty($settings['smtp_password']) ? '********' : '';

        sendJson([
            'from_email' => $settings['email_from_address'] ?? '',
            'from_name' => $settings['email_from_name'] ?? '',
            'smtp_host' => $settings['smtp_host'] ?? '',
            'smtp_port' => $settings['smtp_port'] ?? '587',
            'smtp_username' => $settings['smtp_username'] ?? '',
            'smtp_password' => $maskedPassword,
            'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls'
        ]);
        return true;
    }

    // Update email settings
    if ($path === '/email-settings' && $method === 'PUT') {
        $data = getJsonBody();

        // Validate required fields
        requireFields($data, ['from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password']);

        // Validate email
        if (!Security::validateEmail($data['from_email'])) {
            sendError('Invalid from_email address', 400);
        }

        // Validate port
        if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
            sendError('Invalid smtp_port', 400);
        }

        // Save basic email settings
        saveSetting($db, 'email_from_address', $data['from_email']);
        saveSetting($db, 'email_from_name', $data['from_name']);

        // Save SMTP settings (required)
        saveSetting($db, 'smtp_host', $data['smtp_host']);
        saveSetting($db, 'smtp_port', $data['smtp_port']);
        saveSetting($db, 'smtp_username', $data['smtp_username']);

        // Encrypt password before storing (skip if masked placeholder)
        if ($data['smtp_password'] !== '********') {
            $encryptedPassword = Security::encrypt($data['smtp_password']);
            saveSetting($db, 'smtp_password', $encryptedPassword);
        }

        saveSetting($db, 'smtp_encryption', $data['smtp_encryption'] ?? 'tls');

        sendJson([
            'success' => true,
            'message' => 'Email settings updated successfully'
        ]);
        return true;
    }

    // Get email logs
    if ($path === '/email-logs' && $method === 'GET') {
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], EDIT_MAX_PAGE_LIMIT) : 50;
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
