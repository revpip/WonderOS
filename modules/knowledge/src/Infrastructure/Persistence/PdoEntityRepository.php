<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use PDOException;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityAlias;
use WonderOS\Knowledge\Entity\EntityAliasType;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;
use WonderOS\Knowledge\Entity\EntityRevisionConflict;
use WonderOS\Knowledge\Entity\EntityStatus;

final readonly class PdoEntityRepository implements EntityRepository
{
    public function __construct(private PDO $connection) {}

    public function nextIdentity(): WonderId
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare("UPDATE wonder_id_sequences SET last_value = last_value + 1 WHERE prefix = 'ENT' AND last_value < 999999 RETURNING last_value");
            $statement->execute();
            $sequence = $statement->fetchColumn();
            if ($sequence === false) throw new DomainException('The entity Wonder ID sequence is exhausted or unavailable.');
            $this->connection->commit();
            return WonderId::entity((int) $sequence);
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function save(Entity $entity, ?int $expectedRevision = null): void
    {
        if ($expectedRevision === null) { $this->insert($entity); return; }
        $statement = $this->connection->prepare('UPDATE entities SET canonical_name=:canonical_name, slug=:slug, family=:family, entity_type=:entity_type, status=:status, confidence=:confidence, revision=:revision, updated_at=NOW() WHERE wonder_id=:wonder_id AND revision=:expected_revision');
        $statement->execute($this->parameters($entity) + ['expected_revision' => $expectedRevision]);
        if ($statement->rowCount() !== 1) throw EntityRevisionConflict::forEntity($entity->id(), $expectedRevision);
    }

    public function get(WonderId $id): Entity { return $this->find($id) ?? throw EntityNotFound::withId($id); }

    public function find(WonderId $id): ?Entity
    {
        $statement = $this->connection->prepare('SELECT uuid,wonder_id,canonical_name,slug,family,entity_type,status,confidence,revision FROM entities WHERE wonder_id=:wonder_id');
        $statement->execute(['wonder_id' => (string) $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrate($row);
    }

    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $limit = max(1, min($limit, 50));
        $like = '%' . $query . '%';
        $statement = $this->connection->prepare('SELECT DISTINCT e.uuid,e.wonder_id,e.canonical_name,e.slug,e.family,e.entity_type,e.status,e.confidence,e.revision FROM entities e LEFT JOIN entity_aliases a ON a.entity_wonder_id=e.wonder_id WHERE e.canonical_name ILIKE :name_query OR e.slug ILIKE :slug_query OR e.wonder_id ILIKE :id_query OR a.alias ILIKE :alias_query ORDER BY CASE WHEN LOWER(e.canonical_name)=LOWER(:exact_name) THEN 0 WHEN LOWER(a.alias)=LOWER(:exact_alias) THEN 1 ELSE 2 END, e.canonical_name ASC LIMIT :limit');
        $statement->bindValue('name_query', $like);
        $statement->bindValue('slug_query', $like);
        $statement->bindValue('id_query', $like);
        $statement->bindValue('alias_query', $like);
        $statement->bindValue('exact_name', $query);
        $statement->bindValue('exact_alias', $query);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn(array $row): Entity => $this->hydrate($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findBySlug(string $slug): ?Entity
    {
        $statement = $this->connection->prepare('SELECT uuid,wonder_id,canonical_name,slug,family,entity_type,status,confidence,revision FROM entities WHERE slug=:slug');
        $statement->execute(['slug' => trim(strtolower($slug))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->hydrate($row);
    }

    public function addAlias(EntityAlias $alias): void
    {
        $this->get($alias->entityId);
        $statement = $this->connection->prepare('INSERT INTO entity_aliases (entity_wonder_id,alias,normalised_alias,alias_type,language) VALUES (:entity_id,:alias,:normalised,:type,:language)');
        try {
            $statement->execute(['entity_id'=>(string)$alias->entityId,'alias'=>trim($alias->alias),'normalised'=>$alias->normalised(),'type'=>$alias->type->value,'language'=>$alias->language]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') throw new DomainException(sprintf('Alias "%s" already belongs to a canonical entity.', $alias->alias), previous:$exception);
            throw $exception;
        }
    }

    public function aliases(WonderId $entityId): array
    {
        $this->get($entityId);
        $statement = $this->connection->prepare('SELECT alias,alias_type,language FROM entity_aliases WHERE entity_wonder_id=:entity_id ORDER BY alias_type,alias');
        $statement->execute(['entity_id'=>(string)$entityId]);
        return array_map(fn(array $row): EntityAlias => new EntityAlias($entityId,(string)$row['alias'],EntityAliasType::from((string)$row['alias_type']),$row['language'] !== null ? (string)$row['language'] : null), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function insert(Entity $entity): void
    {
        $statement = $this->connection->prepare('INSERT INTO entities (uuid,wonder_id,canonical_name,slug,family,entity_type,status,confidence,revision) VALUES (:uuid,:wonder_id,:canonical_name,:slug,:family,:entity_type,:status,:confidence,:revision)');
        try { $statement->execute($this->parameters($entity)); }
        catch (PDOException $exception) {
            if ($exception->getCode() === '23505') throw new DomainException(sprintf('Entity %s or slug %s already exists.', (string)$entity->id(), $entity->slug()), previous:$exception);
            throw $exception;
        }
    }

    private function hydrate(array $row): Entity
    {
        return Entity::reconstitute((string)$row['uuid'],WonderId::parse((string)$row['wonder_id']),(string)$row['canonical_name'],(string)$row['slug'],(string)$row['family'],(string)$row['entity_type'],EntityStatus::from((string)$row['status']),(float)$row['confidence'],(int)$row['revision']);
    }

    private function parameters(Entity $entity): array
    {
        return ['uuid'=>$entity->uuid(),'wonder_id'=>(string)$entity->id(),'canonical_name'=>$entity->canonicalName(),'slug'=>$entity->slug(),'family'=>$entity->family(),'entity_type'=>$entity->type(),'status'=>$entity->status()->value,'confidence'=>$entity->confidence(),'revision'=>$entity->revision()];
    }
}
