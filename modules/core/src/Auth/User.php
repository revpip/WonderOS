<?php

declare(strict_types=1);

namespace WonderOS\Core\Auth;

use DomainException;

final readonly class User
{
    public function __construct(
        public string $uuid,
        public string $email,
        public string $displayName,
        public Role $role,
        public string $status = 'active',
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('A valid user email address is required.');
        }
        if (trim($displayName) === '') {
            throw new DomainException('User display name is required.');
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new DomainException('User status is invalid.');
        }
    }

    public function assertActive(): void
    {
        if ($this->status !== 'active') {
            throw new DomainException('This WonderOS account is suspended.');
        }
    }
}
