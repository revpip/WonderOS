<?php

declare(strict_types=1);

namespace WonderOS\Tests\Knowledge;

use DomainException;
use PHPUnit\Framework\TestCase;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityAlias;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;
use WonderOS\Knowledge\Graph\GraphTraversal;
use WonderOS\Knowledge\Relationship\Relationship;
use WonderOS\Knowledge\Relationship\RelationshipRepository;
use WonderOS\Knowledge\Relationship\RelationshipType;
use WonderOS\Knowledge\Relationship\RelationshipTypeRepository;

final class GraphTraversalTest extends TestCase
{
    public function test_it_traverses_multiple_hops_without_revisiting_cycles(): void
    {
        $entities = new GraphEntityRepository();
        $barnOwl = $entities->add('Barn Owl');
        $churchTowers = $entities->add('Church Towers');
        $historicChurches = $entities->add('Historic Churches');

        $relationships = new GraphRelationshipRepository([
            Relationship::create($barnOwl->id(), $churchTowers->id(), 'roosts_in'),
            Relationship::create($churchTowers->id(), $historicChurches->id(), 'part_of'),
            Relationship::create($historicChurches->id(), $barnOwl->id(), 'associated_with'),
        ]);

        $graph = (new GraphTraversal($entities, $relationships, new GraphTypeRepository()))
            ->traverse($barnOwl->id(), 3);

        self::assertCount(3, $graph['nodes']);
        self::assertCount(3, $graph['edges']);
        self::assertFalse($graph['truncated']);
        self::assertSame([0, 1, 2], array_column($graph['nodes'], 'distance'));
    }

    public function test_it_respects_depth_and_node_limits(): void
    {
        $entities = new GraphEntityRepository();
        $one = $entities->add('One');
        $two = $entities->add('Two');
        $three = $entities->add('Three');
        $relationships = new GraphRelationshipRepository([
            Relationship::create($one->id(), $two->id(), 'associated_with'),
            Relationship::create($two->id(), $three->id(), 'associated_with'),
        ]);

        $traversal = new GraphTraversal($entities, $relationships, new GraphTypeRepository());
        $depthOne = $traversal->traverse($one->id(), 1);
        $limited = $traversal->traverse($one->id(), 3, 2);

        self::assertCount(2, $depthOne['nodes']);
        self::assertCount(2, $limited['nodes']);
        self::assertTrue($limited['truncated']);
    }

    public function test_it_rejects_unbounded_depth(): void
    {
        $this->expectException(DomainException::class);
        (new GraphTraversal(new GraphEntityRepository(), new GraphRelationshipRepository([]), new GraphTypeRepository()))
            ->traverse(WonderId::entity(1), 4);
    }
}

final class GraphEntityRepository implements EntityRepository
{
    /** @var array<string,Entity> */
    private array $entities = [];
    private int $sequence = 0;

    public function add(string $name): Entity
    {
        $entity = Entity::create($this->nextIdentity(), $name, 'Knowledge', 'Concept');
        $this->save($entity);
        return $entity;
    }

    public function nextIdentity(): WonderId { return WonderId::entity(++$this->sequence); }
    public function save(Entity $entity, ?int $expectedRevision = null): void { $this->entities[(string)$entity->id()] = $entity; }
    public function get(WonderId $id): Entity { return $this->find($id) ?? throw EntityNotFound::withId($id); }
    public function find(WonderId $id): ?Entity { return $this->entities[(string)$id] ?? null; }
    public function search(string $query, int $limit = 10): array { return []; }
    public function findBySlug(string $slug): ?Entity { return null; }
    public function addAlias(EntityAlias $alias): void {}
    public function aliases(WonderId $entityId): array { return []; }
}

final readonly class GraphRelationshipRepository implements RelationshipRepository
{
    /** @param list<Relationship> $relationships */
    public function __construct(private array $relationships) {}
    public function save(Relationship $relationship): void {}
    public function forEntity(WonderId $entityId): array
    {
        return array_values(array_filter(
            $this->relationships,
            static fn(Relationship $relationship): bool => $relationship->isOutgoingFrom($entityId) || $relationship->isIncomingTo($entityId),
        ));
    }
}

final class GraphTypeRepository implements RelationshipTypeRepository
{
    public function get(string $type): RelationshipType
    {
        return match ($type) {
            'roosts_in' => new RelationshipType('roosts_in', 'hosts_roost_of', 'roosts in', 'hosts roost of', 'Habitat relationship.'),
            'part_of' => new RelationshipType('part_of', 'has_part', 'part of', 'has part', 'Composition relationship.'),
            default => new RelationshipType('associated_with', 'associated_with', 'associated with', 'associated with', 'General association.', true),
        };
    }
    public function active(): array { return []; }
}
