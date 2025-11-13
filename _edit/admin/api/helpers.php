<?php

declare(strict_types=1);

use Edit\Core\Database\Database;

/**
 * Send JSON response
 */
function sendJson(mixed $data, int $statusCode = 200): void
{
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function sendError(string $message, int $statusCode = 400): void
{
    sendJson(['error' => $message], $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonBody(): ?array
{
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return null;
    }
    return json_decode($body, true);
}

/**
 * Rate limiting with exponential backoff
 *
 * @param Database $db Database instance
 * @param string $endpoint Endpoint identifier (e.g., 'login', 'send-email-token')
 * @param int $maxAttempts Maximum attempts allowed in the window
 * @param int $windowMinutes Time window in minutes (default: 15)
 * @return void Sends error response and exits if rate limited
 */
function checkRateLimit(Database $db, string $endpoint, int $maxAttempts = 10, int $windowMinutes = 15): void
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = date('Y-m-d H:i:s');
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

    // Clean up old rate limit records (older than 1 hour)
    // Using raw SQL for datetime comparison
    $db->execute(
        "DELETE FROM rate_limits WHERE window_start < ? AND (locked_until IS NULL OR locked_until < ?)",
        [$oneHourAgo, $now]
    );

    // Check if currently locked out
    $lockCheck = $db->table('rate_limits')
        ->select(['locked_until'])
        ->where('ip_address', $ipAddress)
        ->where('endpoint', $endpoint)
        ->where('locked_until', '>', $now)
        ->first();

    if ($lockCheck) {
        $lockedUntil = new DateTime($lockCheck['locked_until']);
        $nowDt = new DateTime();
        $secondsRemaining = $lockedUntil->getTimestamp() - $nowDt->getTimestamp();

        sendError("Rate limit exceeded. Try again in " . ceil($secondsRemaining / 60) . " minutes.", 429);
    }

    // Get current attempts within window
    $record = $db->table('rate_limits')
        ->select(['id', 'attempts', 'window_start'])
        ->where('ip_address', $ipAddress)
        ->where('endpoint', $endpoint)
        ->where('window_start', '>', $windowStart)
        ->first();

    if (!$record) {
        // First attempt in this window - create new record
        // Note: ON CONFLICT requires raw SQL for SQLite
        $db->execute(
            "INSERT INTO rate_limits (ip_address, endpoint, attempts, window_start) VALUES (?, ?, 1, ?)
             ON CONFLICT(ip_address, endpoint) DO UPDATE SET attempts = 1, window_start = ?, locked_until = NULL",
            [$ipAddress, $endpoint, $now, $now]
        );
    } else {
        // Increment attempts
        $currentAttempts = $record['attempts'];

        if ($currentAttempts >= $maxAttempts) {
            // Calculate lockout duration with exponential backoff
            // 1st violation: 1 minute, 2nd: 5 minutes, 3rd+: 15 minutes
            $violations = floor($currentAttempts / $maxAttempts);
            $lockoutMinutes = min(15, pow(5, min($violations, 2)));
            $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));

            $db->table('rate_limits')
                ->where('id', $record['id'])
                ->update(['locked_until' => $lockedUntil]);

            sendError("Rate limit exceeded. Locked out for {$lockoutMinutes} minutes.", 429);
        } else {
            // Increment counter
            $db->table('rate_limits')
                ->where('id', $record['id'])
                ->update(['attempts' => $currentAttempts + 1]);
        }
    }
}
