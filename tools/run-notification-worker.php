<?php

declare(strict_types=1);

use PDO;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo=new PDO((string)getenv('DATABASE_DSN'),(string)getenv('DATABASE_USER'),(string)getenv('DATABASE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
try {
    $due="INSERT INTO wonder_notifications (uuid,recipient_uuid,notification_type,title,body,subject_type,subject_id,action_url,deduplication_key)
    SELECT gen_random_uuid(),a.assignee_uuid,CASE WHEN a.due_at<NOW() THEN 'overdue' ELSE 'due_soon' END,
    CASE WHEN a.due_at<NOW() THEN 'Assignment overdue' ELSE 'Assignment due soon' END,
    'Claim '||a.claim_wonder_id||CASE WHEN a.due_at<NOW() THEN ' is overdue.' ELSE ' is due within 48 hours.' END,
    'claim',a.claim_wonder_id,'./claim-collaboration.html?claim='||a.claim_wonder_id,
    (CASE WHEN a.due_at<NOW() THEN 'overdue:' ELSE 'due-soon:' END)||a.uuid::text
    FROM wonder_claim_assignments a
    LEFT JOIN wonder_notification_preferences p ON p.user_uuid=a.assignee_uuid
    WHERE a.status='open' AND a.due_at IS NOT NULL AND a.due_at<=NOW()+INTERVAL '48 hours' AND COALESCE(p.due_alerts,TRUE)
    ON CONFLICT (recipient_uuid,deduplication_key) DO NOTHING";
    $generated=$pdo->exec($due)?:0;

    $deliveries="INSERT INTO wonder_notification_deliveries (uuid,notification_uuid,channel,status,available_at)
    SELECT gen_random_uuid(),n.uuid,'email',CASE WHEN p.email_enabled THEN 'queued' ELSE 'suppressed' END,
    CASE WHEN p.quiet_hours_start IS NULL THEN NOW() ELSE NOW() END
    FROM wonder_notifications n JOIN wonder_notification_preferences p ON p.user_uuid=n.recipient_uuid
    WHERE n.created_at>=NOW()-INTERVAL '2 hours' AND NOT p.daily_digest
    ON CONFLICT (notification_uuid,channel) DO NOTHING";
    $queued=$pdo->exec($deliveries)?:0;

    $digests="INSERT INTO wonder_notification_deliveries (uuid,notification_uuid,channel,status,available_at)
    SELECT gen_random_uuid(),n.uuid,'digest','queued',date_trunc('day',NOW())+INTERVAL '1 day 8 hours'
    FROM wonder_notifications n JOIN wonder_notification_preferences p ON p.user_uuid=n.recipient_uuid
    WHERE p.email_enabled AND p.daily_digest AND n.created_at>=date_trunc('day',NOW())
    ON CONFLICT (notification_uuid,channel) DO NOTHING";
    $digestQueued=$pdo->exec($digests)?:0;
    $pdo->commit();
    fwrite(STDOUT,json_encode(['generated'=>$generated,'email_queued'=>$queued,'digest_queued'=>$digestQueued],JSON_THROW_ON_ERROR).PHP_EOL);
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1);
}