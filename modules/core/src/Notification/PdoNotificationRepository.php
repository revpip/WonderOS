<?php

declare(strict_types=1);
namespace WonderOS\Core\Notification;

use PDO;

final readonly class PdoNotificationRepository implements NotificationRepository
{
    public function __construct(private PDO $connection) {}

    public function create(array $notification): array
    {
        $sql='INSERT INTO wonder_notifications (uuid,recipient_uuid,actor_uuid,notification_type,title,body,subject_type,subject_id,action_url,deduplication_key) VALUES (:uuid,:recipient_uuid,:actor_uuid,:notification_type,:title,:body,:subject_type,:subject_id,:action_url,:deduplication_key) ON CONFLICT (recipient_uuid,deduplication_key) DO UPDATE SET title=EXCLUDED.title,body=EXCLUDED.body,created_at=NOW() RETURNING *';
        $statement=$this->connection->prepare($sql); $statement->execute($notification); return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function forUser(string $userUuid,bool $unreadOnly=false,int $limit=100): array
    {
        $sql='SELECT n.*,u.display_name AS actor_name,u.email AS actor_email FROM wonder_notifications n LEFT JOIN wonder_users u ON u.uuid=n.actor_uuid WHERE n.recipient_uuid=:recipient'.($unreadOnly?' AND n.read_at IS NULL':'').' ORDER BY n.read_at NULLS FIRST,n.created_at DESC LIMIT :limit';
        $statement=$this->connection->prepare($sql); $statement->bindValue('recipient',$userUuid); $statement->bindValue('limit',$limit,PDO::PARAM_INT); $statement->execute(); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markRead(string $notificationUuid,string $userUuid): ?array
    {
        $statement=$this->connection->prepare('UPDATE wonder_notifications SET read_at=COALESCE(read_at,NOW()) WHERE uuid=:uuid AND recipient_uuid=:recipient RETURNING *');
        $statement->execute(['uuid'=>$notificationUuid,'recipient'=>$userUuid]); $row=$statement->fetch(PDO::FETCH_ASSOC); return $row===false?null:$row;
    }

    public function markAllRead(string $userUuid): int
    {
        $statement=$this->connection->prepare('UPDATE wonder_notifications SET read_at=NOW() WHERE recipient_uuid=:recipient AND read_at IS NULL'); $statement->execute(['recipient'=>$userUuid]); return $statement->rowCount();
    }

    public function generateDueNotifications(): int
    {
        $sql="INSERT INTO wonder_notifications (uuid,recipient_uuid,notification_type,title,body,subject_type,subject_id,action_url,deduplication_key)
SELECT gen_random_uuid(),a.assignee_uuid,CASE WHEN a.due_at<NOW() THEN 'overdue' ELSE 'due_soon' END,
CASE WHEN a.due_at<NOW() THEN 'Assignment overdue' ELSE 'Assignment due soon' END,
'Claim '||a.claim_wonder_id||CASE WHEN a.due_at<NOW() THEN ' is overdue.' ELSE ' is due within 48 hours.' END,
'claim',a.claim_wonder_id,'./claim-collaboration.html?claim='||a.claim_wonder_id,
(CASE WHEN a.due_at<NOW() THEN 'overdue:' ELSE 'due-soon:' END)||a.uuid::text
FROM wonder_claim_assignments a WHERE a.status='open' AND a.due_at IS NOT NULL AND a.due_at<=NOW()+INTERVAL '48 hours'
ON CONFLICT (recipient_uuid,deduplication_key) DO NOTHING";
        return $this->connection->exec($sql) ?: 0;
    }
}
