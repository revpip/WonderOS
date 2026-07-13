<?php

declare(strict_types=1);
namespace WonderOS\Core\Auth;

use DateTimeImmutable;
use DomainException;
use PDO;

final readonly class PdoAuthRepository implements AuthRepository
{
    public function __construct(private PDO $connection) {}

    public function findByEmail(string $email): ?array
    {
        $statement=$this->connection->prepare('SELECT uuid,email,display_name,password_hash,role,status FROM wonder_users WHERE lower(email)=lower(:email) LIMIT 1');
        $statement->execute(['email'=>trim($email)]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    public function findUserBySessionToken(string $token): ?User
    {
        $statement=$this->connection->prepare('SELECT u.uuid,u.email,u.display_name,u.role,u.status FROM wonder_sessions s JOIN wonder_users u ON u.uuid=s.user_uuid WHERE s.token_hash=:token_hash AND s.revoked_at IS NULL AND s.expires_at>NOW() LIMIT 1');
        $statement->execute(['token_hash'=>hash('sha256',$token)]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$this->hydrate($row);
    }

    public function createSession(User $user,string $tokenHash,DateTimeImmutable $expiresAt): void
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_sessions (user_uuid,token_hash,expires_at) VALUES (:user_uuid,:token_hash,:expires_at)');
        $statement->execute(['user_uuid'=>$user->uuid,'token_hash'=>$tokenHash,'expires_at'=>$expiresAt->format(DATE_ATOM)]);
    }

    public function revokeSession(string $tokenHash): void
    {
        $statement=$this->connection->prepare('UPDATE wonder_sessions SET revoked_at=NOW() WHERE token_hash=:token_hash AND revoked_at IS NULL');
        $statement->execute(['token_hash'=>$tokenHash]);
    }

    public function revokeUserSessions(string $userUuid): int
    {
        $statement=$this->connection->prepare('UPDATE wonder_sessions SET revoked_at=NOW() WHERE user_uuid=:uuid AND revoked_at IS NULL');
        $statement->execute(['uuid'=>$userUuid]); return $statement->rowCount();
    }

    public function createUser(User $user,string $passwordHash): void
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_users (uuid,email,display_name,password_hash,role,status) VALUES (:uuid,lower(:email),:display_name,:password_hash,:role,:status)');
        $statement->execute(['uuid'=>$user->uuid,'email'=>$user->email,'display_name'=>$user->displayName,'password_hash'=>$passwordHash,'role'=>$user->role->value,'status'=>$user->status]);
    }

    public function updateUser(string $uuid,Role $role,string $status): User
    {
        if(!in_array($status,['active','suspended'],true)) throw new DomainException('Account status is invalid.');
        $statement=$this->connection->prepare('UPDATE wonder_users SET role=:role,status=:status,updated_at=NOW() WHERE uuid=:uuid RETURNING uuid,email,display_name,role,status');
        $statement->execute(['uuid'=>$uuid,'role'=>$role->value,'status'=>$status]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        if($row===false) throw new DomainException('User account does not exist.');
        if($status==='suspended') $this->revokeUserSessions($uuid);
        return $this->hydrate($row);
    }

    public function users(): array
    {
        $rows=$this->connection->query('SELECT uuid,email,display_name,role,status FROM wonder_users ORDER BY display_name,email')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row):User=>$this->hydrate($row),$rows);
    }

    public function accountActivity(string $userUuid): array
    {
        $statement=$this->connection->prepare('SELECT created_at,expires_at,revoked_at,CASE WHEN revoked_at IS NULL AND expires_at>NOW() THEN true ELSE false END active FROM wonder_sessions WHERE user_uuid=:uuid ORDER BY created_at DESC LIMIT 30');
        $statement->execute(['uuid'=>$userUuid]); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hydrate(array $row): User
    {
        return new User((string)$row['uuid'],(string)$row['email'],(string)$row['display_name'],Role::from((string)$row['role']),(string)$row['status']);
    }
}