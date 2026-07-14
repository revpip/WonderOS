<?php

declare(strict_types=1);
namespace WonderOS\Api;

use PDO;
use Throwable;
use WonderOS\Core\Audit\AuditRepository;
use WonderOS\Core\Auth\AuthService;

final readonly class EmailReputationApi
{
    public function __construct(private PDO $pdo, private AuthService $auth, private AuditRepository $audit) {}

    /** @param array<string,string> $headers */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if(!($path==='/v1/admin/email-reputation'||preg_match('#^/v1/admin/email-suppressions/([0-9a-f-]{36})/release$#i',$path,$m))) return null;
        try {
            $user=$this->auth->authenticate($headers['authorization']??null); $user->role->assertPermits('users.manage');
            if($method==='GET'&&$path==='/v1/admin/email-reputation') return $this->ok($this->snapshot());
            $payload=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR); if(!is_array($payload)) $payload=[];
            if($method==='PUT'&&$path==='/v1/admin/email-reputation'){
                $bounce=(float)($payload['bounce_rate_threshold']??0); $complaint=(float)($payload['complaint_rate_threshold']??0); $sample=(int)($payload['minimum_sample_size']??0);
                if($bounce<=0||$bounce>1||$complaint<=0||$complaint>1||$sample<1) throw new \InvalidArgumentException('Thresholds must be valid proportions and sample size must be positive.');
                $status=in_array(($payload['status']??'active'),['active','paused'],true)?$payload['status']:'active';
                $s=$this->pdo->prepare("UPDATE wonder_email_reputation_settings SET status=:status,bounce_rate_threshold=:bounce,complaint_rate_threshold=:complaint,minimum_sample_size=:sample,paused_at=CASE WHEN :status='paused' THEN NOW() ELSE NULL END,pause_reason=:reason,updated_by=:user,updated_at=NOW() WHERE singleton=TRUE RETURNING *");
                $s->execute(['status'=>$status,'bounce'=>$bounce,'complaint'=>$complaint,'sample'=>$sample,'reason'=>$payload['pause_reason']??null,'user'=>$user->uuid]); $row=$s->fetch(PDO::FETCH_ASSOC);
                $this->audit->record($user,'email.reputation_updated','email_reputation','global','Updated email reputation controls.',['status'=>$status,'bounce_rate_threshold'=>$bounce,'complaint_rate_threshold'=>$complaint,'minimum_sample_size'=>$sample]);
                return $this->ok($row);
            }
            if($method==='POST'&&isset($m[1])){
                $reason=trim((string)($payload['reason']??'')); if($reason==='') throw new \InvalidArgumentException('A release reason is required.');
                $s=$this->pdo->prepare('UPDATE wonder_email_suppressions SET released_at=NOW(),released_by=:user,release_reason=:reason WHERE uuid=:uuid AND released_at IS NULL RETURNING *');
                $s->execute(['user'=>$user->uuid,'reason'=>$reason,'uuid'=>$m[1]]); $row=$s->fetch(PDO::FETCH_ASSOC); if($row===false) return $this->error(404,'SUPPRESSION_NOT_FOUND','The active suppression does not exist.');
                $this->audit->record($user,'email.suppression_released','email_suppression',$m[1],'Released an email suppression.',['email'=>$row['email'],'reason'=>$reason]);
                return $this->ok($row);
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The email reputation method is not supported.');
        } catch(\JsonException|\InvalidArgumentException $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
          catch(\DomainException $e){return $this->error(403,'AUTHORISATION_FAILED',$e->getMessage());}
          catch(Throwable){return $this->error(500,'INTERNAL_ERROR','WonderOS could not complete the email reputation request.');}
    }

    private function snapshot(): array
    {
        $settings=$this->pdo->query('SELECT * FROM wonder_email_reputation_settings WHERE singleton=TRUE')->fetch(PDO::FETCH_ASSOC);
        $metrics=$this->pdo->query("SELECT COUNT(*) FILTER (WHERE event_type='delivered') delivered,COUNT(*) FILTER (WHERE event_type IN ('bounced','blocked')) bounces,COUNT(*) FILTER (WHERE event_type='complained') complaints,COUNT(*) total FROM wonder_email_events WHERE occurred_at>=NOW()-INTERVAL '30 days'")->fetch(PDO::FETCH_ASSOC);
        $s=$this->pdo->query('SELECT * FROM wonder_email_suppressions WHERE released_at IS NULL ORDER BY created_at DESC LIMIT 250')->fetchAll(PDO::FETCH_ASSOC);
        return ['settings'=>$settings,'metrics'=>$metrics,'active_suppressions'=>$s];
    }
    private function ok(array $data):array{return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}
