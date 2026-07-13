<?php

declare(strict_types=1);
namespace WonderOS\Core\Auth;

interface AuthRepository
{
    public function findByEmail(string $email): ?array;
    public function findUserBySessionToken(string $token): ?User;
    public function createSession(User $user, string $tokenHash, \DateTimeImmutable $expiresAt): void;
    public function revokeSession(string $tokenHash): void;
    public function revokeUserSessions(string $userUuid): int;
    public function createUser(User $user, string $passwordHash): void;
    public function updateUser(string $uuid, Role $role, string $status): User;

    /** @return list<User> */
    public function users(): array;

    /** @return list<array<string,mixed>> */
    public function accountActivity(string $userUuid): array;
}