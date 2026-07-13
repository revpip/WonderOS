<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Graph;

use DomainException;

/** Represents a named, reusable and revisioned editorial graph filter. */
final readonly class EditorialLens
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public GraphFilter $filter,
        public string $status = 'draft',
        public int $revision = 1,
        public string $updatedBy = 'system',
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new DomainException('Editorial lens slug must use lowercase kebab-case.');
        }
        if (trim($name) === '' || trim($description) === '') {
            throw new DomainException('Editorial lens name and description are required.');
        }
        if (!in_array($status, ['draft', 'active', 'deprecated'], true)) {
            throw new DomainException('Editorial lens status is invalid.');
        }
        if ($revision < 1) {
            throw new DomainException('Editorial lens revision must be at least 1.');
        }
        if (trim($updatedBy) === '') {
            throw new DomainException('Editorial lens editor identity is required.');
        }
    }

    public static function create(string $slug, string $name, string $description, GraphFilter $filter, string $editor): self
    {
        return new self($slug, $name, $description, $filter, 'draft', 1, trim($editor));
    }

    public function revise(string $name, string $description, GraphFilter $filter, int $expectedRevision, string $editor): self
    {
        $this->guardRevision($expectedRevision);
        return new self($this->slug, $name, $description, $filter, 'draft', $this->revision + 1, trim($editor));
    }

    public function activate(int $expectedRevision, string $editor): self
    {
        $this->guardRevision($expectedRevision);
        return new self($this->slug, $this->name, $this->description, $this->filter, 'active', $this->revision + 1, trim($editor));
    }

    public function deprecate(int $expectedRevision, string $editor): self
    {
        $this->guardRevision($expectedRevision);
        return new self($this->slug, $this->name, $this->description, $this->filter, 'deprecated', $this->revision + 1, trim($editor));
    }

    private function guardRevision(int $expectedRevision): void
    {
        if ($expectedRevision !== $this->revision) {
            throw new DomainException(sprintf(
                'Editorial lens revision conflict: expected %d, current revision is %d.',
                $expectedRevision,
                $this->revision,
            ));
        }
    }
}
