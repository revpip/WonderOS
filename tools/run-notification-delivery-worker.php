<?php

declare(strict_types=1);

use PDO;
use Throwable;
use WonderOS\Notifications\ResendEmailTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = new PDO((string) getenv('DATABASE_DSN'), (string) getenv('DATABASE_USER'), (string) getenv('DATABASE_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$transport = new ResendEmailTransport((string)getenv('RESEND_API_KEY'),(string)getenv('EMAIL_FROM_ADDRESS'),getenv('EMAIL_FROM_NAME') ?: 'WonderOS');
$maxAttempts=max(1,(int)(getenv('EMAIL_MAX_ATTEMPTS')?:5));
$processed=$sent=$deferred=$failed=$suppressed=0;

$reputation=$pdo->query("SELECT r.*,m.delivered,m.bounces,m.complaints,m.total FROM wonder_email_reputation_settings r CROSS JOIN (SELECT COUNT(*) FILTER (WHERE event_type='delivered') delivered,COUNT(*) FILTER (WHERE event_type IN ('bounced','blocked')) bounces,COUNT(*) FILTER (WHERE event_type='complained') complaints,COUNT(*) total FROM wonder_email_events WHERE occurred_at>=NOW()-INTERVAL '30 days') m WHERE r.singleton=TRUE")->fetch(PDO::FETCH_ASSOC);
$total=max(1,(int)$reputation['total']);
$bounceRate=(int)$reputation['bounces']/$total;$complaintRate=(int)$reputation['complaints']/$total;
if((int)$reputation['total']>=(int)$reputation['minimum_sample_size']&&($bounceRate>=(float)$reputation['bounce_rate_threshold']||$complaintRate>=(float)$reputation['complaint_rate_threshold'])){
    $reason=sprintf('Automatic reputation pause: bounce %.3f%%, complaint %.3f%%.',$bounceRate*100,$complaintRate*100);
    $s=$pdo->prepare("UPDATE wonder_email_reputation_settings SET status='paused',paused_at=COALESCE(paused_at,NOW()),pause_reason=:reason,updated_at=NOW() WHERE singleton=TRUE");$s->execute(['reason'=>$reason]);
    fwrite(STDOUT,json_encode(['processed'=>0,'sent'=>0,'deferred'=>0,'failed'=>0,'suppressed'=>0,'paused'=>true,'reason'=>$reason],JSON_THROW_ON_ERROR).PHP_EOL);exit(0);
}
if(($reputation['status']??'active')==='paused'){fwrite(STDOUT,json_encode(['processed'=>0,'sent'=>0,'deferred'=>0,'failed'=>0,'suppressed'=>0,'paused'=>true,'reason'=>$reputation['pause_reason']],JSON_THROW_ON_ERROR).PHP_EOL);exit(0);}

$sql="SELECT d.*,n.recipient_uuid,n.title,n.body,n.action_url,u.email,p.quiet_hours_start,p.quiet_hours_end,p.timezone FROM wonder_notification_deliveries d JOIN wonder_notifications n ON n.uuid=d.notification_uuid JOIN wonder_users u ON u.uuid=n.recipient_uuid JOIN wonder_notification_preferences p ON p.user_uuid=n.recipient_uuid WHERE d.status='queued' AND d.channel='email' AND COALESCE(d.next_attempt_at,d.available_at)<=NOW() ORDER BY COALESCE(d.next_attempt_at,d.available_at),d.uuid FOR UPDATE OF d SKIP LOCKED LIMIT 50";
$pdo->beginTransaction();
$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row){
    $processed++;
    $check=$pdo->prepare('SELECT 1 FROM wonder_email_suppressions WHERE lower(email)=lower(:email) AND released_at IS NULL LIMIT 1');$check->execute(['email'=>$row['email']]);
    if($check->fetchColumn()){$s=$pdo->prepare("UPDATE wonder_notification_deliveries SET status='suppressed',attempted_at=NOW(),failure_reason='Recipient is actively suppressed.' WHERE uuid=:uuid");$s->execute(['uuid'=>$row['uuid']]);$suppressed++;continue;}
    $timezone=new DateTimeZone((string)$row['timezone']);$now=new DateTimeImmutable('now',$timezone);$start=$row['quiet_hours_start'];$end=$row['quiet_hours_end'];
    if($start!==null&&$end!==null){$clock=$now->format('H:i:s');$quiet=$start<$end?($clock>=$start&&$clock<$end):($clock>=$start||$clock<$end);if($quiet){$resume=new DateTimeImmutable($now->format('Y-m-d').' '.$end,$timezone);if($resume<=$now)$resume=$resume->modify('+1 day');$stmt=$pdo->prepare('UPDATE wonder_notification_deliveries SET available_at=:available,claimed_at=NULL WHERE uuid=:uuid');$stmt->execute(['available'=>$resume->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),'uuid'=>$row['uuid']]);$deferred++;continue;}}
    try{$result=$transport->send((string)$row['email'],(string)$row['title'],'<p>'.htmlspecialchars((string)$row['body'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><p><a href="'.htmlspecialchars((string)$row['action_url'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">Open in WonderOS</a></p>',(string)$row['body']."\n\n".(string)$row['action_url']);$stmt=$pdo->prepare("UPDATE wonder_notification_deliveries SET status='sent',attempts=attempts+1,attempted_at=NOW(),sent_at=NOW(),provider_message_id=:message,last_response_code=:code,failure_reason=NULL WHERE uuid=:uuid");$stmt->execute(['message'=>$result['message_id'],'code'=>$result['response_code'],'uuid'=>$row['uuid']]);$attempt=$pdo->prepare('INSERT INTO wonder_notification_delivery_attempts(uuid,delivery_uuid,succeeded,response_code,provider_message_id) VALUES(gen_random_uuid(),:delivery,TRUE,:code,:message)');$attempt->execute(['delivery'=>$row['uuid'],'code'=>$result['response_code'],'message'=>$result['message_id']]);$sent++;}
    catch(Throwable $e){$attempts=(int)$row['attempts']+1;$terminal=$attempts>=$maxAttempts;$delay=min(3600,60*(2**min($attempts,6)));$stmt=$pdo->prepare("UPDATE wonder_notification_deliveries SET status=:status,attempts=:attempts,attempted_at=NOW(),next_attempt_at=CASE WHEN :terminal THEN NULL ELSE NOW()+(:delay||' seconds')::interval END,failure_reason=:reason WHERE uuid=:uuid");$stmt->execute(['status'=>$terminal?'failed':'queued','attempts'=>$attempts,'terminal'=>$terminal,'delay'=>$delay,'reason'=>substr($e->getMessage(),0,1000),'uuid'=>$row['uuid']]);$attempt=$pdo->prepare('INSERT INTO wonder_notification_delivery_attempts(uuid,delivery_uuid,succeeded,failure_reason) VALUES(gen_random_uuid(),:delivery,FALSE,:reason)');$attempt->execute(['delivery'=>$row['uuid'],'reason'=>substr($e->getMessage(),0,1000)]);$failed++;}
}
$pdo->commit();
fwrite(STDOUT,json_encode(compact('processed','sent','deferred','failed','suppressed'),JSON_THROW_ON_ERROR).PHP_EOL);
