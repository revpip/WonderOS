<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use WonderOS\Core\Identity\WonderId;

final readonly class EntityCreated
{
    public function __construct(
        public WonderId $entityId,
        public string $canonicalName,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
