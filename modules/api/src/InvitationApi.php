<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use JsonException;
use Throwable;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Core\Auth\InvitationRepository;
use WonderOS\Core\Auth\Role;
use WonderOS\Core\Auth\User;

final readonly class InvitationApi
{
    public function __construct(private AuthService $auth, private InvitationRepository $invitations) {}

    /** @param array<string,string> $headers @return array{status:int,body:array<string,mixed>}|null */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if($path!=='/v1/invitations' && $path!=='/v1/invitations/accept' && !preg_match('#^/v1/invitations/([0-9a-f-]{36})/revoke$#',$path)) return null;
        try {
            if($method==='POST' && $path==='/v1/invitations/accept') return $this->accept($this->decode($rawBody));
            $administrator=$this->auth->authenticate($headers['authorization']??null); $administrator->role->assertPermits('users.manage');
            if($method==='GET' && $path==='/v1/invitations') return $this->success(200,$this->invitations->invitations());
            if($method==='POST' && $path==='/v1/invitations') return $this->invite($this->decode($rawBody),$administrator);
            if($method==='POST' && preg_match('#^/v1/invitations/([0-9a-f-]{36})/revoke$#',$path,$matches)){ $this->invitations->revoke($matches[1]); return $this->success(200,['revoked'=>true]); }
            return $this->error(405,'METHOD_NOT_ALLOWED','The invitation method is not supported.');
        } catch(JsonException|DomainException $exception){
            $message=$exception->getMessage(); $auth=str_contains(strtolower($message),'session')||str_contains(strtolower($message),'bearer');
            return $this->error($auth?401:422,$auth?'AUTHENTICATION_FAILED':'VALIDATION_FAILED',$message);
        } catch(Throwable){ return $this->error(500,'INTERNAL_ERROR','WonderOS could not complete the invitation request.'); }
    }

    private function invite(array $payload,User $administrator): array
    {
        $email=strtolower(trim((string)($payload['email']??''))); $name=trim((string)($payload['display_name']??''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name==='') throw new DomainException('A valid email address and display name are required.');
        $role=Role::from((string)($payload['role']??'viewer')); $token=bin2hex(random_bytes(32)); $expires=(new DateTimeImmutable())->add(new DateInterval('P7D'));
        $invitation=$this->invitations->create($this->uuid(),$email,$name,$role,hash('sha256',$token),$administrator,$expires);
        $invitation['acceptance_token']=$token;
        return $this->success(201,$invitation);
    }

    private function accept(array $payload): array
    {
        $token=(string)($payload['token']??''); $password=(string)($payload['password']??'');
        if(strlen($password)<12) throw new DomainException('Passwords must contain at least 12 characters.');
        $invite=$this->invitations->findUsableByToken($token); if($invite===null) throw new DomainException('This invitation is invalid, expired or already used.');
        $user=new User($this->uuid(),(string)$invite['email'],(string)$invite['display_name'],Role::from((string)$invite['role']));
        $this->invitations->accept((string)$invite['uuid'],$user,password_hash($password,PASSWORD_DEFAULT));
        return $this->success(201,['account_created'=>true,'email'=>$user->email,'display_name'=>$user->displayName,'role'=>$user->role->value]);
    }

    private function decode(string $body): array { $value=json_decode($body,true,512,JSON_THROW_ON_ERROR); return is_array($value)?$value:[]; }
    private function uuid(): string { $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
    private function success(int $status,array $data): array { return ['status'=>$status,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]]; }
    private function error(int $status,string $code,string $message): array { return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
}