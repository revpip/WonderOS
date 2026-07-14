# Unified delivery policy

Genesis-029 gives transactional and digest email one preflight boundary.

Before transport, both channels must honour the platform reputation pause and active recipient suppressions. A blocked delivery is marked `suppressed`; the provider is not contacted.

## Soft bounce escalation

The governed settings now include `soft_bounce_limit` and `soft_bounce_window_days`. Run `SELECT wonder_escalate_soft_bounces();` from the scheduled email-health job after provider events are ingested. Reaching the configured limit creates an active suppression with reason `soft_bounce_escalation`.

Defaults are three soft bounces within fourteen days.

## Provider adapters

Webhook suppliers implement `ProviderWebhookAdapter` and return one internal event shape: event ID, event type, provider message ID, recipient email, occurrence time, optional bounce classification and original payload. `GenericJsonWebhookAdapter` defines the reference JSON contract.

Provider-specific signature verification remains at the HTTP edge; normalisation happens only after authenticity and replay checks succeed.
