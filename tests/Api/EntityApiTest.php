<?php

declare(strict_types=1);

namespace WonderOS\Tests\Api;

use PHPUnit\Framework\TestCase;
use WonderOS\Api\EntityApi;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;

final class EntityApiTest extends TestCase
{
    public function test_it_creates_returns_and_searches_an_entity(): void
    {
        $repository = new InMemoryEntityRepository();
        $api = new EntityApi($repository);

        $created = $api->handle('POST', '/v1/entities', json_encode([
            'canonical_name' => 'Barn Owl',
            'family' => 'Living Things',
            'type' => 'Bird',
            'confidence' => 0.9,
        ], JSON_THROW_ON_ERROR));

        self::assertSame(201, $created['status']);
        self::assertSame('WND-ENT-000001', $created['body']['data']['wonder_id']);

        $fetched = $api->handle('GET', '/v1/entities/WND-ENT-000001');
        self::assertSame('Barn Owl', $fetched['body']['data']['canonical_name']);

        $searched = $api->handle('GET', '/v1/entities', '', ['query' => 'owl']);
        self::assertSame(200, $searched['status']);
        self::assertSame(1, $searched['body']['meta']['count']);
        self::assertSame('Barn Owl', $searched['body']['data'][0]['canonical_name']);
    }

    public function test_it_opens_existing_entity_instead_of_creating_a_duplicate(): void
    {
        $repository = new InMemoryEntityRepository();
        $api = new EntityApi($repository);
        $payload = json_encode([
            'canonical_name' => 'Barn Owl',
            'family' => 'Living Things',
            'type' => 'Bird',
        ], JSON_THROW_ON_ERROR);

        $api->handle('POST', '/v1/entities', $payload);
        $duplicate = $api->handle('POST', '/v1/entities', $payload);

        self::assertSame(409, $duplicate['status']);
        self::assertSame('ENTITY_ALREADY_EXISTS', $duplicate['body']['error']['code']);
        self::assertSame('WND-ENT-000001', $duplicate['body']['error']['existing_entity']['wonder_id']);
        self::assertSame(1, $repository->count());
    }

    public function test_it_returns_validation_and_not_found_envelopes(): void
    {
        $api = new EntityApi(new InMemoryEntityRepository());

        $invalid = $api->handle('POST', '/v1/entities', '{}');
        self::assertSame(422, $invalid['status']);
        self::assertSame('VALIDATION_FAILED', $invalid['body']['error']['code']);

        $missingQuery = $api->handle('GET', '/v1/entities');
        self::assertSame(422, $missingQuery['status']);

        $missing = $api->handle('GET', '/v1/entities/WND-ENT-000099');
        self::assertSame(404, $missing['status']);
        self::assertSame('ENTITY_NOT_FOUND', $missing['body']['error']['code']);
    }
}

final class InMemoryEntityRepository implements EntityRepository
{
    /** @var array<string, Entity> */
    private array $entities = [];
    private int $sequence = 0;

    public function nextIdentity(): WonderId
    {
        return WonderId::entity(++$this->sequence);
    }

    public function save(Entity $entity, ?int $expectedRevision = null): void
    {
        $this->entities[(string) $entity->id()] = $entity;
    }

    public function get(WonderId $id): Entity
    {
        return $this->find($id) ?? throw EntityNotFound::withId($id);
    }

    public function find(WonderId $id): ?Entity
    {
        return $this->entities[(string) $id] ?? null;
    }

    /** @return list<Entity> */
    public function search(string $query, int $limit = 10): array
    {
        $query = strtolower(trim($query));
        return array_slice(array_values(array_filter(
            $this->entities,
            static fn (Entity $entity): bool => str_contains(strtolower($entity->canonicalName()), $query)
                || str_contains($entity->slug(), $query)
                || str_contains(strtolower((string) $entity->id()), $query),
        )), 0, $limit);
    }

    public function findBySlug(string $slug): ?Entity
    {
        foreach ($this->entities as $entity) {
            if ($entity->slug() === $slug) {
                return $entity;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->entities);
    }
}
