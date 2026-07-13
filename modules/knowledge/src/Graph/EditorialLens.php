<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Graph;

use DomainException;

/** Represents a named, reusable editorial graph filter. */
final readonly class EditorialLens
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public GraphFilter $filter,
        public string $status = 'active',
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new DomainException('Editorial lens slug must use lowercase kebab-case.');
        }
        if (trim($name) === '' || trim($description) === '') {
            throw new DomainException('Editorial lens name and description are required.');
        }
        if (!in_array($status, ['active', 'deprecated'], true)) {
            throw new DomainException('Editorial lens status is invalid.');
        }
    }
}
