<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;

/** Adds immutable audit attribution around successful Editorial Lens Studio mutations. */
final readonly class AuditedEditorialLensStudioApi
{
    public function __construct(
        private EditorialLensStudioApi $studio,
        private AuthService $auth,
        private AuditRepository $audit,
    ) {}

    /** @param array<string,string> $headers */
    public function handle(string $method, string $path, string $rawBody = '', array $headers = []): ?array
    {
        $mutation = ($method === 'POST' && $path === '/v1/editorial-lenses')
            || ($method === 'PUT' && preg_match('#^/v1/editorial-lenses/[a-z0-9-]+$#', $path))
            || ($method === 'POST' && preg_match('#^/v1/editorial-lenses/[a-z0-9-]+/(activate|deprecate)$#', $path));
        if (!$mutation) return $this->studio->handle($method, $path, $rawBody, $headers);

        try {
            $user = $this->auth->authenticate($headers['authorization'] ?? null);
            $user->role->assertPermits('editorial.manage');
            $response = $this->studio->handle($method, $path, $rawBody, $headers);
            if ($response === null || ($response['status'] ?? 500) < 200 || ($response['status'] ?? 500) >= 300) return $response;

            $data = $response['body']['data'] ?? [];
            $slug = (string)($data['slug'] ?? 'unknown');
            $action = match (true) {
                $method === 'POST' && $path === '/v1/editorial-lenses' => 'editorial_lens.created',
                $method === 'PUT' => 'editorial_lens.revised',
                str_ends_with($path, '/activate') => 'editorial_lens.activated',
                str_ends_with($path, '/deprecate') => 'editorial_lens.deprecated',
                default => 'editorial_lens.changed',
            };
            $this->audit->record($user, $action, 'editorial_lens', $slug, sprintf('%s editorial lens %s.', ucfirst(str_replace(['editorial_lens.','_'],['',' '],$action)), $slug), [
                'name'=>$data['name'] ?? null,'status'=>$data['status'] ?? null,'revision'=>$data['revision'] ?? null,
                'types'=>$data['types'] ?? [],'statuses'=>$data['statuses'] ?? [],'minimum_confidence'=>$data['minimum_confidence'] ?? null,
            ]);
            return $response;
        } catch (DomainException $exception) {
            $message = strtolower($exception->getMessage());
            $authentication = str_contains($message,'session') || str_contains($message,'bearer') || str_contains($message,'suspended');
            return ['status'=>$authentication?401:403,'body'=>['success'=>false,'error'=>['code'=>$authentication?'AUTHENTICATION_FAILED':'AUTHORISATION_FAILED','message'=>$exception->getMessage()]]];
        } catch (Throwable) {
            return ['status'=>500,'body'=>['success'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'WonderOS could not complete the audited editorial mutation.']]];
        }
    }
}
