<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;

final readonly class AuditApi
{
    public function __construct(private AuthService $auth, private AuditRepository $audit) {}

    /** @param array<string,string> $headers @param array<string,mixed> $query */
    public function handle(string $method, string $path, array $headers, array $query = []): ?array
    {
        if ($path !== '/v1/audit-events') return null;
        try {
            if ($method !== 'GET') return $this->error(405, 'METHOD_NOT_ALLOWED', 'The audit method is not supported.');
            $user = $this->auth->authenticate($headers['authorization'] ?? null);
            $user->role->assertPermits('users.manage');
            $events = $this->audit->search([
                'actor_email' => $query['actor_email'] ?? null,
                'action' => $query['action'] ?? null,
                'subject_type' => $query['subject_type'] ?? null,
                'subject_id' => $query['subject_id'] ?? null,
                'limit' => $query['limit'] ?? 100,
            ]);
            return ['status'=>200,'body'=>['success'=>true,'data'=>$events,'meta'=>['count'=>count($events)],'links'=>(object)[]]];
        } catch (DomainException $exception) {
            return $this->error(403, 'AUTHORISATION_FAILED', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not retrieve the audit ledger.');
        }
    }

    private function error(int $status,string $code,string $message):array
    {
        return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];
    }
}