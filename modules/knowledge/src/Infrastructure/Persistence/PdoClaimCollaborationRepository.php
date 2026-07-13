<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use WonderOS\Knowledge\Claim\ClaimCollaborationRepository;

final readonly class PdoClaimCollaborationRepository implements ClaimCollaborationRepository
{
    public function __construct(private PDO $connection) {}

    public function assign(array $assignment): array
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_claim_assignments (uuid,claim_wonder_id,assignee_uuid,assigned_by,assignment_type,due_at) VALUES (:uuid,:claim_wonder_id,:assignee_uuid,:assigned_by,:assignment_type,:due_at) RETURNING *');
        $statement->execute($assignment); return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAssignment(string $uuid,string $status,string $actorUuid): array
    {
        if(!in_array($status,['open','completed','cancelled'],true)) throw new DomainException('Unsupported assignment status.');
        $statement=$this->connection->prepare("UPDATE wonder_claim_assignments SET status=:status,completed_at=CASE WHEN :status='completed' THEN NOW() ELSE NULL END WHERE uuid=:uuid RETURNING *");
        $statement->execute(['status'=>$status,'uuid'=>$uuid]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        if($row===false) throw new DomainException('The assignment does not exist.'); return $row;
    }

    public function comment(array $comment): array
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_claim_comments (uuid,claim_wonder_id,author_uuid,parent_uuid,body,mentions) VALUES (:uuid,:claim_wonder_id,:author_uuid,:parent_uuid,:body,CAST(:mentions AS jsonb)) RETURNING *');
        $comment['mentions']=json_encode($comment['mentions']??[],JSON_THROW_ON_ERROR); $statement->execute($comment); return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function assignmentsForClaim(string $claimWonderId): array
    {
        $statement=$this->connection->prepare('SELECT a.*,u.email AS assignee_email,u.display_name AS assignee_name,b.email AS assigned_by_email FROM wonder_claim_assignments a JOIN wonder_users u ON u.uuid=a.assignee_uuid JOIN wonder_users b ON b.uuid=a.assigned_by WHERE a.claim_wonder_id=:claim ORDER BY (a.status=\'open\') DESC,a.due_at NULLS LAST,a.created_at');
        $statement->execute(['claim'=>$claimWonderId]); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function commentsForClaim(string $claimWonderId): array
    {
        $statement=$this->connection->prepare('SELECT c.*,u.email AS author_email,u.display_name AS author_name FROM wonder_claim_comments c JOIN wonder_users u ON u.uuid=c.author_uuid WHERE c.claim_wonder_id=:claim ORDER BY c.created_at');
        $statement->execute(['claim'=>$claimWonderId]); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function workForUser(string $userUuid,int $limit=100): array
    {
        $statement=$this->connection->prepare('SELECT a.*,c.statement,c.status AS claim_status FROM wonder_claim_assignments a JOIN wonder_claims c ON c.wonder_id=a.claim_wonder_id WHERE a.assignee_uuid=:user AND a.status=\'open\' ORDER BY a.due_at NULLS LAST,a.created_at LIMIT :limit');
        $statement->bindValue('user',$userUuid); $statement->bindValue('limit',$limit,PDO::PARAM_INT); $statement->execute(); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
