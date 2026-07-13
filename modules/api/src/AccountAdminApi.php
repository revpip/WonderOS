<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use JsonException;
use Throwable;
use WonderOS\Core\Auth\AuthRepository;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Core\Auth\Role;
use WonderOS\Core\Auth\User;

final readonly class AccountAdminApi
{
    public function __construct(private AuthService $auth, private AuthRepository $repository) {}

    /** @param array<string,string> $headers @return array{status:int,body:array<string,mixed>}|null */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if($path!=='/v1/account-activity' && !preg_match('#^/v1/users/([0-9a-f-]{36})(?:/(sessions/revoke))?$#i',$path,$m)) return null;
        try {
            $actor=$this->auth->authenticate($headers['authorization']??null); $actor->role->assertPermits('users.manage');
            if($method==='GET' && $path==='/v1/account-activity') return $this->success(200,$this->repository->activity());
            $uuid=$m[1]??''; $action=$m[2]??null; $target=$this->find($uuid);
            if($method==='PUT' && $action===null){
                $p=$this->decode($rawBody);
                $updated=new User($target->uuid,(string)($p['email']??$target->email),(string)($p['display_name']??$target->displayName),Role::from((string)($p['role']??$target->role->value)),(string)($p['status']??$target->status));
                if($updated->uuid===$actor->uuid && ($updated->status!=='active' || $updated->role!==Role::Administrator)) throw new DomainException('Administrators cannot remove their own active administrator access.');
                $this->repository->updateUser($updated); $this->repository->recordActivity($actor->uuid,'user.updated',$updated->uuid,['role'=>$updated->role->value,'status'=>$updated->status]);
                if($updated->status==='suspended') $this->repository->revokeAllSessions($updated->uuid);
                return $this->success(200,$this->user($updated));
            }
            if($method==='POST' && $action==='sessions/revoke'){
                if($target->uuid===$actor->uuid) throw new DomainException('Use logout to revoke your own current session.');
                $count=$this->repository->revokeAllSessions($target->uuid); $this->repository->recordActivity($actor->uuid,'sessions.revoked',$target->uuid,['count'=>$count]);
                return $this->success(200,['revoked_sessions'=>$count,'user'=>$this->user($target)]);
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The account administration method is not supported.');
        } catch(JsonException $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
        catch(DomainException $e){$status=str_contains(strtolower($e->getMessage()),'session')||str_contains(strtolower($e->getMessage()),'bearer')?401:403;return $this->error($status,$status===401?'AUTHENTICATION_FAILED':'AUTHORISATION_FAILED',$e->getMessage());}
        catch(Throwable){return $this->error(500,'INTERNAL_ERROR','WonderOS could not complete the administration request.');}
    }

    private function find(string $uuid): User { foreach($this->repository->users() as $u) if($u->uuid===$uuid) return $u; throw new DomainException('WonderOS user does not exist.'); }
    private function decode(string $raw): array { $p=json_decode($raw,true,512,JSON_THROW_ON_ERROR); return is_array($p)?$p:[]; }
    private function user(User $u): array { return ['uuid'=>$u->uuid,'email'=>$u->email,'display_name'=>$u->displayName,'role'=>$u->role->value,'status'=>$u->status]; }
    private function success(int $status,array $data): array { return ['status'=>$status,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]]; }
    private function error(int $status,string $code,string $message): array { return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
}