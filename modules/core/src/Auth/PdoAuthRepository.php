<?php

declare(strict_types=1);
namespace WonderOS\Core\Auth;

use DateTimeImmutable;
use PDO;

final readonly class PdoAuthRepository implements AuthRepository
{
    public function __construct(private PDO $connection) {}

    public function findByEmail(string $email): ?array
    {
        $s=$this->connection->prepare('SELECT uuid,email,display_name,password_hash,role,status FROM wonder_users WHERE lower(email)=lower(:email) LIMIT 1');
        $s->execute(['email'=>trim($email)]); $row=$s->fetch(PDO::FETCH_ASSOC); return $row===false?null:$row;
    }

    public function findUserBySessionToken(string $token): ?User
    {
        $s=$this->connection->prepare('SELECT u.uuid,u.email,u.display_name,u.role,u.status FROM wonder_sessions s JOIN wonder_users u ON u.uuid=s.user_uuid WHERE s.token_hash=:token_hash AND s.revoked_at IS NULL AND s.expires_at>NOW() LIMIT 1');
        $s->execute(['token_hash'=>hash('sha256',$token)]); $row=$s->fetch(PDO::FETCH_ASSOC); return $row===false?null:$this->hydrate($row);
    }

    public function createSession(User $user,string $tokenHash,DateTimeImmutable $expiresAt): void
    { $s=$this->connection->prepare('INSERT INTO wonder_sessions (user_uuid,token_hash,expires_at) VALUES (:u,:t,:e)'); $s->execute(['u'=>$user->uuid,'t'=>$tokenHash,'e'=>$expiresAt->format(DATE_ATOM)]); }

    public function revokeSession(string $tokenHash): void
    { $s=$this->connection->prepare('UPDATE wonder_sessions SET revoked_at=NOW() WHERE token_hash=:t AND revoked_at IS NULL'); $s->execute(['t'=>$tokenHash]); }

    public function revokeAllSessions(string $userUuid): int
    { $s=$this->connection->prepare('UPDATE wonder_sessions SET revoked_at=NOW() WHERE user_uuid=:u AND revoked_at IS NULL'); $s->execute(['u'=>$userUuid]); return $s->rowCount(); }

    public function createUser(User $user,string $passwordHash): void
    { $s=$this->connection->prepare('INSERT INTO wonder_users (uuid,email,display_name,password_hash,role,status) VALUES (:u,lower(:e),:n,:p,:r,:s)'); $s->execute(['u'=>$user->uuid,'e'=>$user->email,'n'=>$user->displayName,'p'=>$passwordHash,'r'=>$user->role->value,'s'=>$user->status]); }

    public function updateUser(User $user): void
    { $s=$this->connection->prepare('UPDATE wonder_users SET email=lower(:e),display_name=:n,role=:r,status=:s,updated_at=NOW() WHERE uuid=:u'); $s->execute(['u'=>$user->uuid,'e'=>$user->email,'n'=>$user->displayName,'r'=>$user->role->value,'s'=>$user->status]); if($s->rowCount()!==1) throw new \DomainException('WonderOS user does not exist.'); }

    public function users(): array
    { $rows=$this->connection->query('SELECT uuid,email,display_name,role,status FROM wonder_users ORDER BY display_name,email')->fetchAll(PDO::FETCH_ASSOC); return array_map(fn(array $r):User=>$this->hydrate($r),$rows); }

    public function recordActivity(string $actorUuid,string $action,?string $subjectUuid=null,array $context=[]): void
    { $s=$this->connection->prepare('INSERT INTO wonder_account_activity (actor_uuid,subject_uuid,action,context) VALUES (:a,:s,:x,CAST(:c AS JSONB))'); $s->execute(['a'=>$actorUuid,'s'=>$subjectUuid,'x'=>$action,'c'=>json_encode($context,JSON_THROW_ON_ERROR)]); }

    public function activity(int $limit=100): array
    { $s=$this->connection->prepare('SELECT a.id,a.action,a.context,a.created_at,actor.email actor_email,subject.email subject_email FROM wonder_account_activity a JOIN wonder_users actor ON actor.uuid=a.actor_uuid LEFT JOIN wonder_users subject ON subject.uuid=a.subject_uuid ORDER BY a.created_at DESC LIMIT :limit'); $s->bindValue('limit',max(1,min(250,$limit)),PDO::PARAM_INT); $s->execute(); return $s->fetchAll(PDO::FETCH_ASSOC); }

    private function hydrate(array $r): User
    { return new User((string)$r['uuid'],(string)$r['email'],(string)$r['display_name'],Role::from((string)$r['role']),(string)$r['status']); }
}