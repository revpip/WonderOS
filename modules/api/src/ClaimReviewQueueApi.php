<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Knowledge\Claim\ClaimRepository;

final readonly class ClaimReviewQueueApi
{
    public function __construct(private ClaimRepository $claims, private AuthService $auth) {}

    /** @param array<string,string> $headers @param array<string,mixed> $query */
    public function handle(string $method,string $path,array $headers,array $query=[]): ?array
    {
        if($path!=='/v1/claim-review-queue') return null;
        try {
            if($method!=='GET') return $this->error(405,'METHOD_NOT_ALLOWED','The claim review queue method is not supported.');
            $user=$this->auth->authenticate($headers['authorization']??null);
            $user->role->assertPermits('editorial.manage');
            $statuses=array_values(array_filter(array_map('trim',explode(',',(string)($query['statuses']??'draft,review,disputed')))));
            $limit=filter_var($query['limit']??100,FILTER_VALIDATE_INT);
            if($limit===false||$limit<1||$limit>250) throw new \InvalidArgumentException('limit must be between 1 and 250.');
            $rows=$this->claims->reviewQueue($statuses,(int)$limit);
            return ['status'=>200,'body'=>['success'=>true,'data'=>$rows,'meta'=>['count'=>count($rows),'statuses'=>$statuses],'links'=>(object)[]]];
        } catch(\InvalidArgumentException $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
          catch(DomainException $e){return $this->error(403,'AUTHORISATION_FAILED',$e->getMessage());}
          catch(Throwable){return $this->error(500,'INTERNAL_ERROR','WonderOS could not load the claim review queue.');}
    }

    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}
