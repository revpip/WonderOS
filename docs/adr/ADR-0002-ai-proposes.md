# ADR-0002: AI cannot approve canonical knowledge

- **Status:** Accepted
- **Date:** 2026-07-12

## Context

Generative models can accelerate research, classification and drafting, but their output may contain errors, unsupported claims or inappropriate certainty.

## Decision

AI-created claims, relationships and editorial assets enter WonderOS as proposals. An authorised human review is required before canonical verification or public publication.

## Consequences

- AI jobs retain provider, model and prompt-version provenance.
- New claims default to an unverified state.
- Publishing workflows require explicit approval gates.
- Provider changes cannot alter the underlying trust model.
