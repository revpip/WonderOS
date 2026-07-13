<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Relationship\Relationship;

final class RelationshipTest extends TestCase
{
    public function test_it_creates_a_typed_directional_relationship(): void
    {
        $relationship = Relationship::create(
            WonderId::entity(1),
            WonderId::entity(2),
            'roosts in',
            'Historic church towers provide quiet elevated shelter.',
            0.85,
        );

        self::assertSame('WND-ENT-000001', (string) $relationship->sourceId());
        self::assertSame('WND-ENT-000002', (string) $relationship->targetId());
        self::assertSame('roosts_in', $relationship->type());
        self::assertSame(0.85, $relationship->confidence());
        self::assertSame('draft', $relationship->status());
    }

    public function test_it_rejects_self_relationships(): void
    {
        $this->expectException(DomainException::class);
        Relationship::create(WonderId::entity(1), WonderId::entity(1), 'related_to');
    }
}
