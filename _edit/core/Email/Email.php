<?php

namespace Edit\Core\Email;

class Email
{
    private string $fromEmail;
    private string $fromName;
    private ?array $smtpConfig;
    private string $lastError = '';

    public function __construct(string $fromEmail = '', string $fromName = '', ?array $smtpConfig = null)
    {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->smtpConfig = $smtpConfig;
    }

    /**
     * Send an email using SMTP
     *
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $message Email body (can be HTML)
     * @param bool $isHtml Whether the message is HTML (default: true)
     * @return bool Success status
     */
    public function send($to, string $subject, string $message, bool $isHtml = true, string $replyTo = ''): bool
    {
        // Check if SMTP is configured
        if (!$this->isSmtpConfigured()) {
            $this->lastError = 'SMTP is not configured. Please configure SMTP settings in the Email page.';
            return false;
        }

        try {
            $smtp = new SMTP(
                $this->smtpConfig['host'],
                (int)$this->smtpConfig['port'],
                $this->smtpConfig['username'],
                $this->smtpConfig['password'],
                $this->smtpConfig['encryption'] ?? 'tls'
            );

            $toAddress = is_array($to) ? $to[0] : $to; // SMTP class handles single recipient
            $success = $smtp->send($this->fromEmail, $this->fromName, $toAddress, $subject, $message, $isHtml, $replyTo);

            if (!$success) {
                $this->lastError = $smtp->getLastError();
            }

            return $success;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
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
     * Check if SMTP is properly configured
     */
    private function isSmtpConfigured(): bool
    {
        return !empty($this->smtpConfig['host']) &&
               !empty($this->smtpConfig['port']) &&
               !empty($this->smtpConfig['username']) &&
               !empty($this->smtpConfig['password']);
    }

    /**
     * Set the from email address
     */
    public function setFrom(string $email, string $name = ''): void
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
    }
}
