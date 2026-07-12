<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use RuntimeException;
use WonderOS\Core\Identity\WonderId;

final class EntityRevisionConflict extends RuntimeException
{
    public static function forEntity(WonderId $id, int $expectedRevision): self
    {
        return new self(sprintf(
            'Entity %s could not be saved because revision %d is no longer current.',
            (string) $id,
            $expectedRevision,
        ));
    }
}
