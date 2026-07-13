<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\GraphFilter;

final class EditorialLensTest extends TestCase
{
    public function test_it_wraps_a_reusable_graph_filter(): void
    {
        $lens = new EditorialLens('symbolism','Symbolism','Symbolic connections.',new GraphFilter(['symbolises'],['approved'],0.8));
        self::assertSame('symbolism',$lens->slug);
        self::assertSame(['symbolises'],$lens->filter->types);
        self::assertSame(0.8,$lens->filter->minimumConfidence);
    }

    public function test_slug_must_use_kebab_case(): void
    {
        $this->expectException(DomainException::class);
        new EditorialLens('Symbolic Lens','Symbolism','Invalid slug.',new GraphFilter());
    }

    public function test_status_is_governed(): void
    {
        $this->expectException(DomainException::class);
        new EditorialLens('symbolism','Symbolism','Symbolic connections.',new GraphFilter(),'hidden');
    }
}
