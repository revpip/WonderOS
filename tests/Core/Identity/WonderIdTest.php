<?php

declare(strict_types=1);

namespace WonderOS\Tests\Core\Identity;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;

final class WonderIdTest extends TestCase
{
    public function test_it_formats_and_parses_entity_ids(): void
    {
        $id = WonderId::entity(1);

        self::assertSame('WND-ENT-000001', (string) $id);
        self::assertTrue($id->equals(WonderId::parse('wnd-ent-000001')));
    }

    public function test_it_rejects_invalid_sequences(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WonderId::entity(0);
    }
}
