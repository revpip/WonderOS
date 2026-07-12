# Security Policy

## Reporting a vulnerability

Please do not disclose security vulnerabilities in a public issue.

Until a dedicated security address is published, contact the repository owner privately through GitHub and include:

- the affected component;
- reproduction steps;
- likely impact;
- any suggested mitigation;
- whether the issue is already public.

We will acknowledge a credible report, assess severity and coordinate remediation before public disclosure.

## Security principles

WonderOS treats security as part of knowledge integrity.

- Secrets must never be committed.
- API credentials must come from environment or secret-management facilities.
- Canonical mutations require authentication and audit records.
- AI-generated claims must remain unverified by default.
- Publishing adapters must default to non-public states.
- Destructive actions should be recoverable wherever practical.
- Dependencies and containers should be pinned and routinely reviewed.

## Supported versions

WonderOS is pre-release software. Security fixes will be applied to the active Genesis branch and the latest tagged release once releases begin.
