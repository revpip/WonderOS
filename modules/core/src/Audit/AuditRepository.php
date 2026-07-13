<?php

declare(strict_types=1);

namespace WonderOS\Core\Audit;

use WonderOS\Core\Auth\User;

interface AuditRepository
{
    /** @param array<string,mixed> $metadata */
    public function record(User $actor, string $action, string $subjectType, string $subjectId, string $summary, array $metadata = []): void;

    /** @param array<string,string|int|null> $filters @return list<array<string,mixed>> */
    public function search(array $filters = []): array;
}