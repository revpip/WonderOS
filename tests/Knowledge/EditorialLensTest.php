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
        self::assertSame('draft',$lens->status);
        self::assertSame(1,$lens->revision);
    }

    public function test_revision_returns_a_new_draft_and_increments_revision(): void
    {
        $lens = EditorialLens::create('symbolism','Symbolism','Symbolic connections.',new GraphFilter(['symbolises']), 'editor@example.test');
        $revised = $lens->revise('Symbolism & Meaning','A refined symbolic lens.',new GraphFilter(['symbolises','associated_with'],[],0.7),1,'reviewer@example.test');

        self::assertSame(2,$revised->revision);
        self::assertSame('draft',$revised->status);
        self::assertSame('reviewer@example.test',$revised->updatedBy);
        self::assertSame(['symbolises','associated_with'],$revised->filter->types);
    }

    public function test_activation_and_deprecation_are_revisioned(): void
    {
        $draft = EditorialLens::create('travel','Travel','Travel connections.',new GraphFilter(['located_in']), 'editor');
        $active = $draft->activate(1,'publisher');
        $deprecated = $active->deprecate(2,'publisher');

        self::assertSame('active',$active->status);
        self::assertSame(2,$active->revision);
        self::assertSame('deprecated',$deprecated->status);
        self::assertSame(3,$deprecated->revision);
    }

    public function test_stale_revision_is_rejected(): void
    {
        $lens = EditorialLens::create('travel','Travel','Travel connections.',new GraphFilter(), 'editor');
        $this->expectException(DomainException::class);
        $lens->activate(9,'publisher');
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
