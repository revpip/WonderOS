<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Knowledge\Claim\ClaimRepository;
use WonderOS\Knowledge\Entity\EntityRepository;

final readonly class ClaimApi
{
    public function __construct(private ClaimRepository $claims, private EntityRepository $entities, private AuthService $auth, private AuditRepository $audit) {}

    /** @param array<string,string> $headers */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if(!($path==='/v1/sources'||preg_match('#^/v1/sources/WND-SRC-\d{6}$#',$path)||preg_match('#^/v1/entities/WND-ENT-\d{6}/claims$#',$path)||preg_match('#^/v1/claims/WND-CLM-\d{6}/evidence$#',$path))) return null;
        try {
            if($method==='GET'&&preg_match('#^/v1/sources/(WND-SRC-\d{6})$#',$path,$m)){
                $source=$this->claims->source($m[1]);
                return $source?$this->success(200,$source):$this->error(404,'SOURCE_NOT_FOUND','The source does not exist.');
            }
            if($method==='GET'&&preg_match('#^/v1/entities/(WND-ENT-\d{6})/claims$#',$path,$m)) return $this->success(200,$this->claims->claimsForEntity($m[1]));
            if($method==='GET'&&preg_match('#^/v1/claims/(WND-CLM-\d{6})/evidence$#',$path,$m)) return $this->success(200,$this->claims->evidenceForClaim($m[1]));

            $user=$this->auth->authenticate($headers['authorization']??null); $user->role->assertPermits('knowledge.contribute');
            $payload=$this->decode($rawBody);
            if($method==='POST'&&$path==='/v1/sources'){
                $this->required($payload,'title');
                $row=$this->claims->createSource(['wonder_id'=>$this->claims->nextIdentity('SRC'),'title'=>trim((string)$payload['title']),'url'=>$payload['url']??null,'publisher'=>$payload['publisher']??null,'published_at'=>$payload['published_at']??null,'source_type'=>$payload['source_type']??'other']);
                $this->audit->record($user,'source.created','source',$row['wonder_id'],'Created source '.$row['title'].'.',['source_type'=>$row['source_type'],'url'=>$row['url']]);
                return $this->success(201,$row);
            }
            if($method==='POST'&&preg_match('#^/v1/entities/(WND-ENT-\d{6})/claims$#',$path,$m)){
                $this->entities->get(\WonderOS\Core\Identity\WonderId::parse($m[1])); $this->required($payload,'statement');
                $row=$this->claims->createClaim(['wonder_id'=>$this->claims->nextIdentity('CLM'),'entity_wonder_id'=>$m[1],'statement'=>trim((string)$payload['statement']),'claim_type'=>$payload['claim_type']??'fact','confidence'=>(float)($payload['confidence']??0.5)]);
                $this->audit->record($user,'claim.created','claim',$row['wonder_id'],'Created claim for '.$m[1].'.',['entity_wonder_id'=>$m[1],'claim_type'=>$row['claim_type'],'confidence'=>$row['confidence']]);
                return $this->success(201,$row);
            }
            if($method==='POST'&&preg_match('#^/v1/claims/(WND-CLM-\d{6})/evidence$#',$path,$m)){
                $this->required($payload,'source_wonder_id');
                $row=$this->claims->addEvidence(['uuid'=>$this->uuid(),'claim_wonder_id'=>$m[1],'source_wonder_id'=>(string)$payload['source_wonder_id'],'locator'=>$payload['locator']??null,'excerpt'=>$payload['excerpt']??null,'stance'=>$payload['stance']??'supports','strength'=>(float)($payload['strength']??0.5)]);
                $this->audit->record($user,'evidence.linked','claim',$m[1],'Linked evidence to claim '.$m[1].'.',['source_wonder_id'=>$row['source_wonder_id'],'stance'=>$row['stance'],'strength'=>$row['strength']]);
                return $this->success(201,$row);
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The claims method is not supported.');
        } catch(DomainException $e){return $this->error(403,'AUTHORISATION_FAILED',$e->getMessage());}
          catch(Throwable $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
    }

    private function decode(string $raw):array{$value=json_decode($raw,true,512,JSON_THROW_ON_ERROR);return is_array($value)?$value:[];}
    private function required(array $payload,string $field):void{if(!isset($payload[$field])||trim((string)$payload[$field])==='')throw new \InvalidArgumentException($field.' is required.');}
    private function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
    private function success(int $status,array $data):array{return ['status'=>$status,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}