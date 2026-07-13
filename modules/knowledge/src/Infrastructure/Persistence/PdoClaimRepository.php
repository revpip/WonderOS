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
}