<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use RuntimeException;
use WonderOS\Core\Identity\WonderId;

final class EntityNotFound extends RuntimeException
{
    public static function withId(WonderId $id): self
    {
        return new self(sprintf('Entity %s was not found.', (string) $id));
    }
}
