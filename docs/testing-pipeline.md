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

### Local / staging runner note

`phpunit.xml` sets `force="true"` on `APP_ENV`, `APP_URL`, `DB_*`, `MULTI_TENANT`,
and related drivers so a staging/prod shell (or cached `.env` exports) cannot leak
TrustHosts / live MySQL / `MULTI_TENANT=true` into the suite.

On a box whose `.env` enables Pulse/Telescope, you can still run:

```bash
php vendor/bin/phpunit --testsuite=Smoke
# or
php artisan test --testsuite=Smoke
```

CI and `SystemTestRunner` also force the same safe env onto the subprocess.

## 2. CI deploy gate

`.github/workflows/ci.yml` runs on pull requests, and again as the test job of a
production or staging deploy. It has two jobs, in parallel:

- **`gate`** (blocks deploy): Unit(events) → Feature(events) → Smoke → Api →
  Notifications → Mail → Tenancy → Load(events). Any failure fails the gate.
  `deploy.yml` (`needs: test`) only deploys when this passes. Tenancy runs once.
  `tests/CreatesApplication.php` forces `MULTI_TENANT=false`, and the suite covers
  both flag values by setting `tenancy.enabled` inside the tests.
- **`mysql-migrate-fresh`**: a clean MySQL `migrate:fresh --seed`, so a migration
  that only works on sqlite cannot ship.

The broad Unit and Feature suites are not part of this gate. They have known
failures outside these categories (see §4) and run weekly, or on demand, from
`.github/workflows/ci-full-report.yml`. A push to `main` or `staging` does not
start a second copy of this workflow; the deploy workflow calls it.

Deploy safety (production `/var/www/avapakhomios` and staging
`/var/www/khedma-staging` on the avapakhomios VPS):

- A second push does not cancel a deploy that is already running. Cancelling
  after `artisan down` would leave the site offline.
- The remote script brings the app back up if a later step fails, and the
  script's exit status stays non-zero so the GitHub job is red.
- `scripts/ci/ssh-deploy.sh` opens the SSH session. It retries a connection
  failure (4 attempts, 45s apart) and does not retry a failure inside the
  remote script. The private key stays in the process environment and a
  mode-600 temp file. The previous retry action parsed that key as YAML and
  failed before connecting, and `appleboy/ssh-action@v1.0.3` has no
  `script_path` input.
- These workflows do not deploy deaconia.com. That host is a separate checkout
  and is not using these SSH secrets.

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
