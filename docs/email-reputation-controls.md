# Email reputation controls

Genesis-028 prevents outbound email to actively suppressed recipients and pauses the platform when recent provider events exceed governed safety thresholds.

## Administration

`GET /v1/admin/email-reputation`

`PUT /v1/admin/email-reputation`

`POST /v1/admin/email-suppressions/{uuid}/release`

All routes require Administrator permission. Releasing a suppression requires a written reason and creates an immutable `email.suppression_released` audit event. Updating thresholds or manually pausing delivery creates `email.reputation_updated`.

## Worker behaviour

Before claiming queued email, `tools/run-notification-delivery-worker.php`:

1. calculates thirty-day bounce and complaint rates;
2. automatically pauses delivery when a configured threshold is reached after the minimum sample size;
3. stops immediately while the global status is paused;
4. checks every recipient against active suppressions;
5. marks blocked deliveries `suppressed` without contacting the provider.

## Defaults

- Bounce-rate threshold: 5%
- Complaint-rate threshold: 0.1%
- Minimum sample size: 100 events

These are operational defaults, not universal legal or provider guarantees. Administrators should tune them to the selected provider and sending programme.

## Console

Open `http://localhost:8081/email-reputation.html` after signing in as an Administrator.

## Bounce classification

`wonder_email_events.bounce_class` supports `soft` and `hard`. Provider adapters should classify temporary failures as soft and permanent address failures as hard. Only hard bounce, blocked and complaint events should create active suppressions.
