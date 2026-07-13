<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Knowledge\Claim\ClaimCollaborationRepository;
use WonderOS\Knowledge\Claim\ClaimRepository;

final readonly class ClaimCollaborationApi
{
    public function __construct(private ClaimCollaborationRepository $collaboration,private ClaimRepository $claims,private AuthService $auth,private AuditRepository $audit) {}

    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if(!($path==='/v1/my-assignments'||preg_match('#^/v1/claims/(WND-CLM-\d{6})/(assignments|comments)$#',$path,$m)||preg_match('#^/v1/claim-assignments/([0-9a-f-]{36})$#i',$path,$a))) return null;
        try {
            $user=$this->auth->authenticate($headers['authorization']??null);
            if($method==='GET'&&$path==='/v1/my-assignments') return $this->ok($this->collaboration->workForUser($user->uuid));
            if(isset($m[1])&&$this->claims->claim($m[1])===null) return $this->error(404,'CLAIM_NOT_FOUND','The claim does not exist.');
            if($method==='GET'&&($m[2]??'')==='assignments') return $this->ok($this->collaboration->assignmentsForClaim($m[1]));
            if($method==='GET'&&($m[2]??'')==='comments') return $this->ok($this->collaboration->commentsForClaim($m[1]));
            $payload=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR); if(!is_array($payload)) $payload=[];
            if($method==='POST'&&($m[2]??'')==='assignments'){
                $user->role->assertPermits('editorial.manage');
                $row=$this->collaboration->assign(['uuid'=>$this->uuid(),'claim_wonder_id'=>$m[1],'assignee_uuid'=>(string)($payload['assignee_uuid']??''),'assigned_by'=>$user->uuid,'assignment_type'=>(string)($payload['assignment_type']??'research'),'due_at'=>$payload['due_at']??null]);
                $this->audit->record($user,'claim.assigned','claim',$m[1],'Assigned claim '.$m[1].'.',['assignment_uuid'=>$row['uuid'],'assignee_uuid'=>$row['assignee_uuid'],'assignment_type'=>$row['assignment_type'],'due_at'=>$row['due_at']]); return $this->created($row);
            }
            if($method==='POST'&&($m[2]??'')==='comments'){
                $user->role->assertPermits('knowledge.contribute'); $body=trim((string)($payload['body']??'')); if($body==='') throw new DomainException('Comment body is required.');
                $row=$this->collaboration->comment(['uuid'=>$this->uuid(),'claim_wonder_id'=>$m[1],'author_uuid'=>$user->uuid,'parent_uuid'=>$payload['parent_uuid']??null,'body'=>$body,'mentions'=>$payload['mentions']??[]]);
                $this->audit->record($user,'claim.comment_added','claim',$m[1],'Added editorial discussion to '.$m[1].'.',['comment_uuid'=>$row['uuid'],'parent_uuid'=>$row['parent_uuid']]); return $this->created($row);
            }
            if($method==='PUT'&&isset($a[1])){
                $user->role->assertPermits('editorial.manage'); $row=$this->collaboration->updateAssignment($a[1],(string)($payload['status']??''),$user->uuid);
                $this->audit->record($user,'claim.assignment_updated','claim',$row['claim_wonder_id'],'Updated claim assignment.',['assignment_uuid'=>$row['uuid'],'status'=>$row['status']]); return $this->ok($row);
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The collaboration method is not supported.');
        } catch(DomainException $e){return $this->error(403,'AUTHORISATION_FAILED',$e->getMessage());} catch(Throwable $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
    }
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
    private function ok(array $data):array{return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function created(array $data):array{$r=$this->ok($data);$r['status']=201;return $r;}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}
