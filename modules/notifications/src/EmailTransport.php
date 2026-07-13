<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

interface EmailTransport
{
    /** @return array{message_id:?string,response_code:?int} */
    public function send(string $to, string $subject, string $html, string $text): array;
}
