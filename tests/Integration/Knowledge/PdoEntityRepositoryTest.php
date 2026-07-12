<?php

declare(strict_types=1);

namespace WonderOS\Tests\Integration\Knowledge;

use PDO;
use PHPUnit\Framework\TestCase;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityRevisionConflict;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEntityRepository;

final class PdoEntityRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoEntityRepository $repository;

    protected function setUp(): void
    {
        $dsn = getenv('WONDEROS_TEST_DATABASE_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=wonderos';
        $user = getenv('WONDEROS_TEST_DATABASE_USER') ?: 'wonderos';
        $password = getenv('WONDEROS_TEST_DATABASE_PASSWORD') ?: 'wonderos';

        try {
            $this->connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $exception) {
            self::markTestSkipped('PostgreSQL is unavailable: '.$exception->getMessage());
        }

        $this->connection->exec(file_get_contents(
            dirname(__DIR__, 3).'/database/migrations/0001_create_entities.down.sql',
        ));
        $this->connection->exec(file_get_contents(
            dirname(__DIR__, 3).'/database/migrations/0001_create_entities.up.sql',
        ));

        $this->repository = new PdoEntityRepository($this->connection);
    }

    public function test_it_allocates_persists_and_reconstitutes_an_entity(): void
    {
        $id = $this->repository->nextIdentity();
        $entity = Entity::create($id, 'Barn Owl', 'Living Things', 'Bird', 0.9);

        $this->repository->save($entity);
        $stored = $this->repository->get($id);

        self::assertSame('WND-ENT-000001', (string) $stored->id());
        self::assertSame('Barn Owl', $stored->canonicalName());
        self::assertSame($entity->uuid(), $stored->uuid());
        self::assertSame(1, $stored->revision());
    }

    public function test_it_searches_and_prioritises_an_exact_canonical_name(): void
    {
        $this->repository->save(Entity::create(
            $this->repository->nextIdentity(),
            'Western Barn Owl',
            'Living Things',
            'Bird',
        ));
        $this->repository->save(Entity::create(
            $this->repository->nextIdentity(),
            'Barn Owl',
            'Living Things',
            'Bird',
        ));

        $results = $this->repository->search('Barn Owl');

        self::assertCount(2, $results);
        self::assertSame('Barn Owl', $results[0]->canonicalName());
        self::assertSame('WND-ENT-000002', (string) $this->repository->findBySlug('barn-owl')?->id());
    }

    public function test_it_rejects_a_stale_database_revision(): void
    {
        $id = $this->repository->nextIdentity();
        $entity = Entity::create($id, 'Barn Owl', 'Living Things', 'Bird');
        $this->repository->save($entity);

        $entity->rename('Western Barn Owl', 1);
        $this->repository->save($entity, 1);

        $stale = Entity::reconstitute(
            $entity->uuid(),
            $id,
            'Outdated Owl',
            'outdated-owl',
            'Living Things',
            'Bird',
            $entity->status(),
            0.5,
            2,
        );

        $this->expectException(EntityRevisionConflict::class);
        $this->repository->save($stale, 1);
    }
}
