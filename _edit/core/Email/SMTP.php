<?php

namespace Edit\Core\Email;

class SMTP
{
    private $socket;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption; // '', 'tls', or 'ssl'
    private int $timeout = 30;
    private string $lastError = '';

    public function __construct(string $host, int $port, string $username, string $password, string $encryption = 'tls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = strtolower($encryption);
    }

    /**
     * Send an email via SMTP
     */
    public function send(string $from, string $fromName, string $to, string $subject, string $message, bool $isHtml = true, string $replyTo = ''): bool
    {
        try {
            foreach ([$from, $fromName, $to, $subject, $replyTo] as $headerValue) {
                if (preg_match('/[\r\n\x00]/', $headerValue)) {
                    throw new \InvalidArgumentException('Invalid email header.');
                }
            }
            $this->connect();
            $this->authenticate();
            $this->sendMail($from, $fromName, $to, $subject, $message, $isHtml, $replyTo);
            $this->disconnect();
            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            if ($this->socket) {
                @fclose($this->socket);
            }
            return false;
        }
    }

    /**
     * Get the last error message
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Connect to SMTP server
     */
    private function connect(): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);

        // Use SSL wrapper if encryption is SSL
        $host = $this->encryption === 'ssl' ? "ssl://{$this->host}" : $this->host;

        $this->socket = @stream_socket_client(
            "{$host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \Exception("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }

        // Set timeout for read/write operations
        stream_set_timeout($this->socket, $this->timeout);

        // Read server greeting
        $response = $this->getResponse();
        if (!$this->isSuccessResponse($response)) {
            throw new \Exception("SMTP connection failed: {$response}");
        }

        // Send EHLO
        $this->sendCommand("EHLO {$this->host}");

        // Enable TLS if requested (STARTTLS)
        if ($this->encryption === 'tls') {
            $this->sendCommand("STARTTLS");

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \Exception("Failed to enable TLS encryption");
            }

            // Send EHLO again after TLS
            $this->sendCommand("EHLO {$this->host}");
        }
    }

    /**
     * Authenticate with SMTP server
     */
    private function authenticate(): void
    {
        // Use AUTH LOGIN method
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    /**
     * Send the actual email
     */
    private function sendMail(string $from, string $fromName, string $to, string $subject, string $message, bool $isHtml, string $replyTo): void
    {
        // MAIL FROM
        $this->sendCommand("MAIL FROM:<{$from}>");

        // RCPT TO (supports multiple recipients)
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            $this->sendCommand("RCPT TO:<{$recipient}>");
        }

        // DATA
        $this->sendCommand("DATA");

        // Build email headers and body
        $headers = $this->buildHeaders($from, $fromName, $to, $subject, $isHtml, $replyTo);
        // Normalize lines and dot-stuff DATA, including a visitor-supplied line
        // containing only '.', so message text cannot become SMTP commands.
        $message = preg_replace('/\r\n|\r|\n/', "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);
        $email = $headers . "\r\n\r\n" . $message . "\r\n.";

        // Send email content
        $this->sendData($email);
    }

    /**
     * Build email headers
     */
    private function buildHeaders(string $from, string $fromName, string $to, string $subject, bool $isHtml, string $replyTo): string
    {
        $headers = [];

        // From header
        if ($fromName) {
            $headers[] = "From: {$fromName} <{$from}>";
        } else {
            $headers[] = "From: {$from}";
        }

        // To header
        $headers[] = "To: {$to}";
        if ($replyTo !== '') {
            $headers[] = "Reply-To: {$replyTo}";
        }

        // Subject
        $headers[] = "Subject: {$subject}";

        // Date
        $headers[] = "Date: " . date('r');

        // MIME version
        $headers[] = "MIME-Version: 1.0";

        // Content type
        if ($isHtml) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        // Additional headers
        $headers[] = "X-Mailer: Edit CMS SMTP";

        return implode("\r\n", $headers);
    }

    /**
     * Disconnect from SMTP server
     */
    private function disconnect(): void
    {
        $this->sendCommand("QUIT");
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send a command to SMTP server and get response
     */
    private function sendCommand(string $command): string
    {
        fwrite($this->socket, $command . "\r\n");
        $response = $this->getResponse();

        if (!$this->isSuccessResponse($response)) {
            throw new \Exception('SMTP command rejected.');
        }

        return $response;
    }

    /**
     * Send data (email content)
     */
    private function sendData(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
        $response = $this->getResponse();

        if (!$this->isSuccessResponse($response)) {
            throw new \Exception("SMTP data transmission failed: {$response}");
        }
    }

    /**
     * Read response from SMTP server
     */
    private function getResponse(): string
    {
        $response = '';

        while ($line = fgets($this->socket, 515)) {
            $response .= $line;

            // Multi-line responses have a dash after the code (e.g., "250-OK")
            // Single-line or final line has a space (e.g., "250 OK")
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Check if SMTP response indicates success
     */
    private function isSuccessResponse(string $response): bool
    {
        $code = (int) substr($response, 0, 3);

        // Success codes: 2xx and 3xx
        return $code >= 200 && $code < 400;
    }
}
