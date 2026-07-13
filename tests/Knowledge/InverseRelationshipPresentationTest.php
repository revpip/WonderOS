<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Relationship\Relationship;
use WonderOS\Knowledge\Relationship\RelationshipType;

final class InverseRelationshipPresentationTest extends TestCase
{
    public function test_it_presents_the_governed_inverse_from_the_target_perspective(): void
    {
        $barnOwl = WonderId::entity(1);
        $churchTowers = WonderId::entity(2);
        $relationship = Relationship::create($barnOwl, $churchTowers, 'roosts_in');
        $type = new RelationshipType(
            'roosts_in',
            'hosts_roost_of',
            'roosts in',
            'hosts roost of',
            'Connects an animal to a structure or habitat used for roosting.',
        );

        self::assertTrue($relationship->isOutgoingFrom($barnOwl));
        self::assertSame('WND-ENT-000002', (string) $relationship->relatedEntityIdFor($barnOwl));
        self::assertSame('roosts_in', $type->presentationType(true));
        self::assertSame('roosts in', $type->presentationLabel(true));

        self::assertTrue($relationship->isIncomingTo($churchTowers));
        self::assertSame('WND-ENT-000001', (string) $relationship->relatedEntityIdFor($churchTowers));
        self::assertSame('hosts_roost_of', $type->presentationType(false));
        self::assertSame('hosts roost of', $type->presentationLabel(false));
    }

    public function test_symmetric_relationships_keep_the_same_presentation_from_both_ends(): void
    {
        $type = new RelationshipType(
            'associated_with',
            'associated_with',
            'associated with',
            'associated with',
            'Connects subjects with a meaningful non-directional association.',
            true,
        );

        self::assertSame('associated_with', $type->presentationType(true));
        self::assertSame('associated_with', $type->presentationType(false));
        self::assertSame('associated with', $type->presentationLabel(false));
    }

    public function test_it_rejects_a_perspective_entity_outside_the_edge(): void
    {
        $relationship = Relationship::create(WonderId::entity(1), WonderId::entity(2), 'roosts_in');

        $this->expectException(DomainException::class);
        $relationship->relatedEntityIdFor(WonderId::entity(3));
    }
}
