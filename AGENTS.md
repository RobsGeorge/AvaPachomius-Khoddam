# AGENTS.md

For product rules, tenancy/expand-contract constraints, and coding guidelines see `CLAUDE.md`,
`.cursorrules`, and `docs/khedma-master-plan.md`. Standard dev commands live in `README.md`.

## Cursor Cloud specific instructions

Single Laravel 10 app (PHP 8.2, MySQL 8, no frontend build step). The startup update script
runs `composer install`; everything below is not covered by it.

- PHP CLI is pinned to 8.2 (`php -v` → 8.2.x). Keep it on 8.2; do not switch the default `php`.
- MySQL is not auto-started. Start it each session with `sudo service mysql start`. The dev DB is
  `khedma`, user `root` / password `root`. Connect over TCP (`mysql -uroot -proot -h127.0.0.1`);
  a socket connection as the `ubuntu` user fails with a permission error, which is expected.
- Local `.env` uses `DB_DATABASE=khedma`, `DB_PASSWORD=root`, plus `PULSE_ENABLED=false` and
  `TELESCOPE_ENABLED=false` (the console bootstrap otherwise tries to load Pulse/Telescope).
  After a fresh DB, run `php artisan migrate` (many migrations also seed data, e.g. Tenant Zero
  church) then `php artisan permissions:sync` for RBAC.
- Run the app in dev with `php artisan serve --host=0.0.0.0 --port=8000`.
- Tests use SQLite in-memory (forced by `phpunit.xml`) — no MySQL needed. Run `php artisan test`
  or a single suite, e.g. `php artisan test --testsuite=Tenancy`. Suites: Unit, Feature, Smoke,
  Api, Notifications, Mail, Rbac, Tenancy, Load (see `.github/workflows/ci.yml` for the gate order).
- Lint: `./vendor/bin/pint --test`. It reports many pre-existing style deltas and is not part of
  the CI gate; only judge new files against it.
- There is no seeded login user (`DatabaseSeeder` is empty) and registration is OTP/email based.
  To get a login-capable superadmin for manual testing, create a user via `php artisan tinker`
  with `is_verified=true`, `registration_completed=true`,
  `application_status=User::APPLICATION_STATUS_APPROVED`, a known hashed `password`, and
  `is_superadmin=true` (mirror `database/factories/UserFactory.php`). Then log in at `/login` and
  the superadmin console is at `/superadmin`.
