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
    if ($path === '/send-email/token' && $method === 'GET') {
        // Rate limit: 10 tokens per hour
        checkRateLimit($db, 'email-token', 10, 60);

        $db->cleanupExpiredEmailTokens();

        $token = Security::generateToken();
        $expiresAt = dateTime('+30 seconds');

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

    if ($path === '/send-email' && $method === 'POST') {
        checkRateLimit($db, 'email-send', 20, 60);

        $db->cleanupExpiredEmailTokens();

        $data = getJsonBody();

        requireFields($data, ['to', 'subject', 'message']);

        requireFields($data, ['token']);

        if (!Security::validateEmail($data['to'])) {
            sendError('Invalid email address', 400);
        }

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

        $db->table('email_tokens')
            ->where('token', $data['token'])
            ->delete();

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

        $email = new Email($emailConfig['from_email'], $emailConfig['from_name'], $smtpConfig);

        $isHtml = $data['is_html'] ?? true;
        $message = $data['message'];

        if ($isHtml) {
            $message = Security::sanitizeHTML($message);
        }

        $success = $email->send($data['to'], $data['subject'], $message, $isHtml);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

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
    if ($path === '/email-settings' && $method === 'GET') {
        $settings = getSettings($db, [
            'email_from_address',
            'email_from_name',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption'
        ]);

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

    if ($path === '/email-settings' && $method === 'PUT') {
        $data = getJsonBody();

        requireFields($data, ['from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password']);

        if (!Security::validateEmail($data['from_email'])) {
            sendError('Invalid from_email address', 400);
        }

        if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
            sendError('Invalid smtp_port', 400);
        }

        saveSetting($db, 'email_from_address', $data['from_email']);
        saveSetting($db, 'email_from_name', $data['from_name']);

        saveSetting($db, 'smtp_host', $data['smtp_host']);
        saveSetting($db, 'smtp_port', $data['smtp_port']);
        saveSetting($db, 'smtp_username', $data['smtp_username']);

        // Only update password if it's different from the masked value
        // This allows updating even if the actual password is '********'
        $currentSettings = getSettings($db, ['smtp_password']);
        $currentEncryptedPassword = $currentSettings['smtp_password'] ?? '';
        $maskedPassword = !empty($currentEncryptedPassword) ? '********' : '';

        if ($data['smtp_password'] !== $maskedPassword || empty($currentEncryptedPassword)) {
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

    if ($path === '/email-logs' && $method === 'GET') {
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], EDIT_MAX_PAGE_LIMIT) : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $logs = $db->table('email_logs')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

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
