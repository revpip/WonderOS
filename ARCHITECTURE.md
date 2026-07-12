# WonderOS Architecture

## Purpose

WonderOS is an API-first editorial operating system. It owns canonical knowledge and reusable editorial assets; publishing systems consume projections of that material.

## Genesis modules

```text
Core
Knowledge
Editorial
AI
Publishing
Console
```

### Core

Owns identity, configuration, audit, permissions, versioning and shared application contracts.

### Knowledge

Owns entities, aliases, relationships, claims, sources and evidence. It does not own articles or publishing state.

### Editorial

Owns ideas, commissions, briefs, reviews, assets and workflow transitions. It consumes approved knowledge without redefining it.

### AI

Owns provider adapters, prompt versions, job execution and evaluation records. It can propose output but cannot verify claims or publish independently.

### Publishing

Owns projections and channel adapters. WordPress is the first adapter. Publishing defaults to draft and must be idempotent.

### Console

Owns the editor-facing workspace. It communicates through application services and APIs; it does not query storage directly.

## Non-negotiable boundaries

1. WordPress IDs and URLs are never canonical identity.
2. AI provider types do not leak into Knowledge or Editorial domain objects.
3. Controllers contain transport logic, not business rules.
4. Claims, sources and evidence remain distinct records.
5. Every important mutation creates an audit event.
6. Publishing projections can be rebuilt without duplicating canonical knowledge.
7. Modules may depend on Core contracts, but circular module dependencies are prohibited.

## First vertical slice

The Genesis proof uses the Barn Owl:

```text
Entity
  → relationships
  → claims
  → sources and evidence
  → editorial commission
  → reviewable asset
  → WordPress draft projection
```

The architecture is proven only when this path works end to end with tests and a usable Console.

## Initial repository shape

```text
apps/
  api/
  console/
  wordpress/
modules/
  core/
  knowledge/
  editorial/
  ai/
  publishing/
  shared/
database/
docs/
tests/
docker/
.github/
```

A modular monolith is intentional for Genesis. Modules may be extracted only when operational evidence justifies distributed services.
