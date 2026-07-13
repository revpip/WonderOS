<?php

declare(strict_types=1);

namespace WonderOS\Core\Auth;

use DateInterval;
use DateTimeImmutable;
use DomainException;

final readonly class AuthService
{
    public function __construct(private AuthRepository $repository) {}

    /** @return array{token:string,expires_at:string,user:User} */
    public function login(string $email, string $password): array
    {
        $record = $this->repository->findByEmail($email);
        if ($record === null || !password_verify($password, (string)$record['password_hash'])) {
            throw new DomainException('The email address or password is incorrect.');
        }

        $user = new User(
            (string)$record['uuid'],
            (string)$record['email'],
            (string)$record['display_name'],
            Role::from((string)$record['role']),
            (string)$record['status'],
        );
        $user->assertActive();

        $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT8H'));
        $this->repository->createSession($user, hash('sha256', $token), $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt->format(DATE_ATOM), 'user' => $user];
    }

    public function authenticate(?string $authorisation): User
    {
        if ($authorisation === null || !preg_match('/^Bearer\s+(.+)$/i', trim($authorisation), $matches)) {
            throw new DomainException('A Bearer session token is required.');
        }

        $user = $this->repository->findUserBySessionToken($matches[1]);
        if ($user === null) {
            throw new DomainException('The WonderOS session is invalid or has expired.');
        }
        $user->assertActive();
        return $user;
    }

    public function logout(?string $authorisation): void
    {
        if ($authorisation !== null && preg_match('/^Bearer\s+(.+)$/i', trim($authorisation), $matches)) {
            $this->repository->revokeSession(hash('sha256', $matches[1]));
        }
    }
}
