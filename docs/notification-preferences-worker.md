# Notification preferences and worker

Genesis-025 moves due-date generation out of inbox reads and into an hourly command.

## Preferences

Authenticated users can read or replace their preferences through:

- `GET /v1/notification-preferences`
- `PUT /v1/notification-preferences`

Supported settings include assignment alerts, mention alerts, due alerts, email eligibility, daily digest mode, quiet hours and IANA timezone.

## Worker

Run hourly:

```bash
php tools/run-notification-worker.php
```

Example cron entry:

```cron
5 * * * * cd /srv/wonderos && php tools/run-notification-worker.php
```

The worker generates idempotent due-soon and overdue records, then prepares email or digest delivery rows. It does not send email itself; a later transport worker will claim queued deliveries.

## Operational boundaries

- Database uniqueness prevents duplicate due notifications and duplicate channel deliveries.
- Disabling due alerts prevents future due reminders.
- Email is opt-in and defaults off.
- Quiet hours are persisted with the user's timezone for transport-layer enforcement.
- Daily digest delivery is prepared for 08:00 on the next day.
- Assignment and mention creation remain immediate in-app events.

Full PostgreSQL and scheduler execution requires CI or a local runtime.