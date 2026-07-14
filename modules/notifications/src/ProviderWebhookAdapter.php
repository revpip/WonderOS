<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

interface ProviderWebhookAdapter
{
    public function supports(string $provider): bool;

    /** @param array<string,mixed> $payload
     *  @return array{event_id:string,event_type:string,message_id:?string,email:string,occurred_at:string,bounce_classification:?string,payload:array<string,mixed>}
     */
    public function normalise(array $payload): array;
}
