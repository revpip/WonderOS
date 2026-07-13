<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use RuntimeException;

/** Sends transactional email through a configurable JSON HTTP endpoint. */
final readonly class HttpEmailTransport implements EmailTransport
{
    public function __construct(
        private string $endpoint,
        private string $apiKey,
        private string $fromEmail,
        private string $fromName = 'WonderOS',
    ) {}

    public function send(string $to, string $subject, string $html, string $text): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for email delivery.');
        }

        $payload = json_encode([
            'from' => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'to' => [['email' => $to]],
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
            throw new RuntimeException($error !== '' ? $error : sprintf('Email provider returned HTTP %d.', $code));
        }

        $decoded = json_decode((string) $body, true);
        return [
            'message_id' => is_array($decoded) ? (string) ($decoded['id'] ?? $decoded['message_id'] ?? '') ?: null : null,
            'response_code' => $code,
        ];
    }
}
