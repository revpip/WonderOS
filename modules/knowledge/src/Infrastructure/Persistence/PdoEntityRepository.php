<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use PDOException;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;
use WonderOS\Knowledge\Entity\EntityRevisionConflict;
use WonderOS\Knowledge\Entity\EntityStatus;

/** Persists canonical entities in PostgreSQL using PDO. */
final readonly class PdoEntityRepository implements EntityRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function nextIdentity(): WonderId
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                "UPDATE wonder_id_sequences
                 SET last_value = last_value + 1
                 WHERE prefix = 'ENT' AND last_value < 999999
                 RETURNING last_value",
            );
            $statement->execute();
            $sequence = $statement->fetchColumn();

            if ($sequence === false) {
                throw new DomainException('The entity Wonder ID sequence is exhausted or unavailable.');
            }

            $this->connection->commit();

            return WonderId::entity((int) $sequence);
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function save(Entity $entity, ?int $expectedRevision = null): void
    {
        if ($expectedRevision === null) {
            $this->insert($entity);
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE entities
             SET canonical_name = :canonical_name,
                 slug = :slug,
                 family = :family,
                 entity_type = :entity_type,
                 status = :status,
                 confidence = :confidence,
                 revision = :revision,
                 updated_at = NOW()
             WHERE wonder_id = :wonder_id
               AND revision = :expected_revision',
        );

        $statement->execute($this->parameters($entity) + [
            'expected_revision' => $expectedRevision,
        ]);

        if ($statement->rowCount() !== 1) {
            throw EntityRevisionConflict::forEntity($entity->id(), $expectedRevision);
        }
    }

    public function get(WonderId $id): Entity
    {
        return $this->find($id) ?? throw EntityNotFound::withId($id);
    }

    public function find(WonderId $id): ?Entity
    {
        $statement = $this->connection->prepare(
            'SELECT uuid, wonder_id, canonical_name, slug, family, entity_type, status, confidence, revision
             FROM entities
             WHERE wonder_id = :wonder_id',
        );
        $statement->execute(['wonder_id' => (string) $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<Entity> */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $statement = $this->connection->prepare(
            'SELECT uuid, wonder_id, canonical_name, slug, family, entity_type, status, confidence, revision
             FROM entities
             WHERE canonical_name ILIKE :query
                OR slug ILIKE :query
                OR wonder_id ILIKE :query
             ORDER BY
                CASE WHEN LOWER(canonical_name) = LOWER(:exact) THEN 0 ELSE 1 END,
                canonical_name ASC
             LIMIT :limit',
        );
        $statement->bindValue('query', '%' . $query . '%');
        $statement->bindValue('exact', $query);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Entity => $this->hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function findBySlug(string $slug): ?Entity
    {
        $statement = $this->connection->prepare(
            'SELECT uuid, wonder_id, canonical_name, slug, family, entity_type, status, confidence, revision
             FROM entities
             WHERE slug = :slug',
        );
        $statement->execute(['slug' => trim(strtolower($slug))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    private function insert(Entity $entity): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO entities (
                uuid, wonder_id, canonical_name, slug, family, entity_type,
                status, confidence, revision
             ) VALUES (
                :uuid, :wonder_id, :canonical_name, :slug, :family, :entity_type,
                :status, :confidence, :revision
             )',
        );

        try {
            $statement->execute($this->parameters($entity));
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new DomainException(sprintf(
                    'Entity %s or slug %s already exists.',
                    (string) $entity->id(),
                    $entity->slug(),
                ), previous: $exception);
            }

            throw $exception;
        }
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Entity
    {
        return Entity::reconstitute(
            (string) $row['uuid'],
            WonderId::parse((string) $row['wonder_id']),
            (string) $row['canonical_name'],
            (string) $row['slug'],
            (string) $row['family'],
            (string) $row['entity_type'],
            EntityStatus::from((string) $row['status']),
            (float) $row['confidence'],
            (int) $row['revision'],
        );
    }

    /** @return array<string, int|float|string> */
    private function parameters(Entity $entity): array
    {
        return [
            'uuid' => $entity->uuid(),
            'wonder_id' => (string) $entity->id(),
            'canonical_name' => $entity->canonicalName(),
            'slug' => $entity->slug(),
            'family' => $entity->family(),
            'entity_type' => $entity->type(),
            'status' => $entity->status()->value,
            'confidence' => $entity->confidence(),
            'revision' => $entity->revision(),
        ];
    }
}
