<?php

declare(strict_types=1);

namespace WonderOS\Core\Auth;

use DomainException;

enum Role: string
{
    case Viewer = 'viewer';
    case Researcher = 'researcher';
    case Editor = 'editor';
    case Administrator = 'administrator';

    public function permits(string $ability): bool
    {
        $abilities = match ($this) {
            self::Viewer => ['knowledge.read'],
            self::Researcher => ['knowledge.read', 'knowledge.contribute'],
            self::Editor => ['knowledge.read', 'knowledge.contribute', 'editorial.manage'],
            self::Administrator => ['knowledge.read', 'knowledge.contribute', 'editorial.manage', 'users.manage'],
        };

        return in_array($ability, $abilities, true);
    }

    public function assertPermits(string $ability): void
    {
        if (!$this->permits($ability)) {
            throw new DomainException(sprintf('Role "%s" cannot perform "%s".', $this->value, $ability));
        }
    }
}
