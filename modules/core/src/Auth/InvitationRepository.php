<?php

declare(strict_types=1);

namespace WonderOS\Core\Auth;

use DateTimeImmutable;

interface InvitationRepository
{
    /** @return array<string,mixed> */
    public function create(string $uuid, string $email, string $displayName, Role $role, string $tokenHash, User $inviter, DateTimeImmutable $expiresAt): array;

    /** @return array<string,mixed>|null */
    public function findUsableByToken(string $token): ?array;

    /** @return list<array<string,mixed>> */
    public function invitations(): array;

    public function accept(string $uuid, User $user, string $passwordHash): void;

    public function revoke(string $uuid): void;
}