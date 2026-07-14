<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use InvalidArgumentException;

final class GenericJsonWebhookAdapter implements ProviderWebhookAdapter
{
    public function supports(string $provider): bool { return $provider === 'generic-json'; }

    public function normalise(array $payload): array
    {
        foreach (['event_id','event_type','email','occurred_at'] as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
                throw new InvalidArgumentException($field.' is required.');
            }
        }
        $type = strtolower((string)$payload['event_type']);
        $allowed = ['delivered','deferred','bounced','blocked','complained','opened','clicked'];
        if (!in_array($type,$allowed,true)) throw new InvalidArgumentException('Unsupported provider event type.');
        $classification = $payload['bounce_classification'] ?? null;
        if ($classification !== null && !in_array($classification,['soft','hard'],true)) throw new InvalidArgumentException('Unsupported bounce classification.');
        return [
            'event_id'=>(string)$payload['event_id'],
            'event_type'=>$type,
            'message_id'=>isset($payload['message_id'])?(string)$payload['message_id']:null,
            'email'=>strtolower(trim((string)$payload['email'])),
            'occurred_at'=>(string)$payload['occurred_at'],
            'bounce_classification'=>$classification,
            'payload'=>$payload,
        ];
    }
}
