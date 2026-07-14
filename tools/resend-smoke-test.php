<?php

declare(strict_types=1);

use WonderOS\Notifications\ResendEmailTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

$apiKey = trim((string) getenv('RESEND_API_KEY'));
$from = trim((string) getenv('EMAIL_FROM_ADDRESS'));
$to = trim((string) getenv('RESEND_SMOKE_TO'));

if ($apiKey === '' || $from === '' || $to === '') {
    fwrite(STDOUT, "Resend smoke test skipped: required secrets are unavailable.\n");
    exit(0);
}

$transport = new ResendEmailTransport($apiKey, $from, getenv('EMAIL_FROM_NAME') ?: 'WonderOS CI');
$marker = gmdate('Y-m-d\TH:i:s\Z');
$result = $transport->send(
    $to,
    'WonderOS Resend smoke test',
    '<p>WonderOS CI delivery test completed at <strong>' . htmlspecialchars($marker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>.</p>',
    'WonderOS CI delivery test completed at ' . $marker . '.',
);

if (($result['message_id'] ?? null) === null) {
    fwrite(STDERR, "Resend accepted the request without returning an email ID.\n");
    exit(1);
}

fwrite(STDOUT, json_encode(['sent' => true, 'message_id' => $result['message_id']], JSON_THROW_ON_ERROR) . PHP_EOL);
