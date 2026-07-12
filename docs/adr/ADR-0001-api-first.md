# ADR-0001: WonderOS is API-first

- **Status:** Accepted
- **Date:** 2026-07-12

## Context

WonderOS must serve WordPress initially while remaining capable of supporting future web, mobile, print, audio and partner applications.

## Decision

Canonical knowledge and editorial workflows are accessed through versioned application and API contracts. WordPress is a client and publishing adapter, not the system of record.

## Consequences

- Canonical records use Wonder IDs rather than WordPress IDs.
- Publishing integrations must be replaceable.
- User interfaces cannot depend directly on database tables.
- API compatibility and migration discipline are product concerns.
