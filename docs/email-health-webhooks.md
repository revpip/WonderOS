# Email health and provider webhooks

Apply migration `0015_email_health_webhooks.up.sql` and configure `EMAIL_WEBHOOK_SECRET`.

The provider posts JSON to `POST /v1/email/webhooks/provider` with `event_id`, `message_id`, `event_type`, `recipient` and `occurred_at`. Supported events are delivered, deferred, bounced, blocked, complained, opened and clicked.

Signatures use HMAC-SHA256 over `<timestamp>.<raw-body>`. Send the timestamp in `X-Wonder-Timestamp` and the lowercase hexadecimal signature in `X-Wonder-Signature`. Requests older than five minutes are rejected.

Provider event IDs are unique, making retries idempotent. Hard bounces, blocks and complaints create or reactivate an email suppression. Suppressed recipients must be excluded by the delivery transport before a future production rollout.

Administrators can inspect `GET /v1/admin/email-health` or open `http://localhost:8081/email-health.html`. The dashboard shows thirty-day delivered, bounced, complaint and deferred counts, active suppressions and recent events.

Webhook secrets remain environment-only. Raw provider payloads are retained for operational diagnosis and therefore require an agreed retention period and personal-data policy before production.