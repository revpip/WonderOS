<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge\Entity;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityCreated;
use WonderOS\Knowledge\Entity\EntityStatus;

final class EntityTest extends TestCase
{
    public function test_it_creates_a_canonical_entity_and_emits_an_event(): void
    {
        $entity = Entity::create(
            WonderId::entity(1),
            'Barn Owl',
            'Living Things',
            'Bird',
            0.9,
        );

        self::assertSame('WND-ENT-000001', (string) $entity->id());
        self::assertSame('Barn Owl', $entity->canonicalName());
        self::assertSame('barn-owl', $entity->slug());
        self::assertSame(EntityStatus::Draft, $entity->status());
        self::assertSame(1, $entity->revision());
        self::assertInstanceOf(EntityCreated::class, $entity->releaseEvents()[0]);
    }

    public function test_it_rejects_an_empty_name(): void
    {
        $this->expectException(DomainException::class);
        Entity::create(WonderId::entity(1), ' ', 'Living Things', 'Bird');
    }

    public function test_it_prevents_silent_revision_overwrite(): void
    {
        $entity = Entity::create(WonderId::entity(1), 'Barn Owl', 'Living Things', 'Bird');
        $entity->rename('Western Barn Owl', 1);

        self::assertSame(2, $entity->revision());
        self::assertSame('western-barn-owl', $entity->slug());

        $this->expectException(DomainException::class);
        $entity->rename('Old Name', 1);
    }
}
