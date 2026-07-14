# Production readiness and CI

WonderOS runs `.github/workflows/ci.yml` for pull requests and pushes to `main`.

## Required checks

The `verify` job provisions PostgreSQL 16 and then:

1. validates `composer.json`;
2. installs locked dependencies on PHP 8.3;
3. lints PHP across applications, modules, tests and tools;
4. applies every `*.up.sql` migration in lexical order;
5. rolls back and reapplies the latest migration;
6. runs the complete PHPUnit suite;
7. discovers and validates every OpenAPI document with Redocly;
8. syntax-checks all notification worker entry points.

A pull request should not be merged until this job succeeds.

## Resend smoke test

The `resend-smoke` job is intentionally limited to manual `workflow_dispatch` runs and the protected `production-email-smoke` GitHub Environment.

Configure these repository or environment secrets:

- `RESEND_API_KEY`
- `EMAIL_FROM_ADDRESS`
- `RESEND_SMOKE_TO`

The smoke command sends one clearly labelled message and fails if Resend does not return an email ID. Keep environment approval enabled so a live send cannot be triggered casually.

## Branch protection

Protect the production branch and require:

- WonderOS CI / verify;
- at least one approving review;
- dismissal of stale approvals after new commits;
- resolution of all review conversations;
- linear history or squash merges;
- no force pushes or branch deletion.

## Migration discipline

Every forward migration must have a matching rollback file using the same prefix:

```text
0018_example.up.sql
0018_example.down.sql
```

The CI rollback check covers the newest migration. Earlier migrations remain exercised by applying the complete chain to an empty PostgreSQL database.

## Release gate

Before production deployment:

- confirm CI is green on the exact release commit;
- run the Resend smoke workflow with approved secrets;
- verify the message appears in Resend and reaches the controlled mailbox;
- confirm webhook delivery is accepted and visible in Email Health;
- confirm suppression and reputation controls remain active;
- retain the previous application revision for rollback.
