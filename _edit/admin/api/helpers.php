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

    // Clean up old rate limit records (older than 1 hour)
    $db->execute(
        "DELETE FROM rate_limits WHERE window_start < datetime('now', '-1 hour') AND (locked_until IS NULL OR locked_until < datetime('now'))"
    );

    // Check if currently locked out
    $lockCheck = $db->query(
        "SELECT locked_until FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND locked_until > datetime('now')",
        [$ipAddress, $endpoint]
    );

    if (!empty($lockCheck)) {
        $lockedUntil = new DateTime($lockCheck[0]['locked_until']);
        $now = new DateTime();
        $secondsRemaining = $lockedUntil->getTimestamp() - $now->getTimestamp();

        sendError("Rate limit exceeded. Try again in " . ceil($secondsRemaining / 60) . " minutes.", 429);
    }

    // Get current attempts within window
    $record = $db->query(
        "SELECT id, attempts, window_start FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND window_start > datetime('now', '-{$windowMinutes} minutes')",
        [$ipAddress, $endpoint]
    );

    if (empty($record)) {
        // First attempt in this window - create new record
        $db->execute(
            "INSERT INTO rate_limits (ip_address, endpoint, attempts, window_start) VALUES (?, ?, 1, datetime('now'))
             ON CONFLICT(ip_address, endpoint) DO UPDATE SET attempts = 1, window_start = datetime('now'), locked_until = NULL",
            [$ipAddress, $endpoint]
        );
    } else {
        // Increment attempts
        $currentAttempts = $record[0]['attempts'];

        if ($currentAttempts >= $maxAttempts) {
            // Calculate lockout duration with exponential backoff
            // 1st violation: 1 minute, 2nd: 5 minutes, 3rd+: 15 minutes
            $violations = floor($currentAttempts / $maxAttempts);
            $lockoutMinutes = min(15, pow(5, min($violations, 2)));

            $db->execute(
                "UPDATE rate_limits SET locked_until = datetime('now', '+{$lockoutMinutes} minutes') WHERE id = ?",
                [$record[0]['id']]
            );

            sendError("Rate limit exceeded. Locked out for {$lockoutMinutes} minutes.", 429);
        } else {
            // Increment counter
            $db->execute(
                "UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?",
                [$record[0]['id']]
            );
        }
    }
}
