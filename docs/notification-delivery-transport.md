# Notification delivery transport

Genesis-026 turns queued notification deliveries into real outbound email while preserving user preferences and a complete attempt history.

## Environment

```text
EMAIL_API_ENDPOINT=https://provider.example/v1/send
EMAIL_API_KEY=replace-me
EMAIL_FROM_ADDRESS=notifications@example.com
EMAIL_FROM_NAME=WonderOS
EMAIL_MAX_ATTEMPTS=5
```

The configured endpoint must accept a JSON body containing `from`, `to`, `subject`, `html` and `text`, and may return either `id` or `message_id`.

## Workers

Run individual transactional mail frequently:

```bash
php tools/run-notification-delivery-worker.php
```

Run digest delivery after the digest queue becomes available:

```bash
php tools/run-notification-digest-worker.php
```

A practical production schedule is every five minutes for transactional delivery and every fifteen minutes for digest delivery. The database queue, `SKIP LOCKED` claim pattern and idempotency header make repeated execution safe.

## Behaviour

- quiet hours are evaluated in each recipient's IANA timezone;
- messages inside quiet hours are deferred until the quiet period ends;
- failures use exponential backoff and become terminal after the configured maximum attempts;
- every attempt is written to `wonder_notification_delivery_attempts`;
- provider message IDs and response codes are retained without storing credentials;
- daily digest rows are rendered into one email per recipient;
- HTML content is escaped before rendering.

## Operational boundary

The HTTP transport is provider-neutral. Production deployment must choose a transactional email provider, configure domain authentication, manage API secrets outside the repository, and process provider webhooks for bounces, complaints and delivery confirmation in a later iteration.
