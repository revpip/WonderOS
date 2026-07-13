<?php

declare(strict_types=1);

use PDO;
use Throwable;
use WonderOS\Notifications\HttpEmailTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = new PDO(
    (string) getenv('DATABASE_DSN'),
    (string) getenv('DATABASE_USER'),
    (string) getenv('DATABASE_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$transport = new HttpEmailTransport(
    (string) getenv('EMAIL_API_ENDPOINT'),
    (string) getenv('EMAIL_API_KEY'),
    (string) getenv('EMAIL_FROM_ADDRESS'),
    getenv('EMAIL_FROM_NAME') ?: 'WonderOS',
);

$query = $pdo->query(
    "SELECT DISTINCT n.recipient_uuid, u.email, p.timezone,
            p.quiet_hours_start, p.quiet_hours_end
       FROM wonder_notification_deliveries d
       JOIN wonder_notifications n ON n.uuid = d.notification_uuid
       JOIN wonder_users u ON u.uuid = n.recipient_uuid
       JOIN wonder_notification_preferences p ON p.user_uuid = n.recipient_uuid
      WHERE d.status = 'queued'
        AND d.channel = 'digest'
        AND d.available_at <= NOW()"
);

$sent = 0;
$failed = 0;
$deferred = 0;

foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $user) {
    $timezone = new DateTimeZone((string) $user['timezone']);
    $now = new DateTimeImmutable('now', $timezone);
    $clock = $now->format('H:i:s');
    $start = $user['quiet_hours_start'];
    $end = $user['quiet_hours_end'];

    $quiet = $start !== null && $end !== null
        && ($start < $end
            ? ($clock >= $start && $clock < $end)
            : ($clock >= $start || $clock < $end));

    if ($quiet) {
        $deferred++;
        continue;
    }

    $itemsQuery = $pdo->prepare(
        "SELECT d.uuid, n.title, n.body, n.action_url
           FROM wonder_notification_deliveries d
           JOIN wonder_notifications n ON n.uuid = d.notification_uuid
          WHERE d.status = 'queued'
            AND d.channel = 'digest'
            AND d.available_at <= NOW()
            AND n.recipient_uuid = :recipient
          ORDER BY n.created_at"
    );
    $itemsQuery->execute(['recipient' => $user['recipient_uuid']]);
    $items = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);

    if ($items === []) {
        continue;
    }

    $html = '<h1>Your WonderOS daily digest</h1><ul>';
    $text = "Your WonderOS daily digest\n\n";

    foreach ($items as $item) {
        $title = htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = htmlspecialchars((string) $item['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = htmlspecialchars((string) $item['action_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= "<li><strong>{$title}</strong><br>{$body}<br><a href=\"{$url}\">Open</a></li>";
        $text .= $item['title'] . "\n" . $item['body'] . "\n" . $item['action_url'] . "\n\n";
    }
    $html .= '</ul>';

    try {
        $result = $transport->send((string) $user['email'], 'Your WonderOS daily digest', $html, $text);
        $pdo->beginTransaction();
        $update = $pdo->prepare(
            "UPDATE wonder_notification_deliveries
                SET status = 'sent', attempts = attempts + 1,
                    attempted_at = NOW(), sent_at = NOW(),
                    provider_message_id = :message_id,
                    last_response_code = :response_code
              WHERE uuid = :uuid"
        );
        foreach ($items as $item) {
            $update->execute([
                'message_id' => $result['message_id'],
                'response_code' => $result['response_code'],
                'uuid' => $item['uuid'],
            ]);
        }
        $pdo->commit();
        $sent++;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $failed++;
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    }
}

fwrite(STDOUT, json_encode(compact('sent', 'failed', 'deferred'), JSON_THROW_ON_ERROR) . PHP_EOL);
