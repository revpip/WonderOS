<?php

declare(strict_types=1);

namespace WonderOS\Core\Identity;

use InvalidArgumentException;
use Stringable;

/** Represents a permanent, human-readable WonderOS identifier. */
final readonly class WonderId implements Stringable
{
    private const PATTERN = '/^WND-([A-Z]{3})-(\d{6})$/';

    private function __construct(
        private string $prefix,
        private int $sequence,
    ) {
    }

    public static function entity(int $sequence): self
    {
        return self::fromParts('ENT', $sequence);
    }

    public static function fromParts(string $prefix, int $sequence): self
    {
        $prefix = strtoupper(trim($prefix));

        if (!preg_match('/^[A-Z]{3}$/', $prefix)) {
            throw new InvalidArgumentException('Wonder ID prefix must contain exactly three letters.');
        }

        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidArgumentException('Wonder ID sequence must be between 1 and 999999.');
        }

        return new self($prefix, $sequence);
    }

    public static function parse(string $value): self
    {
        $value = strtoupper(trim($value));

        if (!preg_match(self::PATTERN, $value, $matches)) {
            throw new InvalidArgumentException(sprintf('Invalid Wonder ID: %s', $value));
        }

        return self::fromParts($matches[1], (int) $matches[2]);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function equals(self $other): bool
    {
        return (string) $this === (string) $other;
    }

    public function __toString(): string
    {
        return sprintf('WND-%s-%06d', $this->prefix, $this->sequence);
    }
}
