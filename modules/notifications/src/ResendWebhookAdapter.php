<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use DateTimeImmutable;
use InvalidArgumentException;

/** Normalises Resend webhook payloads into WonderOS email-health events. */
final class ResendWebhookAdapter implements ProviderWebhookAdapter
{
    public function normalise(array $payload): array
    {
        $type = trim((string) ($payload['type'] ?? ''));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $mapped = match ($type) {
            'email.delivered' => 'delivered',
            'email.delivery_delayed' => 'deferred',
            'email.bounced' => 'bounced',
            'email.failed' => 'blocked',
            'email.complained' => 'complained',
            'email.opened' => 'opened',
            'email.clicked' => 'clicked',
            default => throw new InvalidArgumentException('Unsupported Resend event type: ' . $type),
        };

        $recipient = $data['to'][0] ?? $data['to'] ?? $data['email'] ?? null;
        if (is_array($recipient)) $recipient = $recipient[0] ?? null;
        $eventId = trim((string) ($payload['id'] ?? $payload['event_id'] ?? ''));
        $messageId = trim((string) ($data['email_id'] ?? $data['id'] ?? ''));
        $occurred = trim((string) ($payload['created_at'] ?? $data['created_at'] ?? ''));
        if ($eventId === '' || !is_string($recipient) || trim($recipient) === '' || $occurred === '') {
            throw new InvalidArgumentException('Resend webhook lacks event ID, recipient or timestamp.');
        }

        $classification = null;
        if ($mapped === 'bounced') {
            $classification = strtolower((string) ($data['bounce']['type'] ?? $data['bounce_type'] ?? 'hard'));
            $classification = in_array($classification, ['soft', 'hard'], true) ? $classification : 'hard';
        }

        return [
            'event_id' => $eventId,
            'event_type' => $mapped,
            'message_id' => $messageId !== '' ? $messageId : null,
            'recipient' => strtolower(trim($recipient)),
            'occurred_at' => (new DateTimeImmutable($occurred))->format(DATE_ATOM),
            'bounce_classification' => $classification,
            'payload' => $payload,
        ];
    }
}
