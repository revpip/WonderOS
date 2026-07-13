<?php

declare(strict_types=1);
namespace WonderOS\Core\Auth;

interface AuthRepository
{
    public function findByEmail(string $email): ?array;
    public function findUserBySessionToken(string $token): ?User;
    public function createSession(User $user, string $tokenHash, \DateTimeImmutable $expiresAt): void;
    public function revokeSession(string $tokenHash): void;
    public function revokeAllSessions(string $userUuid): int;
    public function createUser(User $user, string $passwordHash): void;
    public function updateUser(User $user): void;
    public function recordActivity(string $actorUuid, string $action, ?string $subjectUuid = null, array $context = []): void;

    /** @return list<User> */
    public function users(): array;

    /** @return list<array<string,mixed>> */
    public function activity(int $limit = 100): array;
}