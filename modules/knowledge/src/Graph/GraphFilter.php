<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Graph;

use DomainException;
use WonderOS\Knowledge\Relationship\Relationship;

/** Defines editorial constraints applied during graph traversal. */
final readonly class GraphFilter
{
    /** @param list<string> $types @param list<string> $statuses */
    public function __construct(
        public array $types = [],
        public array $statuses = [],
        public float $minimumConfidence = 0.0,
    ) {
        if ($minimumConfidence < 0.0 || $minimumConfidence > 1.0) {
            throw new DomainException('Graph minimum confidence must be between 0 and 1.');
        }

        foreach ($types as $type) {
            if (!preg_match('/^[a-z0-9_]+$/', $type)) {
                throw new DomainException(sprintf('Graph relationship type "%s" is invalid.', $type));
            }
        }

        foreach ($statuses as $status) {
            if (!in_array($status, ['draft', 'review', 'approved', 'archived'], true)) {
                throw new DomainException(sprintf('Graph relationship status "%s" is invalid.', $status));
            }
        }
    }

    public function accepts(Relationship $relationship): bool
    {
        if ($this->types !== [] && !in_array($relationship->type(), $this->types, true)) {
            return false;
        }

        if ($this->statuses !== [] && !in_array($relationship->status(), $this->statuses, true)) {
            return false;
        }

        return $relationship->confidence() >= $this->minimumConfidence;
    }

    /** @return array{types:list<string>,statuses:list<string>,minimum_confidence:float} */
    public function toArray(): array
    {
        return [
            'types' => $this->types,
            'statuses' => $this->statuses,
            'minimum_confidence' => $this->minimumConfidence,
        ];
    }
}
