<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Infrastructure\Persistence;

use PDO;
use WonderOS\Knowledge\Claim\ClaimRepository;

final readonly class PdoClaimRepository implements ClaimRepository
{
    public function __construct(private PDO $connection) {}

    public function nextIdentity(string $prefix): string
    {
        $prefix = strtoupper($prefix);
        $this->connection->beginTransaction();
        try {
            $statement=$this->connection->prepare('UPDATE wonder_knowledge_sequences SET last_value=last_value+1 WHERE prefix=:prefix RETURNING last_value');
            $statement->execute(['prefix'=>$prefix]);
            $value=$statement->fetchColumn();
            if($value===false) throw new \DomainException('Unknown knowledge identity prefix.');
            $this->connection->commit();
            return sprintf('WND-%s-%06d',$prefix,(int)$value);
        } catch (\Throwable $exception) {
            if($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function createSource(array $source): array
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_sources (wonder_id,title,url,publisher,published_at,source_type) VALUES (:wonder_id,:title,:url,:publisher,:published_at,:source_type) RETURNING *');
        $statement->execute($source);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function createClaim(array $claim): array
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_claims (wonder_id,entity_wonder_id,statement,claim_type,confidence) VALUES (:wonder_id,:entity_wonder_id,:statement,:claim_type,:confidence) RETURNING *');
        $statement->execute($claim);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function addEvidence(array $evidence): array
    {
        $statement=$this->connection->prepare('INSERT INTO wonder_evidence (uuid,claim_wonder_id,source_wonder_id,locator,excerpt,stance,strength) VALUES (:uuid,:claim_wonder_id,:source_wonder_id,:locator,:excerpt,:stance,:strength) RETURNING *');
        $statement->execute($evidence);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function claimsForEntity(string $entityWonderId): array
    {
        $statement=$this->connection->prepare('SELECT * FROM wonder_claims WHERE entity_wonder_id=:entity ORDER BY created_at,wonder_id');
        $statement->execute(['entity'=>$entityWonderId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function evidenceForClaim(string $claimWonderId): array
    {
        $statement=$this->connection->prepare('SELECT e.*,s.title AS source_title,s.url,s.publisher,s.published_at,s.source_type FROM wonder_evidence e JOIN wonder_sources s ON s.wonder_id=e.source_wonder_id WHERE e.claim_wonder_id=:claim ORDER BY e.created_at');
        $statement->execute(['claim'=>$claimWonderId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function source(string $sourceWonderId): ?array
    {
        $statement=$this->connection->prepare('SELECT * FROM wonder_sources WHERE wonder_id=:id');
        $statement->execute(['id'=>$sourceWonderId]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    public function claim(string $claimWonderId): ?array
    {
        $statement=$this->connection->prepare('SELECT * FROM wonder_claims WHERE wonder_id=:id');
        $statement->execute(['id'=>$claimWonderId]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    public function reviseClaim(string $claimWonderId,array $changes,int $expectedRevision,string $changedBy,?string $changeNote): array
    {
        $this->connection->beginTransaction();
        try {
            $current=$this->claim($claimWonderId);
            if($current===null) throw new \DomainException('The claim does not exist.');
            if((int)$current['revision']!==$expectedRevision) throw new \DomainException('The claim changed after it was opened. Reload before saving.');
            $next=$expectedRevision+1;
            $statement=$this->connection->prepare('UPDATE wonder_claims SET statement=:statement,claim_type=:claim_type,confidence=:confidence,status=:status,revision=:next_revision,reviewed_by=:reviewed_by,reviewed_at=CASE WHEN :status IN (\'approved\',\'disputed\',\'archived\') THEN NOW() ELSE reviewed_at END,updated_at=NOW() WHERE wonder_id=:id AND revision=:expected RETURNING *');
            $statement->execute([
                'statement'=>$changes['statement']??$current['statement'],
                'claim_type'=>$changes['claim_type']??$current['claim_type'],
                'confidence'=>$changes['confidence']??$current['confidence'],
                'status'=>$changes['status']??$current['status'],
                'next_revision'=>$next,'reviewed_by'=>$changedBy,'id'=>$claimWonderId,'expected'=>$expectedRevision,
            ]);
            $updated=$statement->fetch(PDO::FETCH_ASSOC);
            if($updated===false) throw new \DomainException('The claim revision conflicted with another edit.');
            $history=$this->connection->prepare('INSERT INTO wonder_claim_revisions (claim_wonder_id,revision,statement,claim_type,confidence,status,changed_by,change_note) VALUES (:claim,:revision,:statement,:claim_type,:confidence,:status,:changed_by,:change_note)');
            $history->execute(['claim'=>$claimWonderId,'revision'=>$next,'statement'=>$updated['statement'],'claim_type'=>$updated['claim_type'],'confidence'=>$updated['confidence'],'status'=>$updated['status'],'changed_by'=>$changedBy,'change_note'=>$changeNote]);
            $this->connection->commit();
            return $updated;
        } catch (\Throwable $exception) {
            if($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function claimHistory(string $claimWonderId): array
    {
        $statement=$this->connection->prepare('SELECT r.*,u.email AS changed_by_email,u.display_name AS changed_by_name FROM wonder_claim_revisions r JOIN wonder_users u ON u.uuid=r.changed_by WHERE r.claim_wonder_id=:claim ORDER BY r.revision DESC');
        $statement->execute(['claim'=>$claimWonderId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reviewQueue(array $statuses, int $limit = 100): array
    {
        $statuses=array_values(array_intersect($statuses,['draft','review','disputed']));
        if($statuses===[]) $statuses=['draft','review','disputed'];
        $placeholders=[]; $parameters=['limit'=>max(1,min(250,$limit))];
        foreach($statuses as $index=>$status){$key='status'.$index;$placeholders[]=':'.$key;$parameters[$key]=$status;}
        $sql='SELECT c.*,e.canonical_name AS entity_name,(SELECT COUNT(*) FROM wonder_evidence we WHERE we.claim_wonder_id=c.wonder_id) AS evidence_count FROM wonder_claims c JOIN entities e ON e.wonder_id=c.entity_wonder_id WHERE c.status IN ('.implode(',',$placeholders).') ORDER BY CASE c.status WHEN \'disputed\' THEN 0 WHEN \'review\' THEN 1 ELSE 2 END,c.updated_at NULLS LAST,c.created_at LIMIT :limit';
        $statement=$this->connection->prepare($sql);
        foreach($parameters as $key=>$value){$statement->bindValue(':'.$key,$value,$key==='limit'?PDO::PARAM_INT:PDO::PARAM_STR);}
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
