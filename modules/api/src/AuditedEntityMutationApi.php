<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;

/** Authenticates and audits canonical entity, alias and relationship mutations. */
final readonly class AuditedEntityMutationApi
{
    public function __construct(
        private EntityApi $entities,
        private AuthService $auth,
        private AuditRepository $audit,
    ) {}

    /** @param array<string,string> $headers @param array<string,mixed> $query */
    public function handle(string $method, string $path, string $rawBody, array $headers, array $query = []): ?array
    {
        if ($method !== 'POST' || !($path === '/v1/entities'
            || preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})/(aliases|relationships)$#i', $path))) {
            return null;
        }

        try {
            $user = $this->auth->authenticate($headers['authorization'] ?? null);
            $user->role->assertPermits('knowledge.contribute');
            $response = $this->entities->handle($method, $path, $rawBody, $query);
            if (($response['status'] ?? 500) < 200 || ($response['status'] ?? 500) >= 300) return $response;

            $data = $response['body']['data'] ?? [];
            if ($path === '/v1/entities') {
                $id = (string)($data['wonder_id'] ?? 'unknown');
                $this->audit->record($user, 'entity.created', 'entity', $id, sprintf('Created canonical entity %s.', (string)($data['canonical_name'] ?? $id)), [
                    'family'=>$data['family'] ?? null,'type'=>$data['type'] ?? null,'confidence'=>$data['confidence'] ?? null,
                ]);
            } elseif (str_ends_with($path, '/aliases')) {
                preg_match('#/v1/entities/(WND-[A-Z]{3}-\d{6})/#i', $path, $m);
                $id = strtoupper($m[1] ?? 'unknown');
                $this->audit->record($user, 'entity.alias_added', 'entity', $id, sprintf('Added alias %s to %s.', (string)($data['alias'] ?? ''), $id), [
                    'alias'=>$data['alias'] ?? null,'alias_type'=>$data['type'] ?? null,'language'=>$data['language'] ?? null,
                ]);
            } else {
                $uuid = (string)($data['uuid'] ?? 'unknown');
                $this->audit->record($user, 'relationship.created', 'relationship', $uuid, sprintf('Created relationship %s → %s.', (string)($data['source_wonder_id'] ?? ''), (string)($data['target_wonder_id'] ?? '')), [
                    'source_wonder_id'=>$data['source_wonder_id'] ?? null,'target_wonder_id'=>$data['target_wonder_id'] ?? null,'type'=>$data['canonical_type'] ?? null,'confidence'=>$data['confidence'] ?? null,
                ]);
            }
            return $response;
        } catch (DomainException $exception) {
            $message = strtolower($exception->getMessage());
            $authentication = str_contains($message,'session') || str_contains($message,'bearer') || str_contains($message,'suspended');
            return ['status'=>$authentication?401:403,'body'=>['success'=>false,'error'=>['code'=>$authentication?'AUTHENTICATION_FAILED':'AUTHORISATION_FAILED','message'=>$exception->getMessage()]]];
        } catch (Throwable) {
            return ['status'=>500,'body'=>['success'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'WonderOS could not complete the audited knowledge mutation.']]];
        }
    }
}
