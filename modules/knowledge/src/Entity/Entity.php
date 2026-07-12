<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Entity;

use DomainException;
use WonderOS\Core\Identity\WonderId;

/** Represents a canonical subject within WonderOS. */
final class Entity
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        private WonderId $id,
        private string $canonicalName,
        private string $slug,
        private string $family,
        private string $type,
        private EntityStatus $status,
        private float $confidence,
        private int $revision,
    ) {
    }

    public static function create(
        WonderId $id,
        string $canonicalName,
        string $family,
        string $type,
        float $confidence = 0.5,
    ): self {
        $canonicalName = trim($canonicalName);
        $family = trim($family);
        $type = trim($type);

        if ($canonicalName === '') {
            throw new DomainException('Canonical name cannot be empty.');
        }
        if ($family === '' || $type === '') {
            throw new DomainException('Entity family and type are required.');
        }
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new DomainException('Confidence must be between 0 and 1.');
        }

        $entity = new self(
            $id,
            $canonicalName,
            self::slugify($canonicalName),
            $family,
            $type,
            EntityStatus::Draft,
            $confidence,
            1,
        );

        $entity->events[] = new EntityCreated($id, $canonicalName, new \DateTimeImmutable());

        return $entity;
    }

    public function rename(string $canonicalName, int $expectedRevision): void
    {
        $this->guardRevision($expectedRevision);
        $canonicalName = trim($canonicalName);

        if ($canonicalName === '') {
            throw new DomainException('Canonical name cannot be empty.');
        }

        $this->canonicalName = $canonicalName;
        $this->slug = self::slugify($canonicalName);
        ++$this->revision;
    }

    public function id(): WonderId { return $this->id; }
    public function canonicalName(): string { return $this->canonicalName; }
    public function slug(): string { return $this->slug; }
    public function family(): string { return $this->family; }
    public function type(): string { return $this->type; }
    public function status(): EntityStatus { return $this->status; }
    public function confidence(): float { return $this->confidence; }
    public function revision(): int { return $this->revision; }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }

    private function guardRevision(int $expectedRevision): void
    {
        if ($expectedRevision !== $this->revision) {
            throw new DomainException(sprintf(
                'Revision conflict: expected %d, current revision is %d.',
                $expectedRevision,
                $this->revision,
            ));
        }
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
