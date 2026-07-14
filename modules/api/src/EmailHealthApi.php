<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DateTimeImmutable;
use PDO;
use Throwable;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Notifications\GenericJsonWebhookAdapter;
use WonderOS\Notifications\ResendWebhookAdapter;
use WonderOS\Notifications\ResendWebhookVerifier;

final readonly class EmailHealthApi
{
    public function __construct(private PDO $db, private AuthService $auth) {}

    /** @param array<string,string> $headers */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if($method==='POST'&&$path==='/v1/email/webhooks/provider') return $this->webhook($rawBody,$headers);
        if($method==='GET'&&$path==='/v1/admin/email-health') return $this->health($headers);
        return null;
    }

    private function webhook(string $rawBody,array $headers): array
    {
        try {
            $provider=strtolower((string)(getenv('EMAIL_PROVIDER')?:'resend'));
            $decoded=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($decoded)) throw new \InvalidArgumentException('Invalid webhook payload.');

            if($provider==='resend'){
                (new ResendWebhookVerifier((string)getenv('RESEND_WEBHOOK_SECRET')))->verify($rawBody,$headers);
                $event=(new ResendWebhookAdapter())->normalise($decoded);
            } else {
                $secret=(string)getenv('EMAIL_WEBHOOK_SECRET');
                $timestamp=$headers['x-wonder-timestamp']??'';$signature=$headers['x-wonder-signature']??'';
                if($secret===''||!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>300||!hash_equals(hash_hmac('sha256',$timestamp.'.'.$rawBody,$secret),$signature)) return $this->error(401,'INVALID_WEBHOOK_SIGNATURE','Webhook signature verification failed.');
                $event=(new GenericJsonWebhookAdapter())->normalise($decoded);
            }

            $type=(string)$event['event_type'];
            $classification=$event['bounce_classification']??null;
            $this->db->beginTransaction();
            $insert=$this->db->prepare('INSERT INTO wonder_email_events(provider_event_id,provider_message_id,event_type,recipient_email,payload,occurred_at,bounce_classification) VALUES(:event_id,:message_id,:event_type,:recipient,CAST(:payload AS jsonb),:occurred_at,:classification) ON CONFLICT(provider_event_id) DO NOTHING');
            $insert->execute([
                'event_id'=>$event['event_id'],'message_id'=>$event['message_id']??null,'event_type'=>$type,
                'recipient'=>strtolower((string)$event['recipient']),'payload'=>json_encode($event['payload']??$decoded,JSON_THROW_ON_ERROR),
                'occurred_at'=>(new DateTimeImmutable((string)$event['occurred_at']))->format(DATE_ATOM),'classification'=>$classification,
            ]);
            if($insert->rowCount()===0){$this->db->commit();return $this->ok(['accepted'=>true,'duplicate'=>true]);}

            if(!empty($event['message_id'])){
                $column=match($type){'delivered'=>'delivered_at','bounced','blocked'=>'bounced_at','complained'=>'complained_at',default=>null};
                $sql='UPDATE wonder_notification_deliveries SET provider_status=:status'.($column?', '.$column.'=:occurred':'').' WHERE provider_message_id=:message';
                $values=['status'=>$type,'message'=>$event['message_id']];if($column)$values['occurred']=$event['occurred_at'];
                $statement=$this->db->prepare($sql);$statement->execute($values);
            }

            $permanentBounce=$type==='bounced'&&$classification!=='soft';
            if($permanentBounce||in_array($type,['blocked','complained'],true)){
                $reason=$type==='complained'?'complaint':($type==='blocked'?'blocked':'hard_bounce');
                $s=$this->db->prepare('INSERT INTO wonder_email_suppressions(email,reason,provider_event_id) VALUES(:email,:reason,:event) ON CONFLICT(email) DO UPDATE SET reason=EXCLUDED.reason,provider_event_id=EXCLUDED.provider_event_id,released_at=NULL');
                $s->execute(['email'=>strtolower((string)$event['recipient']),'reason'=>$reason,'event'=>$event['event_id']]);
            }
            $this->db->commit();return $this->ok(['accepted'=>true,'duplicate'=>false,'provider'=>$provider]);
        } catch(\InvalidArgumentException $e){if($this->db->inTransaction())$this->db->rollBack();return $this->error(401,'INVALID_WEBHOOK_SIGNATURE',$e->getMessage());}
        catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return $this->error(422,'WEBHOOK_REJECTED',$e->getMessage());}
    }

    private function health(array $headers): array
    {
        try{$user=$this->auth->authenticate($headers['authorization']??null);$user->role->assertPermits('users.manage');
            $summary=$this->db->query("SELECT COUNT(*) FILTER(WHERE event_type='delivered') delivered,COUNT(*) FILTER(WHERE event_type IN('bounced','blocked')) bounced,COUNT(*) FILTER(WHERE event_type='complained') complained,COUNT(*) FILTER(WHERE event_type='deferred') deferred FROM wonder_email_events WHERE occurred_at>=NOW()-INTERVAL '30 days'")->fetch(PDO::FETCH_ASSOC);
            $suppressions=$this->db->query("SELECT email,reason,created_at FROM wonder_email_suppressions WHERE released_at IS NULL ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            $recent=$this->db->query("SELECT event_type,recipient_email,provider_message_id,bounce_classification,occurred_at FROM wonder_email_events ORDER BY occurred_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            return $this->ok(['period_days'=>30,'summary'=>$summary,'active_suppressions'=>$suppressions,'recent_events'=>$recent]);
        }catch(Throwable $e){return $this->error(403,'EMAIL_HEALTH_FORBIDDEN',$e->getMessage());}
    }
    private function ok(array $data):array{return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}
