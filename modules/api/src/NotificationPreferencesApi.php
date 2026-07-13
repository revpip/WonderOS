<?php

declare(strict_types=1);
namespace WonderOS\Api;

use PDO;
use Throwable;
use WonderOS\Core\Auth\AuthService;

final readonly class NotificationPreferencesApi
{
    public function __construct(private PDO $connection, private AuthService $auth) {}

    /** @param array<string,string> $headers */
    public function handle(string $method,string $path,string $rawBody,array $headers): ?array
    {
        if($path!=='/v1/notification-preferences') return null;
        try {
            $user=$this->auth->authenticate($headers['authorization']??null);
            if($method==='GET') return $this->ok($this->preferences($user->uuid));
            if($method!=='PUT') return $this->error(405,'METHOD_NOT_ALLOWED','The preferences method is not supported.');
            $payload=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR); if(!is_array($payload)) $payload=[];
            $timezone=(string)($payload['timezone']??'Europe/London'); new \DateTimeZone($timezone);
            $sql='INSERT INTO wonder_notification_preferences (user_uuid,assignment_alerts,mention_alerts,due_alerts,email_enabled,daily_digest,quiet_hours_start,quiet_hours_end,timezone) VALUES (:user_uuid,:assignment_alerts,:mention_alerts,:due_alerts,:email_enabled,:daily_digest,:quiet_hours_start,:quiet_hours_end,:timezone) ON CONFLICT (user_uuid) DO UPDATE SET assignment_alerts=EXCLUDED.assignment_alerts,mention_alerts=EXCLUDED.mention_alerts,due_alerts=EXCLUDED.due_alerts,email_enabled=EXCLUDED.email_enabled,daily_digest=EXCLUDED.daily_digest,quiet_hours_start=EXCLUDED.quiet_hours_start,quiet_hours_end=EXCLUDED.quiet_hours_end,timezone=EXCLUDED.timezone,updated_at=NOW() RETURNING *';
            $statement=$this->connection->prepare($sql); $statement->execute([
                'user_uuid'=>$user->uuid,'assignment_alerts'=>(bool)($payload['assignment_alerts']??true),'mention_alerts'=>(bool)($payload['mention_alerts']??true),'due_alerts'=>(bool)($payload['due_alerts']??true),'email_enabled'=>(bool)($payload['email_enabled']??false),'daily_digest'=>(bool)($payload['daily_digest']??false),'quiet_hours_start'=>$payload['quiet_hours_start']??null,'quiet_hours_end'=>$payload['quiet_hours_end']??null,'timezone'=>$timezone,
            ]);
            return $this->ok($statement->fetch(PDO::FETCH_ASSOC));
        } catch(Throwable $e){return $this->error(422,'VALIDATION_FAILED',$e->getMessage());}
    }

    private function preferences(string $uuid):array
    {
        $statement=$this->connection->prepare('SELECT * FROM wonder_notification_preferences WHERE user_uuid=:uuid'); $statement->execute(['uuid'=>$uuid]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?['user_uuid'=>$uuid,'assignment_alerts'=>true,'mention_alerts'=>true,'due_alerts'=>true,'email_enabled'=>false,'daily_digest'=>false,'quiet_hours_start'=>null,'quiet_hours_end'=>null,'timezone'=>'Europe/London']:$row;
    }
    private function ok(array $data):array{return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}