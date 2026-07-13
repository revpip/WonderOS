<?php

declare(strict_types=1);

namespace WonderOS\Tests\Api;

use PHPUnit\Framework\TestCase;
use WonderOS\Api\EntityApi;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityAlias;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;

final class EntityApiTest extends TestCase
{
    public function test_it_creates_searches_and_resolves_an_alias(): void
    {
        $repository = new InMemoryEntityRepository();
        $api = new EntityApi($repository);
        $api->handle('POST','/v1/entities',json_encode(['canonical_name'=>'Barn Owl','family'=>'Living Things','type'=>'Bird'],JSON_THROW_ON_ERROR));
        $added = $api->handle('POST','/v1/entities/WND-ENT-000001/aliases',json_encode(['alias'=>'Tyto alba','type'=>'scientific','language'=>'la'],JSON_THROW_ON_ERROR));
        self::assertSame(201,$added['status']);
        $searched = $api->handle('GET','/v1/entities','',['query'=>'Tyto alba']);
        self::assertSame('Barn Owl',$searched['body']['data'][0]['canonical_name']);
        $fetched = $api->handle('GET','/v1/entities/WND-ENT-000001');
        self::assertSame('Tyto alba',$fetched['body']['data']['aliases'][0]['alias']);
    }
}

final class InMemoryEntityRepository implements EntityRepository
{
    private array $entities=[];
    private array $aliases=[];
    private int $sequence=0;
    public function nextIdentity(): WonderId { return WonderId::entity(++$this->sequence); }
    public function save(Entity $entity, ?int $expectedRevision=null): void { $this->entities[(string)$entity->id()]=$entity; }
    public function get(WonderId $id): Entity { return $this->find($id) ?? throw EntityNotFound::withId($id); }
    public function find(WonderId $id): ?Entity { return $this->entities[(string)$id] ?? null; }
    public function search(string $query,int $limit=10): array
    {
        $query=strtolower(trim($query));
        return array_slice(array_values(array_filter($this->entities,function(Entity $entity) use($query): bool {
            if (str_contains(strtolower($entity->canonicalName()),$query) || str_contains($entity->slug(),$query)) return true;
            foreach ($this->aliases[(string)$entity->id()] ?? [] as $alias) if (str_contains(strtolower($alias->alias),$query)) return true;
            return false;
        })),0,$limit);
    }
    public function findBySlug(string $slug): ?Entity { foreach($this->entities as $entity) if($entity->slug()===$slug)return $entity; return null; }
    public function addAlias(EntityAlias $alias): void { $this->get($alias->entityId); $this->aliases[(string)$alias->entityId][]=$alias; }
    public function aliases(WonderId $entityId): array { $this->get($entityId); return $this->aliases[(string)$entityId] ?? []; }
}
