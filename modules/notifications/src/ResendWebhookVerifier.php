<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use InvalidArgumentException;

/** Verifies Resend's Svix-signed webhook requests using the untouched body. */
final readonly class ResendWebhookVerifier
{
    public function __construct(private string $secret, private int $toleranceSeconds = 300) {}

    /** @param array<string,string> $headers */
    public function verify(string $rawBody, array $headers): void
    {
        $id = trim($headers['svix-id'] ?? '');
        $timestamp = trim($headers['svix-timestamp'] ?? '');
        $signatureHeader = trim($headers['svix-signature'] ?? '');
        if ($this->secret === '' || $id === '' || !ctype_digit($timestamp) || $signatureHeader === '') {
            throw new InvalidArgumentException('Missing Resend webhook verification values.');
        }
        if (abs(time() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidArgumentException('Resend webhook timestamp is outside the accepted window.');
        }

        $encodedKey = str_starts_with($this->secret, 'whsec_') ? substr($this->secret, 6) : $this->secret;
        $key = base64_decode($encodedKey, true);
        if ($key === false) {
            throw new InvalidArgumentException('RESEND_WEBHOOK_SECRET is not a valid signing secret.');
        }
        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $rawBody, $key, true));
        foreach (preg_split('/\s+/', $signatureHeader) ?: [] as $candidate) {
            [$version, $value] = array_pad(explode(',', $candidate, 2), 2, '');
            if ($version === 'v1' && $value !== '' && hash_equals($expected, $value)) {
                return;
            }
        }
        throw new InvalidArgumentException('Resend webhook signature verification failed.');
    }
}
