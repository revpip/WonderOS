<?php

declare(strict_types=1);
namespace WonderOS\Api;

use Throwable;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Core\Notification\NotificationRepository;

final readonly class NotificationApi
{
    public function __construct(private NotificationRepository $notifications, private AuthService $auth) {}

    /** @param array<string,string> $headers @param array<string,mixed> $query */
    public function handle(string $method,string $path,array $headers,array $query=[]): ?array
    {
        if(!($path==='/v1/notifications'||$path==='/v1/notifications/read-all'||preg_match('#^/v1/notifications/([0-9a-f-]{36})/read$#i',$path,$m))) return null;
        try {
            $user=$this->auth->authenticate($headers['authorization']??null);
            if($method==='GET'&&$path==='/v1/notifications'){
                $this->notifications->generateDueNotifications();
                $unread=filter_var($query['unread_only']??false,FILTER_VALIDATE_BOOLEAN);
                $limit=max(1,min(250,(int)($query['limit']??100)));
                $rows=$this->notifications->forUser($user->uuid,$unread,$limit);
                return $this->ok($rows,['unread_count'=>count(array_filter($rows,fn(array $n)=>$n['read_at']===null))]);
            }
            if($method==='POST'&&$path==='/v1/notifications/read-all') return $this->ok(['updated'=>$this->notifications->markAllRead($user->uuid)]);
            if($method==='POST'&&isset($m[1])){
                $row=$this->notifications->markRead($m[1],$user->uuid);
                return $row?$this->ok($row):$this->error(404,'NOTIFICATION_NOT_FOUND','The notification does not exist.');
            }
            return $this->error(405,'METHOD_NOT_ALLOWED','The notification method is not supported.');
        } catch(Throwable $e){return $this->error(401,'AUTHENTICATION_FAILED',$e->getMessage());}
    }

    private function ok(array $data,array $meta=[]):array{return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>$meta,'links'=>(object)[]]];}
    private function error(int $status,string $code,string $message):array{return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];}
}
