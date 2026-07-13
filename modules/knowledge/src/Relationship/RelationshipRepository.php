<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Relationship;

use WonderOS\Core\Identity\WonderId;

interface RelationshipRepository
{
    public function save(Relationship $relationship): void;

    /** @return list<Relationship> */
    public function forEntity(WonderId $entityId): array;
}
