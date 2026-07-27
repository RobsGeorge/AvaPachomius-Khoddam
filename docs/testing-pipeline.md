# Testing Pipeline & In-Portal Report

Companion to [testing-plan.md](testing-plan.md). This documents the **categorized test
pipelines**, the **CI deploy gate**, and the **in-portal testing report** that were
built to run and surface the automated tests.

## 1. Categorized pipelines

Tests are grouped into independently-runnable pipelines, defined as named
`<testsuite>`s in `phpunit.xml`:

| Pipeline | Path | What it covers |
|---|---|---|
| `Unit` | `tests/Unit` | Pure service/unit logic |
| `Feature` | `tests/Feature` (excl. categorized subfolders) | Module feature tests |
| `Smoke` | `tests/Feature/Smoke` | UI/endpoint coverage: every route resolves; every parameterless GET renders without a 5xx; guests are safely rejected |
| `Api` | `tests/Feature/Api` | JSON API surface; every API route is auth-guarded |
| `Notifications` | `tests/Feature/Notifications` | Notifications sent to the right recipient and received/read in the portal feed |
| `Mail` | `tests/Feature/Mail` | Mailables + Blade rendering (ar/en) and external comms (WhatsApp Cloud API, faked) |
| `Tenancy` | `tests/Feature/Tenancy` | Tenant-isolation readiness + CLAUDE.md invariant guards (rules 4, 6) |
| `Load` | `tests/Load` | Load/perf sketches |

Run any pipeline in isolation:

```bash
php artisan test --testsuite=Smoke
php artisan test --testsuite=Notifications
```

### Local runner note (dev box)

On a dev box whose `.env` enables Pulse/Telescope, the console bootstrap of
`php artisan test` can hang. Run with the testing env forced:

```bash
APP_ENV=testing PULSE_ENABLED=false TELESCOPE_ENABLED=false \
DB_CONNECTION=sqlite DB_DATABASE=":memory:" \
CACHE_DRIVER=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
php artisan test --testsuite=Smoke
```

CI adds `PULSE_ENABLED=false` / `TELESCOPE_ENABLED=false` to `.env` for the same reason.

## 2. CI deploy gate

`.github/workflows/ci.yml` has two jobs:

- **`gate`** (blocks deploy): runs the pipelines **in sequence** — Unit(events) →
  Feature(events) → Smoke → Api → Notifications → Mail → Tenancy → Load(events).
  Any failure fails the gate. `deploy.yml` (`needs: test`) only deploys when this passes.
- **`full-suite-report`** (non-blocking): runs the full `Unit` and `Feature` suites for
  visibility. Marked `continue-on-error` so pre-existing failures outside the gated
  categories don't block deploys while they're worked through (see §4).

## 3. In-portal testing report

SuperAdmin → **System testing report** (`/superadmin/system-tests`).

- Backend: `App\Services\SystemTestRunner` shells out to `php artisan test
  --testsuite=<Name>` per pipeline and records each run in `system_test_runs`.
  **Safety:** the subprocess is forced onto in-memory sqlite with Pulse/Telescope off,
  so running the suite from production php-fpm can never touch the live MySQL database.
- UI: run any single pipeline or "Run all (in sequence)"; a status board shows the
  latest result per pipeline; history rows link to full captured output.
- Controller `SuperAdminSystemTestController`; views under
  `resources/views/superadmin/system-tests/`; strings in `lang/{en,ar}/systemtests.php`.
- Migration: `…_create_system_test_runs_table.php` (additive; runs on deploy via
  `migrate:deploy`).
- If `SYSTEM_TEST_PHP_BINARY` is set, the runner uses it as the PHP CLI (useful when
  php-fpm's `PHP_BINARY` is not a usable CLI binary).

## 4. Pre-existing failures (handoff)

Running the **full** Unit + Feature suites surfaces failures that predate this work and
sit **outside** the gated categories. They are intentionally not in the blocking gate.
Full-suite result at time of writing: **210 passed, 1 skipped, 14 failed** (736s).
Of the 14, **13 pre-date this work** and live in files this change never touched. They
fall into these buckets:

| Test | Cause (pre-existing) |
|---|---|
| `Auth\AuthenticationTest` (×3), `Auth\EmailVerificationTest` (×2), `Auth\PasswordResetTest` (×2) | `QueryException: table user has no column named name` — the tests insert a `name` column, but the `user` table uses `first_name/second_name/third_name`. Stale tests against an old schema. |
| `ExampleTest > returns a successful response` | Default Laravel example asserts `/` returns 200; `/` now requires auth and returns 302. |
| `LoginPageTest > login page loads for guests` | View/markup assertion drift. |
| `RegistrationTest > new users can register` | `false is true` — registration flow/test drift. |
| `AnnouncementModuleTest > student … hard blocked after grace period` | `false is true` — photo-gate timing assertion. |
| `CourseApplicationReviewTest > admin can build form and enable it` | `false is true` — form-enable assertion. |
| `ProfilePhotoAdminTest > grace days setting changes deadline` | String assertion drift. |

These are **not** gated (they sit outside the categorized pipelines) and were **not
introduced by this change** — every new pipeline passes in isolation, and the 27
navigation/roles-hub tests still pass. Recommended follow-up: fix the stale-schema Auth
tests (quick) and re-point the drift assertions; then they can graduate into the gate.

The 14th failure was in the new `Smoke` pipeline (`superadmin/security` returning 500
only under full-suite ordering — cross-test state pollution) and is addressed directly
in the Smoke test.
