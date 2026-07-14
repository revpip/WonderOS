<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use RuntimeException;

/** Production transport for Resend's email API. */
final readonly class ResendEmailTransport implements EmailTransport
{
    public function __construct(
        private string $apiKey,
        private string $fromEmail,
        private string $fromName = 'WonderOS',
        private string $endpoint = 'https://api.resend.com/emails',
    ) {}

    public function send(string $to, string $subject, string $html, string $text): array
    {
        if ($this->apiKey === '' || $this->fromEmail === '') {
            throw new RuntimeException('RESEND_API_KEY and EMAIL_FROM_ADDRESS are required.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for Resend delivery.');
        }

        $payload = json_encode([
            'from' => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $handle = curl_init($this->endpoint);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Idempotency-Key: ' . hash('sha256', $to . "\0" . $subject . "\0" . $text),
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new RuntimeException($error !== '' ? $error : sprintf('Resend returned HTTP %d: %s', $code, substr((string) $body, 0, 500)));
        }

        $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        return ['message_id' => (string) ($decoded['id'] ?? '') ?: null, 'response_code' => $code];
    }
}
