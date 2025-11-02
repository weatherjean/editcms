<?php

namespace Edit\Core\Email;

class Email {
    private string $fromEmail;
    private string $fromName;

    public function __construct(string $fromEmail = '', string $fromName = '') {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * Send an email using PHP's built-in mail() function
     *
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $message Email body (can be HTML)
     * @param bool $isHtml Whether the message is HTML (default: true)
     * @return bool Success status
     */
    public function send($to, string $subject, string $message, bool $isHtml = true): bool {
        // Handle array of recipients
        $toAddress = is_array($to) ? implode(', ', $to) : $to;

        // Build headers
        $headers = $this->buildHeaders($isHtml);

        // Send email
        return mail($toAddress, $subject, $message, $headers);
    }

    /**
     * Build email headers
     */
    private function buildHeaders(bool $isHtml): string {
        $headers = [];

        // From header
        if ($this->fromEmail) {
            if ($this->fromName) {
                $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
            } else {
                $headers[] = "From: {$this->fromEmail}";
            }
        }

        // Content type
        if ($isHtml) {
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        // Additional headers for better deliverability
        $headers[] = "X-Mailer: PHP/" . phpversion();

        return implode("\r\n", $headers);
    }

    /**
     * Set the from email address
     */
    public function setFrom(string $email, string $name = ''): void {
        $this->fromEmail = $email;
        $this->fromName = $name;
    }
}
