<?php

declare(strict_types=1);

namespace WonderOS\Tests\Integration\Knowledge;

use PDO;
use PHPUnit\Framework\TestCase;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityAlias;
use WonderOS\Knowledge\Entity\EntityAliasType;
use WonderOS\Knowledge\Entity\EntityRevisionConflict;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEntityRepository;

final class PdoEntityRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoEntityRepository $repository;

    protected function setUp(): void
    {
        $dsn=getenv('WONDEROS_TEST_DATABASE_DSN')?:'pgsql:host=127.0.0.1;port=5432;dbname=wonderos';
        try {$this->connection=new PDO($dsn,getenv('WONDEROS_TEST_DATABASE_USER')?:'wonderos',getenv('WONDEROS_TEST_DATABASE_PASSWORD')?:'wonderos',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
        catch(\Throwable $exception){self::markTestSkipped('PostgreSQL is unavailable: '.$exception->getMessage());}
        $root=dirname(__DIR__,3).'/database/migrations/';
        $this->connection->exec(file_get_contents($root.'0002_create_entity_aliases.down.sql'));
        $this->connection->exec(file_get_contents($root.'0001_create_entities.down.sql'));
        $this->connection->exec(file_get_contents($root.'0001_create_entities.up.sql'));
        $this->connection->exec(file_get_contents($root.'0002_create_entity_aliases.up.sql'));
        $this->repository=new PdoEntityRepository($this->connection);
    }

    public function test_it_persists_and_searches_aliases(): void
    {
        $id=$this->repository->nextIdentity();
        $this->repository->save(Entity::create($id,'Barn Owl','Living Things','Bird',0.9));
        $this->repository->addAlias(new EntityAlias($id,'Tyto alba',EntityAliasType::Scientific,'la'));
        self::assertSame('Barn Owl',$this->repository->search('Tyto alba')[0]->canonicalName());
        self::assertSame('Tyto alba',$this->repository->aliases($id)[0]->alias);
    }

    public function test_it_rejects_a_stale_database_revision(): void
    {
        $id=$this->repository->nextIdentity();
        $entity=Entity::create($id,'Barn Owl','Living Things','Bird');
        $this->repository->save($entity);
        $entity->rename('Western Barn Owl',1);
        $this->repository->save($entity,1);
        $stale=Entity::reconstitute($entity->uuid(),$id,'Outdated Owl','outdated-owl','Living Things','Bird',$entity->status(),0.5,2);
        $this->expectException(EntityRevisionConflict::class);
        $this->repository->save($stale,1);
    }
}
