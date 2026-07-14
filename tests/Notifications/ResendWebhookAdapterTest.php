<?php

declare(strict_types=1);

namespace WonderOS\Tests\Notifications;

use PHPUnit\Framework\TestCase;
use WonderOS\Notifications\ResendWebhookAdapter;
use WonderOS\Notifications\ResendWebhookVerifier;

final class ResendWebhookAdapterTest extends TestCase
{
    public function testDeliveredEventIsNormalised(): void
    {
        $event=(new ResendWebhookAdapter())->normalise([
            'id'=>'evt_123','type'=>'email.delivered','created_at'=>'2026-07-14T12:00:00Z',
            'data'=>['email_id'=>'email_456','to'=>['editor@example.com']],
        ]);
        self::assertSame('delivered',$event['event_type']);
        self::assertSame('email_456',$event['message_id']);
        self::assertSame('editor@example.com',$event['recipient']);
    }

    public function testSoftBounceIsRetained(): void
    {
        $event=(new ResendWebhookAdapter())->normalise([
            'id'=>'evt_124','type'=>'email.bounced','created_at'=>'2026-07-14T12:00:00Z',
            'data'=>['email_id'=>'email_457','to'=>['editor@example.com'],'bounce'=>['type'=>'soft']],
        ]);
        self::assertSame('bounced',$event['event_type']);
        self::assertSame('soft',$event['bounce_classification']);
    }

    public function testSvixSignatureIsVerifiedAgainstRawBody(): void
    {
        $body='{"id":"evt_123"}';
        $id='msg_123';$timestamp=(string)time();$key=random_bytes(32);
        $secret='whsec_'.base64_encode($key);
        $signature='v1,'.base64_encode(hash_hmac('sha256',$id.'.'.$timestamp.'.'.$body,$key,true));
        (new ResendWebhookVerifier($secret))->verify($body,['svix-id'=>$id,'svix-timestamp'=>$timestamp,'svix-signature'=>$signature]);
        self::assertTrue(true);
    }
}
