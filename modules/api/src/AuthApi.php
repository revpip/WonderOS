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

final readonly class AuthApi
{
    public function __construct(private AuthService $auth,private AuthRepository $repository) {}

    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if(!str_starts_with($path,'/v1/auth')&&!str_starts_with($path,'/v1/users')) return null;
        try {
            if($method==='POST'&&$path==='/v1/auth/login'){
                $payload=$this->decode($rawBody); $result=$this->auth->login((string)($payload['email']??''),(string)($payload['password']??''));
                return $this->success(200,['token'=>$result['token'],'expires_at'=>$result['expires_at'],'user'=>$this->user($result['user'])]);
            }
            $current=$this->auth->authenticate($headers['authorization']??null);
            if($method==='POST'&&$path==='/v1/auth/logout'){ $this->auth->logout($headers['authorization']??null); return $this->success(200,['logged_out'=>true]); }
            if($method==='GET'&&$path==='/v1/auth/me') return $this->success(200,$this->user($current));
            if($method==='GET'&&$path==='/v1/users'){ $current->role->assertPermits('users.manage'); return $this->success(200,array_map(fn(User $user):array=>$this->user($user),$this->repository->users())); }
            if($method==='POST'&&$path==='/v1/users'){
                $current->role->assertPermits('users.manage'); $payload=$this->decode($rawBody); $password=(string)($payload['password']??'');
                if(strlen($password)<12) throw new DomainException('Passwords must contain at least 12 characters.');
                $user=new User($this->uuid(),strtolower(trim((string)($payload['email']??''))),trim((string)($payload['display_name']??'')),Role::from((string)($payload['role']??'viewer')));
                $this->repository->createUser($user,password_hash($password,PASSWORD_DEFAULT)); return $this->success(201,$this->user($user));
            }
            if(preg_match('#^/v1/users/([0-9a-f-]{36})(?:/(sessions|activity))?$#i',$path,$matches)){
                $current->role->assertPermits('users.manage'); $uuid=$matches[1]; $action=$matches[2]??null;
                if($method==='PUT'&&$action===null){
                    $payload=$this->decode($rawBody); $role=Role::from((string)($payload['role']??'')); $status=(string)($payload['status']??'');
                    if($uuid===$current->uuid&&$status==='suspended') throw new DomainException('Administrators cannot suspend their own active account.');
                    return $this->success(200,$this->user($this->repository->updateUser($uuid,$role,$status)));
                }
                if($method==='POST'&&$action==='sessions') return $this->success(200,['revoked_sessions'=>$this->repository->revokeUserSessions($uuid)]);
                if($method==='GET'&&$action==='activity') return $this->success(200,$this->repository->accountActivity($uuid));
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The account method is not supported.');
        } catch(JsonException $exception){ return $this->error(422,'VALIDATION_FAILED',$exception->getMessage()); }
        catch(DomainException $exception){
            $message=strtolower($exception->getMessage()); $status=str_contains($message,'session')||str_contains($message,'bearer')||str_contains($message,'password')?401:403;
            return $this->error($status,$status===401?'AUTHENTICATION_FAILED':'AUTHORISATION_FAILED',$exception->getMessage());
        } catch(Throwable){ return $this->error(500,'INTERNAL_ERROR','WonderOS could not complete the account request.'); }
    }

    private function decode(string $rawBody):array { $payload=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR); return is_array($payload)?$payload:[]; }
    private function user(User $user):array { return ['uuid'=>$user->uuid,'email'=>$user->email,'display_name'=>$user->displayName,'role'=>$user->role->value,'status'=>$user->status]; }
    private function uuid():string { $data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4)); }
    private function success(int $status,array $data):array { return ['status'=>$status,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]]; }
    private function error(int $status,string $code,string $message):array { return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
}