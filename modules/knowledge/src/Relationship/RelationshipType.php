<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Relationship;

use DomainException;

/** Defines one governed relationship meaning and its inverse presentation. */
final readonly class RelationshipType
{
    public function __construct(
        public string $type,
        public string $inverseType,
        public string $label,
        public string $inverseLabel,
        public string $description,
        public bool $symmetric = false,
        public string $status = 'active',
    ) {
        foreach ([$type, $inverseType, $label, $inverseLabel, $description] as $value) {
            if (trim($value) === '') {
                throw new DomainException('Relationship vocabulary fields cannot be empty.');
            }
        }
        if ($symmetric && $type !== $inverseType) {
            throw new DomainException('A symmetric relationship must use itself as its inverse.');
        }
        if (!in_array($status, ['active', 'deprecated'], true)) {
            throw new DomainException('Relationship type status is invalid.');
        }
    }

    public function presentationType(bool $outgoing): string
    {
        return $outgoing || $this->symmetric ? $this->type : $this->inverseType;
    }

    public function presentationLabel(bool $outgoing): string
    {
        return $outgoing || $this->symmetric ? $this->label : $this->inverseLabel;
    }
}
