<?php

declare(strict_types=1);

namespace WonderOS\Core\Auth;

use DateTimeImmutable;
use DomainException;
use PDO;

final readonly class PdoInvitationRepository implements InvitationRepository
{
    public function __construct(private PDO $connection) {}

    public function create(string $uuid, string $email, string $displayName, Role $role, string $tokenHash, User $inviter, DateTimeImmutable $expiresAt): array
    {
        $statement = $this->connection->prepare('INSERT INTO wonder_user_invitations (uuid,email,display_name,role,token_hash,invited_by,expires_at) VALUES (:uuid,lower(:email),:display_name,:role,:token_hash,:invited_by,:expires_at)');
        try {
            $statement->execute(['uuid'=>$uuid,'email'=>trim($email),'display_name'=>trim($displayName),'role'=>$role->value,'token_hash'=>$tokenHash,'invited_by'=>$inviter->uuid,'expires_at'=>$expiresAt->format(DATE_ATOM)]);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23505') throw new DomainException('A pending invitation already exists for this email address.', previous:$exception);
            throw $exception;
        }
        return ['uuid'=>$uuid,'email'=>strtolower(trim($email)),'display_name'=>trim($displayName),'role'=>$role->value,'expires_at'=>$expiresAt->format(DATE_ATOM),'status'=>'pending'];
    }

    public function findUsableByToken(string $token): ?array
    {
        $statement=$this->connection->prepare('SELECT uuid,email,display_name,role,expires_at FROM wonder_user_invitations WHERE token_hash=:token_hash AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1');
        $statement->execute(['token_hash'=>hash('sha256',$token)]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    public function invitations(): array
    {
        return $this->connection->query("SELECT uuid,email,display_name,role,expires_at,accepted_at,revoked_at,created_at,CASE WHEN accepted_at IS NOT NULL THEN 'accepted' WHEN revoked_at IS NOT NULL THEN 'revoked' WHEN expires_at<=NOW() THEN 'expired' ELSE 'pending' END AS status FROM wonder_user_invitations ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accept(string $uuid, User $user, string $passwordHash): void
    {
        $this->connection->beginTransaction();
        try {
            $invite=$this->connection->prepare('UPDATE wonder_user_invitations SET accepted_at=NOW() WHERE uuid=:uuid AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()');
            $invite->execute(['uuid'=>$uuid]);
            if($invite->rowCount()!==1) throw new DomainException('This invitation is invalid, expired or already used.');
            $userInsert=$this->connection->prepare('INSERT INTO wonder_users (uuid,email,display_name,password_hash,role,status) VALUES (:uuid,lower(:email),:display_name,:password_hash,:role,:status)');
            $userInsert->execute(['uuid'=>$user->uuid,'email'=>$user->email,'display_name'=>$user->displayName,'password_hash'=>$passwordHash,'role'=>$user->role->value,'status'=>$user->status]);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function revoke(string $uuid): void
    {
        $statement=$this->connection->prepare('UPDATE wonder_user_invitations SET revoked_at=NOW() WHERE uuid=:uuid AND accepted_at IS NULL AND revoked_at IS NULL');
        $statement->execute(['uuid'=>$uuid]);
        if($statement->rowCount()!==1) throw new DomainException('Only a pending invitation can be revoked.');
    }
}