<?php

declare(strict_types=1);
namespace WonderOS\Core\Notification;

interface NotificationRepository
{
    /** @param array<string,mixed> $notification */
    public function create(array $notification): array;
    /** @return list<array<string,mixed>> */
    public function forUser(string $userUuid, bool $unreadOnly = false, int $limit = 100): array;
    public function markRead(string $notificationUuid, string $userUuid): ?array;
    public function markAllRead(string $userUuid): int;
    public function generateDueNotifications(): int;
}
