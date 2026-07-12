<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use WonderOS\Core\Identity\WonderId;

/** Defines durable storage operations for canonical entities. */
interface EntityRepository
{
    public function nextIdentity(): WonderId;

    public function save(Entity $entity, ?int $expectedRevision = null): void;

    public function get(WonderId $id): Entity;

    public function find(WonderId $id): ?Entity;

    /** @return list<Entity> */
    public function search(string $query, int $limit = 10): array;

    public function findBySlug(string $slug): ?Entity;
}
