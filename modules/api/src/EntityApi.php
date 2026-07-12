<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DomainException;
use InvalidArgumentException;
use JsonException;
use Throwable;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\Entity;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Entity\EntityRepository;

/** Provides the first HTTP-facing entity use cases without owning domain rules. */
final readonly class EntityApi
{
    public function __construct(private EntityRepository $entities)
    {
    }

    /** @return array{status:int,body:array<string,mixed>} */
    public function handle(string $method, string $path, string $rawBody = ''): array
    {
        try {
            if ($method === 'POST' && $path === '/v1/entities') {
                return $this->create($this->decode($rawBody));
            }

            if ($method === 'GET' && preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})$#i', $path, $matches)) {
                return $this->show($matches[1]);
            }

            return $this->error(404, 'ROUTE_NOT_FOUND', 'The requested API route does not exist.');
        } catch (JsonException|InvalidArgumentException|DomainException $exception) {
            return $this->error(422, 'VALIDATION_FAILED', $exception->getMessage());
        } catch (EntityNotFound $exception) {
            return $this->error(404, 'ENTITY_NOT_FOUND', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not complete the request.');
        }
    }

    /** @param array<string,mixed> $payload */
    private function create(array $payload): array
    {
        foreach (['canonical_name', 'family', 'type'] as $required) {
            if (!isset($payload[$required]) || !is_string($payload[$required]) || trim($payload[$required]) === '') {
                throw new InvalidArgumentException(sprintf('%s is required.', $required));
            }
        }

        $id = $this->entities->nextIdentity();
        $entity = Entity::create(
            $id,
            $payload['canonical_name'],
            $payload['family'],
            $payload['type'],
            isset($payload['confidence']) ? (float) $payload['confidence'] : 0.5,
        );
        $this->entities->save($entity);

        return ['status' => 201, 'body' => $this->success($this->serialize($entity))];
    }

    private function show(string $wonderId): array
    {
        $entity = $this->entities->get(WonderId::parse($wonderId));
        return ['status' => 200, 'body' => $this->success($this->serialize($entity))];
    }

    /** @return array<string,mixed> */
    private function decode(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    private function serialize(Entity $entity): array
    {
        return [
            'wonder_id' => (string) $entity->id(),
            'canonical_name' => $entity->canonicalName(),
            'slug' => $entity->slug(),
            'family' => $entity->family(),
            'type' => $entity->type(),
            'status' => $entity->status()->value,
            'confidence' => $entity->confidence(),
            'revision' => $entity->revision(),
        ];
    }

    /** @param array<string,mixed> $data */
    private function success(array $data): array
    {
        return ['success' => true, 'data' => $data, 'meta' => (object) [], 'links' => (object) []];
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => ['code' => $code, 'message' => $message]]];
    }
}
