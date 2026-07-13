<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Knowledge\Relationship\RelationshipType;

final class RelationshipTypeTest extends TestCase
{
    public function test_it_models_inverse_and_symmetric_relationships(): void
    {
        $directional = new RelationshipType('roosts_in','hosts_roost_of','roosts in','hosts roost of','Shelter relationship.');
        self::assertSame('hosts_roost_of', $directional->inverseType);
        self::assertFalse($directional->symmetric);

        $symmetric = new RelationshipType('associated_with','associated_with','associated with','associated with','Broad association.', true);
        self::assertTrue($symmetric->symmetric);
    }

    public function test_symmetric_relationship_must_be_its_own_inverse(): void
    {
        $this->expectException(DomainException::class);
        new RelationshipType('near','far_from','near','far from','Invalid symmetric pair.', true);
    }
}
