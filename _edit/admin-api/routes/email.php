<?php

declare(strict_types=1);

use Edit\Core\Database\Database;
use Edit\Core\Email\Email;
use Edit\Core\Security\Captcha;
use Edit\Core\Security\Security;

/** Public contact-form endpoints, shared by both API entry points. */
function handlePublicEmailRoutes(string $method, string $path, Database $db): bool
{
    if ($path === '/captcha') {
        requireMethod($method, 'GET');
        header('Cache-Control: no-store');
        checkRateLimit($db, 'captcha', 10, 1);
        sendJson((new Captcha($db, EDIT_ENCRYPTION_KEY))->issue());
    }

    if ($path === '/send-email/token') {
        header('Cache-Control: no-store');
        sendError('Email tokens have been retired. Submit an ALTCHA proof with your message.', 410);
    }

    if ($path === '/send-email') {
        requireMethod($method, 'POST');
        header('Cache-Control: no-store');
        checkRateLimit($db, 'email-send', 20, 60);
        $data = getEmailRequestData();
        $settings = getSettings($db, ['contact_recipient', 'email_from_address']);
        $recipient = $settings['contact_recipient'] ?? $settings['email_from_address'] ?? '';
        if (!Security::validateEmail($recipient)) {
            sendError('Contact form is not configured.', 503);
        }
        // Accept an old client's matching address during migration, never a new destination.
        if (isset($data['to']) && (!is_string($data['to']) || strcasecmp($data['to'], $recipient) !== 0)) {
            sendError('The contact recipient is configured by the site administrator.', 400);
        }
        if (Captcha::isEnabled($db)
            && !(new Captcha($db, EDIT_ENCRYPTION_KEY))->verifyAndConsume($data['altcha'] ?? null)) {
            sendError('Verification failed or expired. Please verify again.', 403);
        }
        deliverEmail($db, $data, $recipient);
    }

    return false;
}

/** Validate bounded JSON before consuming a proof or contacting SMTP. */
function getEmailRequestData(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || strlen($raw) > 32768) {
        sendError('Message request is too large.', 413);
    }
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        sendError('Invalid JSON request.', 400);
    }
    if (!is_array($data) || array_is_list($data)) {
        sendError('Expected a JSON object.', 400);
    }
    foreach (['subject' => 200, 'message' => 20000] as $field => $limit) {
        if (!isset($data[$field]) || !is_string($data[$field])
            || trim($data[$field]) === '' || strlen($data[$field]) > $limit) {
            sendError("Invalid {$field}.", 400);
        }
    }
    foreach (['subject', 'from_name', 'reply_to'] as $field) {
        if (isset($data[$field]) && (!is_string($data[$field])
            || strlen($data[$field]) > 254 || preg_match('/[\r\n\x00]/', $data[$field]))) {
            sendError("Invalid {$field}.", 400);
        }
    }
    if (!empty($data['reply_to']) && !Security::validateEmail($data['reply_to'])) {
        sendError('Invalid reply_to address.', 400);
    }
    if (isset($data['is_html']) && !is_bool($data['is_html'])) {
        sendError('is_html must be a boolean.', 400);
    }
    return $data;
}

/** Shared transport for verified public messages and authenticated test messages. */
function deliverEmail(Database $db, array $data, string $recipient): void
{
    $settings = getSettings($db, [
        'email_from_address', 'email_from_name', 'smtp_host', 'smtp_port',
        'smtp_username', 'smtp_password', 'smtp_encryption',
    ]);
    $smtpConfig = [
        'host' => $settings['smtp_host'] ?? '',
        'port' => $settings['smtp_port'] ?? 587,
        'username' => $settings['smtp_username'] ?? '',
        'password' => empty($settings['smtp_password']) ? '' : (Security::decrypt($settings['smtp_password']) ?? ''),
        'encryption' => $settings['smtp_encryption'] ?? 'tls',
    ];
    $email = new Email($settings['email_from_address'] ?? '', $settings['email_from_name'] ?? '', $smtpConfig);
    $isHtml = $data['is_html'] ?? false;
    $message = $isHtml ? Security::sanitizeHTML($data['message']) : $data['message'];
    $success = $email->send($recipient, $data['subject'], $message, $isHtml, $data['reply_to'] ?? '');
    $db->table('email_logs')->insert([
        'to_address' => $recipient,
        'subject' => $data['subject'],
        'message' => $message,
        'from_name' => $data['from_name'] ?? null,
        'reply_to' => $data['reply_to'] ?? null,
        'is_html' => $isHtml ? 1 : 0,
        'success' => $success ? 1 : 0,
        // Do not persist SMTP diagnostics that could contain credentials or message content.
        'error_message' => $success ? null : 'Email delivery failed. Check the SMTP configuration.',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    if (!$success) {
        sendError('Email delivery failed. Please try again later.', 502);
    }
    sendJson(['success' => true, 'message' => 'Email sent successfully']);
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
    if ($path === '/email-test') {
        requireMethod($method, 'POST');
        checkRateLimit($db, 'email-test', 10, 60);
        $data = getEmailRequestData();
        if (!isset($data['to']) || !is_string($data['to']) || !Security::validateEmail($data['to'])) {
            sendError('Invalid test recipient.', 400);
        }
        deliverEmail($db, $data, $data['to']);
    }

    if ($path === '/email-settings' && $method === 'GET') {
        $settings = getSettings($db, [
            'email_from_address',
            'email_from_name',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'contact_recipient',
            'captcha_enabled'
        ]);

        $maskedPassword = !empty($settings['smtp_password']) ? '********' : '';

        sendJson([
            'from_email' => $settings['email_from_address'] ?? '',
            'from_name' => $settings['email_from_name'] ?? '',
            'smtp_host' => $settings['smtp_host'] ?? '',
            'smtp_port' => $settings['smtp_port'] ?? '587',
            'smtp_username' => $settings['smtp_username'] ?? '',
            'smtp_password' => $maskedPassword,
            'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls',
            'contact_recipient' => $settings['contact_recipient'] ?? $settings['email_from_address'] ?? '',
            'captcha_enabled' => Captcha::isEnabled($db)
        ]);
        return true;
    }

    if ($path === '/email-settings' && $method === 'PUT') {
        $data = getJsonBody();

        requireFields($data, ['from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password']);

        if (!Security::validateEmail($data['from_email'])) {
            sendError('Invalid from_email address', 400);
        }

        $recipient = $data['contact_recipient'] ?? $data['from_email'];
        if (!is_string($recipient) || !Security::validateEmail($recipient)) {
            sendError('Invalid contact recipient.', 400);
        }
        if (!is_string($data['from_name']) || preg_match('/[\r\n\x00]/', $data['from_name'])) {
            sendError('Invalid from_name.', 400);
        }

        if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
            sendError('Invalid smtp_port', 400);
        }

        saveSetting($db, 'email_from_address', $data['from_email']);
        saveSetting($db, 'email_from_name', $data['from_name']);
        saveSetting($db, 'contact_recipient', $recipient);

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

        // Save captcha setting
        saveSetting($db, 'captcha_enabled', ($data['captcha_enabled'] ?? Captcha::isEnabled($db)) ? '1' : '0');

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

    if (preg_match('#^/email-logs/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $logId = (int)$matches[1];
        $db->table('email_logs')->where('id', $logId)->delete();
        sendJson(['success' => true, 'message' => 'Email log deleted successfully']);
        return true;
    }

    if ($path === '/email-logs' && $method === 'DELETE') {
        $count = $db->table('email_logs')->count();
        $db->execute("DELETE FROM email_logs");
        sendJson(['success' => true, 'message' => "Deleted {$count} email log(s)"]);
        return true;
    }

    return false;
}
