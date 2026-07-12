# Contributing to WonderOS

Thank you for helping build software for people who create, connect and steward knowledge.

## Before contributing

1. Read `docs/CONSTITUTION.md` and `ARCHITECTURE.md`.
2. Open or select an issue before beginning substantial work.
3. Keep changes small, focused and independently testable.
4. Never place canonical knowledge or business logic inside a publishing adapter.

## Branches and commits

Use branches such as `feature/entity-create`, `fix/claim-validation` or `docs/api-contracts`.

Commit messages should be concise and imperative, for example:

- `Introduce canonical entity aggregate`
- `Validate relationship context`
- `Document WordPress projection boundary`

## Pull requests

Every pull request must explain:

- what changed;
- why it changed;
- user or developer impact;
- validation performed;
- documentation or architectural decisions affected.

## Definition of done

Work is complete only when:

- acceptance criteria are met;
- tests cover the behaviour;
- error handling is clear;
- documentation is updated;
- provenance and audit requirements are preserved;
- no unrelated changes are included.

## Engineering principles

- Prefer clarity over cleverness.
- Keep controllers thin and domain logic explicit.
- Use typed interfaces at module boundaries.
- Make state transitions deliberate and testable.
- AI output is always a proposal until an authorised review accepts it.
- WordPress is a publishing client, never WonderOS's source of truth.
