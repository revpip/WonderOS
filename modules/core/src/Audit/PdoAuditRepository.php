<?php

declare(strict_types=1);

namespace WonderOS\Core\Audit;

use JsonException;
use PDO;
use WonderOS\Core\Auth\User;

final readonly class PdoAuditRepository implements AuditRepository
{
    public function __construct(private PDO $connection) {}

    public function record(User $actor, string $action, string $subjectType, string $subjectId, string $summary, array $metadata = []): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO wonder_audit_events (event_uuid,actor_uuid,actor_email,action,subject_type,subject_id,summary,metadata)
             VALUES (:event_uuid,:actor_uuid,:actor_email,:action,:subject_type,:subject_id,:summary,CAST(:metadata AS JSONB))'
        );
        $statement->execute([
            'event_uuid' => $this->uuid(),
            'actor_uuid' => $actor->uuid,
            'actor_email' => $actor->email,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    public function search(array $filters = []): array
    {
        $where = [];
        $params = [];
        foreach (['actor_email','action','subject_type','subject_id'] as $field) {
            if (isset($filters[$field]) && trim((string)$filters[$field]) !== '') {
                $where[] = $field . ' = :' . $field;
                $params[$field] = trim((string)$filters[$field]);
            }
        }
        $limit = max(1, min(250, (int)($filters['limit'] ?? 100)));
        $sql = 'SELECT event_uuid,occurred_at,actor_uuid,actor_email,action,subject_type,subject_id,summary,metadata
                FROM wonder_audit_events' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
               ' ORDER BY occurred_at DESC, id DESC LIMIT ' . $limit;
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return array_map(static function(array $row): array {
            $row['metadata'] = json_decode((string)$row['metadata'], true, 512, JSON_THROW_ON_ERROR);
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function uuid(): string
    {
        $data=random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4));
    }
}