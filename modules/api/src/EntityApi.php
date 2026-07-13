<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DomainException;
use InvalidArgumentException;
use JsonException;
use Throwable;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityAlias;
use WonderOS\Knowledge\Entity\EntityAliasType;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;

final readonly class EntityApi
{
    public function __construct(private EntityRepository $entities) {}

    public function handle(string $method, string $path, string $rawBody = '', array $query = []): array
    {
        try {
            if ($method === 'POST' && $path === '/v1/entities') return $this->create($this->decode($rawBody));
            if ($method === 'GET' && $path === '/v1/entities') return $this->search($query);
            if (preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})/aliases$#i', $path, $matches)) {
                return $method === 'POST' ? $this->addAlias($matches[1], $this->decode($rawBody)) : ($method === 'GET' ? $this->listAliases($matches[1]) : $this->error(405,'METHOD_NOT_ALLOWED','The method is not supported.'));
            }
            if ($method === 'GET' && preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})$#i', $path, $matches)) return $this->show($matches[1]);
            return $this->error(404, 'ROUTE_NOT_FOUND', 'The requested API route does not exist.');
        } catch (JsonException|InvalidArgumentException|DomainException $exception) {
            return $this->error(422, 'VALIDATION_FAILED', $exception->getMessage());
        } catch (EntityNotFound $exception) {
            return $this->error(404, 'ENTITY_NOT_FOUND', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not complete the request.');
        }
    }

    private function create(array $payload): array
    {
        foreach (['canonical_name','family','type'] as $required) if (!isset($payload[$required]) || !is_string($payload[$required]) || trim($payload[$required]) === '') throw new InvalidArgumentException(sprintf('%s is required.', $required));
        $existing = $this->entities->findBySlug($this->slugify($payload['canonical_name']));
        if ($existing !== null) return ['status'=>409,'body'=>['success'=>false,'error'=>['code'=>'ENTITY_ALREADY_EXISTS','message'=>sprintf('%s already exists as %s.',$existing->canonicalName(),(string)$existing->id()),'existing_entity'=>$this->serialize($existing)]]];
        $entity = Entity::create($this->entities->nextIdentity(),$payload['canonical_name'],$payload['family'],$payload['type'],isset($payload['confidence'])?(float)$payload['confidence']:0.5);
        $this->entities->save($entity);
        return ['status'=>201,'body'=>$this->success($this->serialize($entity))];
    }

    private function search(array $query): array
    {
        $term = isset($query['query']) && is_string($query['query']) ? trim($query['query']) : '';
        if ($term === '') throw new InvalidArgumentException('query is required.');
        $results = array_map(fn(Entity $entity): array => $this->serialize($entity), $this->entities->search($term,20));
        return ['status'=>200,'body'=>['success'=>true,'data'=>$results,'meta'=>['count'=>count($results),'query'=>$term],'links'=>(object)[]]];
    }

    private function show(string $wonderId): array
    {
        $id = WonderId::parse($wonderId);
        $data = $this->serialize($this->entities->get($id));
        $data['aliases'] = array_map(fn(EntityAlias $alias): array => $this->serializeAlias($alias), $this->entities->aliases($id));
        return ['status'=>200,'body'=>$this->success($data)];
    }

    private function addAlias(string $wonderId, array $payload): array
    {
        if (!isset($payload['alias']) || !is_string($payload['alias']) || trim($payload['alias']) === '') throw new InvalidArgumentException('alias is required.');
        $type = isset($payload['type']) && is_string($payload['type']) ? EntityAliasType::from($payload['type']) : EntityAliasType::Common;
        $language = isset($payload['language']) && is_string($payload['language']) && trim($payload['language']) !== '' ? trim($payload['language']) : null;
        $alias = new EntityAlias(WonderId::parse($wonderId), trim($payload['alias']), $type, $language);
        $this->entities->addAlias($alias);
        return ['status'=>201,'body'=>$this->success($this->serializeAlias($alias))];
    }

    private function listAliases(string $wonderId): array
    {
        $aliases = array_map(fn(EntityAlias $alias): array => $this->serializeAlias($alias), $this->entities->aliases(WonderId::parse($wonderId)));
        return ['status'=>200,'body'=>['success'=>true,'data'=>$aliases,'meta'=>['count'=>count($aliases)],'links'=>(object)[]]];
    }

    private function decode(string $rawBody): array
    {
        $decoded = json_decode($rawBody,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new InvalidArgumentException('Request body must be a JSON object.');
        return $decoded;
    }

    private function serialize(Entity $entity): array
    {
        return ['wonder_id'=>(string)$entity->id(),'canonical_name'=>$entity->canonicalName(),'slug'=>$entity->slug(),'family'=>$entity->family(),'type'=>$entity->type(),'status'=>$entity->status()->value,'confidence'=>$entity->confidence(),'revision'=>$entity->revision()];
    }

    private function serializeAlias(EntityAlias $alias): array
    {
        return ['alias'=>$alias->alias,'type'=>$alias->type->value,'language'=>$alias->language];
    }

    private function success(array $data): array { return ['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]; }
    private function error(int $status,string $code,string $message): array { return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
    private function slugify(string $value): string { $value=strtolower(trim($value)); $value=preg_replace('/[^a-z0-9]+/','-',$value)??''; return trim($value,'-'); }
}
