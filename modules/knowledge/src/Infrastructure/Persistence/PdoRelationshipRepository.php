<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use PDOException;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Relationship\Relationship;
use WonderOS\Knowledge\Relationship\RelationshipRepository;

final readonly class PdoRelationshipRepository implements RelationshipRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Relationship $relationship): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO entity_relationships (
                uuid, source_wonder_id, target_wonder_id, relationship_type, context, confidence, status
             ) VALUES (
                :uuid, :source, :target, :type, :context, :confidence, :status
             )',
        );

        try {
            $statement->execute([
                'uuid' => $relationship->uuid(),
                'source' => (string) $relationship->sourceId(),
                'target' => (string) $relationship->targetId(),
                'type' => $relationship->type(),
                'context' => $relationship->context(),
                'confidence' => $relationship->confidence(),
                'status' => $relationship->status(),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new DomainException('This relationship already exists.', previous: $exception);
            }
            throw $exception;
        }
    }

    public function forEntity(WonderId $entityId): array
    {
        $statement = $this->connection->prepare(
            'SELECT uuid, source_wonder_id, target_wonder_id, relationship_type, context, confidence, status
             FROM entity_relationships
             WHERE source_wonder_id = :source OR target_wonder_id = :target
             ORDER BY relationship_type, created_at',
        );
        $statement->execute([
            'source' => (string) $entityId,
            'target' => (string) $entityId,
        ]);

        return array_map(
            static fn (array $row): Relationship => Relationship::reconstitute(
                (string) $row['uuid'],
                WonderId::parse((string) $row['source_wonder_id']),
                WonderId::parse((string) $row['target_wonder_id']),
                (string) $row['relationship_type'],
                $row['context'] === null ? null : (string) $row['context'],
                (float) $row['confidence'],
                (string) $row['status'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }
}
