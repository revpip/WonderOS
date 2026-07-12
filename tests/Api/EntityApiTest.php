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
    public function test_it_creates_and_returns_an_entity(): void
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
        self::assertTrue($created['body']['success']);
        self::assertSame('WND-ENT-000001', $created['body']['data']['wonder_id']);

        $fetched = $api->handle('GET', '/v1/entities/WND-ENT-000001');

        self::assertSame(200, $fetched['status']);
        self::assertSame('Barn Owl', $fetched['body']['data']['canonical_name']);
        self::assertSame(1, $fetched['body']['data']['revision']);
    }

    public function test_it_returns_validation_and_not_found_envelopes(): void
    {
        $api = new EntityApi(new InMemoryEntityRepository());

        $invalid = $api->handle('POST', '/v1/entities', '{}');
        self::assertSame(422, $invalid['status']);
        self::assertSame('VALIDATION_FAILED', $invalid['body']['error']['code']);

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
}
