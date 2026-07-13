<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Graph\GraphFilter;
use WonderOS\Knowledge\Relationship\Relationship;

final class GraphFilterTest extends TestCase
{
    public function test_it_filters_by_type_status_and_confidence(): void
    {
        $relationship = Relationship::create(
            WonderId::entity(1),
            WonderId::entity(2),
            'symbolises',
            'Athena and later Western traditions.',
            0.86,
        );

        self::assertTrue((new GraphFilter(['symbolises'], ['draft'], 0.8))->accepts($relationship));
        self::assertFalse((new GraphFilter(['located_in'], ['draft'], 0.8))->accepts($relationship));
        self::assertFalse((new GraphFilter(['symbolises'], ['approved'], 0.8))->accepts($relationship));
        self::assertFalse((new GraphFilter(['symbolises'], ['draft'], 0.9))->accepts($relationship));
    }

    public function test_empty_filters_accept_every_relationship(): void
    {
        $relationship = Relationship::create(
            WonderId::entity(1),
            WonderId::entity(2),
            'associated_with',
        );

        self::assertTrue((new GraphFilter())->accepts($relationship));
    }

    public function test_it_rejects_invalid_filter_values(): void
    {
        $this->expectException(DomainException::class);
        new GraphFilter(['not a valid type'], [], 0.5);
    }

    public function test_it_rejects_confidence_outside_the_unit_interval(): void
    {
        $this->expectException(DomainException::class);
        new GraphFilter([], [], 1.01);
    }
}
