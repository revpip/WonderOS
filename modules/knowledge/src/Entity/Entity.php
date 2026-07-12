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
        private string $uuid,
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

        self::validate($canonicalName, $family, $type, $confidence);

        $entity = new self(
            self::generateUuidV4(),
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

    public static function reconstitute(
        string $uuid,
        WonderId $id,
        string $canonicalName,
        string $slug,
        string $family,
        string $type,
        EntityStatus $status,
        float $confidence,
        int $revision,
    ): self {
        self::validate(trim($canonicalName), trim($family), trim($type), $confidence);

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new DomainException('Entity UUID is invalid.');
        }

        if ($revision < 1) {
            throw new DomainException('Entity revision must be at least 1.');
        }

        return new self(
            strtolower($uuid),
            $id,
            trim($canonicalName),
            trim($slug),
            trim($family),
            trim($type),
            $status,
            $confidence,
            $revision,
        );
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

    public function uuid(): string { return $this->uuid; }
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

    private static function validate(string $canonicalName, string $family, string $type, float $confidence): void
    {
        if ($canonicalName === '') {
            throw new DomainException('Canonical name cannot be empty.');
        }
        if ($family === '' || $type === '') {
            throw new DomainException('Entity family and type are required.');
        }
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new DomainException('Confidence must be between 0 and 1.');
        }
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
