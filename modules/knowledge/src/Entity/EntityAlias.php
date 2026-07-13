<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use DomainException;
use WonderOS\Core\Identity\WonderId;

final readonly class EntityAlias
{
    public function __construct(
        public WonderId $entityId,
        public string $alias,
        public EntityAliasType $type,
        public ?string $language = null,
    ) {
        if (trim($alias) === '') {
            throw new DomainException('Alias cannot be empty.');
        }
    }

    public function normalised(): string
    {
        $value = strtolower(trim($this->alias));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
