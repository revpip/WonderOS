# Resend production deployment

WonderOS uses Resend for transactional and digest email.

## Runtime configuration

Set these secrets outside Git:

- `EMAIL_PROVIDER=resend`
- `RESEND_API_KEY=re_...`
- `RESEND_WEBHOOK_SECRET=whsec_...`
- `EMAIL_FROM_ADDRESS=notifications@your-domain.example`
- `EMAIL_FROM_NAME=WonderOS`

The send endpoint is `https://api.resend.com/emails`. Webhooks are accepted at `POST /v1/email/webhooks/provider` and must include the Resend/Svix headers `svix-id`, `svix-timestamp` and `svix-signature`.

## Domain authentication

1. Add the sending domain in Resend.
2. Publish every DNS record Resend provides for domain verification and DKIM.
3. Confirm the visible From domain aligns with the authenticated sending domain.
4. Ensure the domain has one valid SPF policy; merge authorised senders rather than publishing multiple SPF TXT records.
5. Publish DMARC at `_dmarc.<domain>` initially with reporting (`p=none`) and an aggregate-report mailbox.
6. Review reports, correct alignment failures, then progress deliberately to `p=quarantine` and ultimately `p=reject`.
7. Keep the notification subdomain separate from human mailbox traffic when practical.

## Webhook configuration

Subscribe to delivery, delayed, bounced, failed, complaint, opened and clicked events. Copy the endpoint-specific signing secret into `RESEND_WEBHOOK_SECRET`. Do not parse or re-encode the request body before verification.

## Release checks

- send a message to Resend's successful-delivery test recipient;
- confirm the returned email ID is stored as the provider message ID;
- replay a fixture with an invalid signature and confirm HTTP 401;
- confirm a valid delivered event updates the matching delivery;
- confirm hard bounce, failure and complaint events create suppression;
- confirm a soft bounce is recorded without immediate suppression;
- confirm a repeated event ID is accepted as a duplicate without side effects;
- confirm transactional and digest workers stop for suppressed recipients and platform pauses;
- verify SPF, DKIM and DMARC alignment in received-message headers;
- keep reputation thresholds conservative until real traffic establishes a baseline.

## Rollback

Pause outbound email through the Email Reputation workspace, revoke the Resend API key, remove the webhook endpoint in Resend, and retain provider events and suppression history for audit purposes.
