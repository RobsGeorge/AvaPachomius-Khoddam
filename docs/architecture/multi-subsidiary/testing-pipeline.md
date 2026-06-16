# Testing Pipeline Prompt (Cursor)

A single, strict prompt to build CI for this programme. **Today there is no test gate** — the only
workflow is `.github/workflows/deploy.yml`, which deploys on every push to `main`. This adds a CI
workflow that runs the tests and **gates deploy on green CI**.

---

## PROMPT — create the testing pipeline

```
Create a CI testing pipeline as a new GitHub Actions workflow .github/workflows/ci.yml WITHOUT
breaking the existing deploy.yml. Follow the repo's stack: Laravel 10, PHP 8.2, MySQL, PHPUnit,
Laravel Dusk (e2e), k6 (load). Read docs/architecture/multi-subsidiary/testing-prompts.md for what
each suite covers.

REQUIREMENTS

1) Triggers: on pull_request (all branches) and on push to feature branches. Concurrency group per
   ref with cancel-in-progress.

2) Job `tests` (unit + feature) — the merge gate:
   - ubuntu-latest; services: mysql:8 (health-checked), DB name e.g. avapakhomios_test.
   - shivammathur/setup-php@v2 with php-version 8.2 and extensions: pdo_mysql, mbstring, xml, curl,
     zip, gd, bcmath.
   - composer install (with dev deps); copy .env.testing (APP_ENV=testing, the test MySQL creds,
     SESSION_DRIVER=array, CACHE_DRIVER=array, QUEUE_CONNECTION=sync, MAIL_MAILER=array,
     plus TENANCY_MAIN_SLUG=academy, TENANCY_CONSOLE_HOST=admin.inst.test); php artisan key:generate.
   - php artisan migrate --force  (exercises LegacySchemaSync on a clean DB).
   - php artisan test --testsuite=Unit,Feature  with coverage; fail the job on any failure and on
     new-code line coverage < 80% (use a coverage check; xdebug or pcov).
   - Upload the coverage report + any docs/.../test-reports/*.md as artifacts.

3) Job `e2e` (Dusk) — needs: tests:
   - Boot the app: php artisan serve (or nginx) + the seeded multi-subsidiary dataset; configure
     hosts academy.inst.test / service.inst.test / admin.inst.test → 127.0.0.1 in the runner
     (/etc/hosts) so subdomain resolution + SSO scenarios run.
   - Run php artisan dusk; on failure upload tests/Browser/screenshots + console logs as artifacts.

4) Job `load` (k6) — manual + nightly only (workflow_dispatch + schedule cron), NOT on every PR:
   - Seed the realistic dataset seeder; run tests/Load/*.js with k6; enforce the thresholds from
     testing-prompts.md (p95<500ms, error<1%, no cross-tenant leakage). Publish the k6 summary as an
     artifact. Failing thresholds fail the job.

5) Gate deploy on CI:
   - Modify deploy.yml so production deploy only runs after CI passes on main. Prefer:
     `on: workflow_run: { workflows: ["CI"], types: [completed], branches: [main] }` and a guard
     `if: github.event.workflow_run.conclusion == 'success'`. Keep the existing SSH deploy steps
     unchanged. Do NOT deploy when CI is red.

6) Add a .env.testing.example and a database/seeders/LoadTestSeeder.php (realistic volumes:
   ~10k users, ~50 subsidiaries, ~100k attendance rows) used only by the load/e2e jobs.

CONSTRAINTS
- Dev/test tooling (laravel/dusk, k6 setup) is allowed but must be dev-only; do not add runtime deps.
- Keep secrets out of the repo; the test MySQL is an ephemeral service container, not the VPS.
- The pipeline must be green on the current codebase BEFORE P0 (baseline), so add it early and let it
  grow as each phase adds tests.

DELIVERABLES: .github/workflows/ci.yml, modified deploy.yml (gated), .env.testing.example,
LoadTestSeeder, a tests/DuskTestCase.php + tests/Browser/ scaffold, tests/Load/ scaffold, and a
short docs/architecture/multi-subsidiary/test-reports/README.md explaining how to run each suite
locally (php artisan test, php artisan dusk, k6 run tests/Load/p4-concurrency.js).
ACCEPTANCE: open a draft PR; CI runs; tests + e2e jobs green on baseline; load job runnable via
workflow_dispatch; deploy.yml no longer fires on a red main.
```

## Notes for the human

- **Branch protection:** after `ci.yml` exists, enable "Require status checks to pass" on `main` for
  the `tests` (and ideally `e2e`) jobs so PRs can't merge red. CI-as-gate in `deploy.yml` stops a bad
  deploy; branch protection stops a bad merge — do both.
- **Coverage tooling:** the runner needs `pcov` or `xdebug` for coverage; `setup-php` has a
  `coverage:` input.
- **Load job cost:** keep it off per-PR (nightly + manual) to control runner minutes; run it for real
  before P4 sign-off and GA (P6).
