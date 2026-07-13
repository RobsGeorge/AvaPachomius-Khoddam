# Product catalog: personas, use cases, test cases, and gaps

This directory is the persona-oriented source of truth for **who can do what** in the platform,
how each path is expected to behave, how those paths are covered by tests, and what is still
missing to serve every kind of user well.

## Contents

| File | What it is |
|---|---|
| [personas.md](personas.md) | The user types (guest → superadmin + accessibility personas), their entry points and permission profiles |
| [use-cases/](use-cases/) | Per-module use-case catalog (`UC-<MODULE>-<n>`): main path, alternate/error paths, authorization boundary |
| [test-cases/test-case-catalog.md](test-cases/test-case-catalog.md) | `TC-<MODULE>-<n>` → UC mapping, given/when/then, type, **automation status**, priority |
| [feature-gap-analysis.md](feature-gap-analysis.md) | Prioritized functional backlog to make the system useful to all personas (not implemented) |
| [accessibility-audit.md](accessibility-audit.md) | WCAG 2.1 AA audit + ranked fixes (RTL, keyboard, screen-reader, contrast, mobile) |

## How the pieces relate

```
persona  ──uses──▶  use case (UC-*)  ──verified by──▶  test case (TC-*)  ──automated in──▶  tests/Feature/UseCases/*
                         │
                         └─ gaps found while cataloguing ─▶ feature-gap-analysis.md / accessibility-audit.md
```

- Every **use case** names the persona(s) who can perform it and the persona(s) who are refused.
- Every use case has **at least one test case**. A test case is either automated (names its
  `file::method`), planned (`🔲`), or manual (`📋`).
- The **executable subset** lives in `tests/Feature/UseCases/` and runs in the `Feature`
  pipeline (`php artisan test --testsuite=Feature`) and the SuperAdmin in-portal test report.

## Conventions

- **IDs are stable.** Renumbering breaks traceability; append rather than renumber.
- **Authorization is by permission key, never role name** (CLAUDE.md rule 4). Use cases state the
  required permission(s); tests grant them via `courseRoleWithPermissions()` / system roles.
- **Localization:** every user-facing string is ar + en, RTL-first. Use cases note any locale-specific
  behavior; the ar/en parity guard already lives in `tests/Feature/Tenancy/TenantIsolationTest.php`.

## Running the executable coverage

```bash
# Dev box: force the testing env (the local .env boots Pulse/Telescope and hangs otherwise)
APP_ENV=testing PULSE_ENABLED=false TELESCOPE_ENABLED=false \
DB_CONNECTION=sqlite DB_DATABASE=":memory:" \
CACHE_DRIVER=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
php artisan test --testsuite=Feature
```
