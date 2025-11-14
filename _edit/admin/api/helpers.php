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
 * Get Authorization header from request
 * Checks both HTTP_AUTHORIZATION and apache_request_headers() fallback
 */
function getAuthHeader(): string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
    }

    return $authHeader;
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

    // ATOMIC OPERATION: Insert or reset window if expired
    $db->execute(
        "INSERT INTO rate_limits (ip_address, endpoint, attempts, window_start) VALUES (?, ?, 0, ?)
         ON CONFLICT(ip_address, endpoint) DO UPDATE SET
            attempts = CASE WHEN window_start <= ? THEN 0 ELSE attempts END,
            window_start = CASE WHEN window_start <= ? THEN ? ELSE window_start END,
            locked_until = NULL",
        [$ipAddress, $endpoint, $now, $windowStart, $windowStart, $now]
    );

    // ATOMIC OPERATION: Increment if under limit, using WHERE clause
    $result = $db->execute(
        "UPDATE rate_limits
         SET attempts = attempts + 1
         WHERE ip_address = ? AND endpoint = ? AND attempts < ?",
        [$ipAddress, $endpoint, $maxAttempts]
    );

    // If no rows updated, rate limit exceeded
    if ($result === 0) {
        // Get current attempts to calculate lockout
        $record = $db->table('rate_limits')
            ->select(['attempts'])
            ->where('ip_address', $ipAddress)
            ->where('endpoint', $endpoint)
            ->first();

        $currentAttempts = $record['attempts'] ?? $maxAttempts;

        // Calculate lockout duration with exponential backoff
        // 1st violation: 1 minute, 2nd: 5 minutes, 3rd+: 15 minutes
        $violations = floor($currentAttempts / $maxAttempts);
        $lockoutMinutes = min(15, pow(5, min($violations, 2)));
        $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));

        // Set lockout
        $db->table('rate_limits')
            ->where('ip_address', $ipAddress)
            ->where('endpoint', $endpoint)
            ->update(['locked_until' => $lockedUntil]);

        sendError("Rate limit exceeded. Locked out for {$lockoutMinutes} minutes.", 429);
    }
}

/**
 * Require HTTP method to match one of the allowed methods
 *
 * @param string $current Current HTTP method
 * @param string|array $allowed Allowed method(s) - string or array of strings
 * @return void Sends error and exits if method not allowed
 */
function requireMethod(string $current, string|array $allowed): void
{
    $allowed = is_array($allowed) ? $allowed : [$allowed];
    if (!in_array($current, $allowed)) {
        sendError('Method not allowed', 405);
    }
}

/**
 * Get multiple settings in a single query
 *
 * @param Database $db Database instance
 * @param array $keys Setting keys to fetch
 * @return array Associative array of key => value
 */
function getSettings(Database $db, array $keys): array
{
    $placeholders = str_repeat('?,', count($keys) - 1) . '?';
    $results = $db->query(
        "SELECT key, value FROM settings WHERE key IN ($placeholders)",
        $keys
    );

    $settings = [];
    foreach ($results as $row) {
        $settings[$row['key']] = $row['value'];
    }

    return $settings;
}

/**
 * Validate required fields exist in data
 *
 * @param array $data Data to validate
 * @param array $required Required field names
 * @param int $statusCode HTTP status code for error (default: 400)
 * @return void Sends error and exits if validation fails
 */
function requireFields(array $data, array $required, int $statusCode = 400): void
{
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        $fields = implode(', ', $missing);
        sendError(ucfirst($fields) . ' required', $statusCode);
    }
}

/**
 * Save or update a setting (upsert)
 *
 * @param Database $db Database instance
 * @param string $key Setting key
 * @param string $value Setting value
 * @return void
 */
function saveSetting(Database $db, string $key, string $value): void
{
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
}

/**
 * Add URL to media item(s)
 *
 * @param array|null $media Single media item or array of items
 * @return array|null Media with URL added
 */
function addMediaUrl(array|null $media): array|null
{
    if (!$media) {
        return null;
    }

    // Check if it's a single item (has 'path' key) or array of items
    if (isset($media['path'])) {
        // Single item
        $media['url'] = '/_edit/uploads/' . $media['path'];
        return $media;
    } else {
        // Array of items
        foreach ($media as &$item) {
            if (isset($item['path'])) {
                $item['url'] = '/_edit/uploads/' . $item['path'];
            }
        }
        return $media;
    }
}

/**
 * Check if media is in use across all content
 * Handles both direct media fields and nested media in repeater/JSON fields
 *
 * @param Database $db Database instance
 * @param int $mediaId Media ID to check
 * @return bool True if media is in use
 */
function checkMediaUsage(Database $db, int $mediaId): bool
{
    // Use SQL to check for media usage - much faster than loading all records into PHP
    // Check for:
    // 1. Direct match: meta_value = 'mediaId'
    // 2. JSON occurrence: meta_value contains the media ID in various JSON formats
    //    - As number: "123" or ":123," or ":123}"
    //    - As string: "\"123\"" (quoted string in JSON)

    $count = $db->query(
        "SELECT COUNT(*) as count FROM content_meta
         WHERE meta_value = ?
            OR meta_value LIKE ?
            OR meta_value LIKE ?
            OR meta_value LIKE ?",
        [
            (string)$mediaId,              // Direct match: "123"
            '%":' . $mediaId . ',%',       // JSON number: ":123,"
            '%":' . $mediaId . '}%',       // JSON number: ":123}"
            '%"' . $mediaId . '"%'         // JSON string: "123" or \"123\"
        ]
    );

    return ($count[0]['count'] ?? 0) > 0;
}
