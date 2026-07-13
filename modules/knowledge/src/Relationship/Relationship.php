<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Relationship;

use DomainException;
use WonderOS\Core\Identity\WonderId;

/** Represents a typed, directional edge between two canonical entities. */
final readonly class Relationship
{
    private function __construct(
        private string $uuid,
        private WonderId $sourceId,
        private WonderId $targetId,
        private string $type,
        private ?string $context,
        private float $confidence,
        private string $status,
    ) {
    }

    public static function create(
        WonderId $sourceId,
        WonderId $targetId,
        string $type,
        ?string $context = null,
        float $confidence = 0.5,
    ): self {
        $type = self::normaliseType($type);
        $context = $context === null ? null : trim($context);

        if ((string) $sourceId === (string) $targetId) {
            throw new DomainException('An entity cannot relate to itself.');
        }
        if ($type === '') {
            throw new DomainException('Relationship type is required.');
        }
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new DomainException('Relationship confidence must be between 0 and 1.');
        }

        return new self(
            self::uuidV4(),
            $sourceId,
            $targetId,
            $type,
            $context === '' ? null : $context,
            $confidence,
            'draft',
        );
    }

    public static function reconstitute(
        string $uuid,
        WonderId $sourceId,
        WonderId $targetId,
        string $type,
        ?string $context,
        float $confidence,
        string $status,
    ): self {
        return new self($uuid, $sourceId, $targetId, $type, $context, $confidence, $status);
    }

    public function uuid(): string { return $this->uuid; }
    public function sourceId(): WonderId { return $this->sourceId; }
    public function targetId(): WonderId { return $this->targetId; }
    public function type(): string { return $this->type; }
    public function context(): ?string { return $this->context; }
    public function confidence(): float { return $this->confidence; }
    public function status(): string { return $this->status; }

    public function isOutgoingFrom(WonderId $entityId): bool
    {
        return (string) $this->sourceId === (string) $entityId;
    }

    public function isIncomingTo(WonderId $entityId): bool
    {
        return (string) $this->targetId === (string) $entityId;
    }

    public function relatedEntityIdFor(WonderId $entityId): WonderId
    {
        if ($this->isOutgoingFrom($entityId)) {
            return $this->targetId;
        }
        if ($this->isIncomingTo($entityId)) {
            return $this->sourceId;
        }

        throw new DomainException(sprintf(
            'Entity %s is not part of relationship %s.',
            (string) $entityId,
            $this->uuid,
        ));
    }

    private static function normaliseType(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
