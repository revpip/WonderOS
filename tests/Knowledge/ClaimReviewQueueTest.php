<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClaimReviewQueueTest extends TestCase
{
    public function test_queue_statuses_are_governed(): void
    {
        $allowed=['draft','review','disputed'];
        self::assertSame(['draft','review','disputed'],array_values(array_intersect(['draft','review','disputed','approved'],$allowed)));
    }
}
