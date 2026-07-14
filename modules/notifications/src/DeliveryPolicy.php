<?php

declare(strict_types=1);

namespace WonderOS\Notifications;

use PDO;

final readonly class DeliveryPolicy
{
    public function __construct(private PDO $connection) {}

    /** @return array{allowed:bool,reason:?string} */
    public function permits(string $email): array
    {
        $settings = $this->connection->query("SELECT delivery_status,pause_reason FROM wonder_email_reputation_settings WHERE singleton=TRUE")->fetch(PDO::FETCH_ASSOC);
        if ($settings !== false && $settings['delivery_status'] === 'paused') {
            return ['allowed' => false, 'reason' => (string) ($settings['pause_reason'] ?: 'Email delivery is paused.')];
        }

        $statement = $this->connection->prepare('SELECT reason FROM wonder_email_suppressions WHERE lower(email)=lower(:email) AND released_at IS NULL AND (release_at IS NULL OR release_at>NOW()) ORDER BY created_at DESC LIMIT 1');
        $statement->execute(['email' => $email]);
        $reason = $statement->fetchColumn();

        return $reason === false
            ? ['allowed' => true, 'reason' => null]
            : ['allowed' => false, 'reason' => 'Recipient suppressed: '.$reason];
    }
}
