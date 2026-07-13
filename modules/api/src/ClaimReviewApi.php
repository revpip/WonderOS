<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use JsonException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Knowledge\Claim\ClaimRepository;

final readonly class ClaimReviewApi
{
    public function __construct(private ClaimRepository $claims, private AuthService $auth, private AuditRepository $audit) {}

    /** @param array<string,string> $headers */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if(!preg_match('#^/v1/claims/(WND-CLM-\d{6})(?:/(revisions|review))?$#',$path,$matches)) return null;
        $claimId=$matches[1]; $action=$matches[2]??null;
        try {
            if($method==='GET'&&$action===null){
                $claim=$this->claims->claim($claimId);
                return $claim?$this->success(200,$claim):$this->error(404,'CLAIM_NOT_FOUND','The claim does not exist.');
            }
            if($method==='GET'&&$action==='revisions'){
                if($this->claims->claim($claimId)===null) return $this->error(404,'CLAIM_NOT_FOUND','The claim does not exist.');
                return $this->success(200,$this->claims->claimHistory($claimId));
            }
            if(($method==='PUT'&&$action===null)||($method==='POST'&&$action==='review')){
                $user=$this->auth->authenticate($headers['authorization']??null);
                $user->role->assertPermits($action==='review'?'editorial.manage':'knowledge.contribute');
                $payload=$this->decode($rawBody);
                $expected=filter_var($payload['expected_revision']??null,FILTER_VALIDATE_INT);
                if($expected===false) throw new \InvalidArgumentException('expected_revision is required.');
                $changes=[];
                foreach(['statement','claim_type','confidence','status'] as $field) if(array_key_exists($field,$payload)) $changes[$field]=$payload[$field];
                if($action==='review'&&!isset($changes['status'])) throw new \InvalidArgumentException('status is required for claim review.');
                if(isset($changes['status'])&&!in_array($changes['status'],['draft','review','approved','disputed','archived'],true)) throw new \InvalidArgumentException('Unsupported claim status.');
                if(isset($changes['confidence'])&&((float)$changes['confidence']<0||(float)$changes['confidence']>1)) throw new \InvalidArgumentException('confidence must be between 0 and 1.');
                $claim=$this->claims->reviseClaim($claimId,$changes,(int)$expected,$user->uuid,isset($payload['change_note'])?trim((string)$payload['change_note']):null);
                $event=$action==='review'?'claim.reviewed':'claim.revised';
                $this->audit->record($user,$event,'claim',$claimId,ucfirst(str_replace('.',' ',$event)).' '.$claimId.'.',['revision'=>$claim['revision'],'status'=>$claim['status'],'confidence'=>$claim['confidence'],'change_note'=>$payload['change_note']??null]);
                return $this->success(200,$claim);
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The claim review method is not supported.');
        } catch(JsonException|\InvalidArgumentException $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
          catch(DomainException $e){$conflict=str_contains(strtolower($e->getMessage()),'reload')||str_contains(strtolower($e->getMessage()),'conflict');return $this->error($conflict?409:403,$conflict?'CLAIM_REVISION_CONFLICT':'AUTHORISATION_FAILED',$e->getMessage());}
          catch(Throwable){return $this->error(500,'INTERNAL_ERROR','WonderOS could not complete the claim review request.');}
    }

    private function decode(string $raw):array{$value=json_decode($raw,true,512,JSON_THROW_ON_ERROR);return is_array($value)?$value:[];}
    private function success(int $status,array $data):array{return ['status'=>$status,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}