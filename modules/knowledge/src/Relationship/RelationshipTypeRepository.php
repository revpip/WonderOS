<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Relationship;

interface RelationshipTypeRepository
{
    public function get(string $type): RelationshipType;

    /** @return list<RelationshipType> */
    public function active(): array;
}
