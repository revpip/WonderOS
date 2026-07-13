<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Graph;

use DomainException;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityRepository;
use WonderOS\Knowledge\Relationship\Relationship;
use WonderOS\Knowledge\Relationship\RelationshipRepository;
use WonderOS\Knowledge\Relationship\RelationshipTypeRepository;

/** Builds a bounded, cycle-safe view of connected canonical knowledge. */
final readonly class GraphTraversal
{
    public function __construct(
        private EntityRepository $entities,
        private RelationshipRepository $relationships,
        private RelationshipTypeRepository $relationshipTypes,
    ) {
    }

    /** @return array{root_wonder_id:string,depth:int,nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>,truncated:bool} */
    public function traverse(WonderId $rootId, int $depth = 1, int $maxNodes = 100): array
    {
        if ($depth < 1 || $depth > 3) {
            throw new DomainException('Graph depth must be between 1 and 3.');
        }
        if ($maxNodes < 1 || $maxNodes > 250) {
            throw new DomainException('Graph max_nodes must be between 1 and 250.');
        }

        $root = $this->entities->get($rootId);
        $visited = [(string) $rootId => true];
        $queue = [[$rootId, 0]];
        $nodes = [(string) $rootId => $this->node($root, 0)];
        $edges = [];
        $seenEdges = [];
        $truncated = false;

        while ($queue !== []) {
            [$currentId, $currentDepth] = array_shift($queue);
            if ($currentDepth >= $depth) {
                continue;
            }

            foreach ($this->relationships->forEntity($currentId) as $relationship) {
                $edgeKey = $relationship->uuid();
                if (!isset($seenEdges[$edgeKey])) {
                    $edges[] = $this->edge($relationship, $currentId, $currentDepth + 1);
                    $seenEdges[$edgeKey] = true;
                }

                $relatedId = $relationship->relatedEntityIdFor($currentId);
                $relatedKey = (string) $relatedId;
                if (isset($visited[$relatedKey])) {
                    continue;
                }

                if (count($nodes) >= $maxNodes) {
                    $truncated = true;
                    continue 2;
                }

                $related = $this->entities->get($relatedId);
                $visited[$relatedKey] = true;
                $nodes[$relatedKey] = $this->node($related, $currentDepth + 1);
                $queue[] = [$relatedId, $currentDepth + 1];
            }
        }

        return [
            'root_wonder_id' => (string) $rootId,
            'depth' => $depth,
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'truncated' => $truncated,
        ];
    }

    /** @return array<string,mixed> */
    private function node(Entity $entity, int $distance): array
    {
        return [
            'wonder_id' => (string) $entity->id(),
            'canonical_name' => $entity->canonicalName(),
            'slug' => $entity->slug(),
            'family' => $entity->family(),
            'type' => $entity->type(),
            'status' => $entity->status()->value,
            'confidence' => $entity->confidence(),
            'distance' => $distance,
        ];
    }

    /** @return array<string,mixed> */
    private function edge(Relationship $relationship, WonderId $perspectiveId, int $distance): array
    {
        $type = $this->relationshipTypes->get($relationship->type());
        $outgoing = $relationship->isOutgoingFrom($perspectiveId);

        return [
            'uuid' => $relationship->uuid(),
            'from_wonder_id' => (string) $perspectiveId,
            'to_wonder_id' => (string) $relationship->relatedEntityIdFor($perspectiveId),
            'direction' => $outgoing ? 'outgoing' : 'incoming',
            'type' => $type->presentationType($outgoing),
            'label' => $type->presentationLabel($outgoing),
            'canonical_type' => $relationship->type(),
            'source_wonder_id' => (string) $relationship->sourceId(),
            'target_wonder_id' => (string) $relationship->targetId(),
            'context' => $relationship->context(),
            'confidence' => $relationship->confidence(),
            'status' => $relationship->status(),
            'distance' => $distance,
        ];
    }
}
