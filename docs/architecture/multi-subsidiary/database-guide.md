# Database Update Guide

Exact, ordered database changes for the whole programme. **Treat this as the source of truth for
every migration.** Read alongside each P-phase spec.

## Positioning

The current live platform becomes **the first subsidiary** under a larger multi-disciplinary
umbrella. All existing rows are backfilled into that one subsidiary. Set its slug/name to the real
current institution — do **not** leave it as the generic literal "main":

```dotenv
TENANCY_MAIN_SLUG=academy            # the current platform's real slug
TENANCY_MAIN_NAME="<current institution name>"
TENANCY_CONSOLE_HOST=admin.<domain>  # umbrella superadmin console
```

`TENANCY_MAIN_SLUG` only names *which subsidiary the existing data belongs to*; the umbrella itself
is the superadmin/console layer, not a row.

## Environment facts (do not fight these)

- Laravel **10**, MySQL, PHP **8.2** on the VPS. Deploys run `php8.2 artisan migrate:deploy --force`.
- **Brownfield schema** with non-standard PKs (`user_id`, `course_id`, `role_id`, …) and the
  `SafeMySqlConnection` / `LegacySchemaSync` machinery. `user.$timestamps = false`.
- Migrations run on a **live VPS DB that already has data and partial columns**.

## Golden rules for every migration here

1. **New tables** → `App\Database\SchemaGuards::createTableIfMissing()` + `id('x_id')` PK convention.
2. **Columns on existing tables** → `App\Database\MigrationSupport::addColumn()/addStringColumn()/…`
   (no-ops if missing table or existing column). **Never** raw `Schema::table(...->add)` unguarded.
3. **Type changes / NOT NULL** → raw `DB::statement('ALTER TABLE … MODIFY …')` guarded by
   `Schema::hasColumn` + driver check (`=== 'mysql'`), and only **after** backfill is verified.
4. **Never** drop/rename a legacy column or destroy data in `up()`. `down()` may drop only what this
   programme added.
5. Every migration must be **safe to re-run** (idempotent) — deploys re-run pending migrations.
6. Index `subsidiary_id` on every tenant table (it joins every query from P1 on).

## Full column / table inventory

### P0 — new tables + scoping column
| Object | Type | Notes |
|---|---|---|
| `subsidiary` | **new table** | `subsidiary_id` PK, `slug` unique, `name`, `domain` nullable, `status` default `active`, `settings` json nullable, timestamps |
| `subsidiary_user` | **new table** | PK, `subsidiary_id` FK, `user_id` FK, `status` default `active`, `joined_at` nullable, UNIQUE(`subsidiary_id`,`user_id`) |
| `subsidiary_id` | **new col, nullable + index** | on: `course, modules, content, assignments, session, exams, assessment, course_assessment, attendance, grade_categories, activity_logs` |

Backfill (P0 File 4): create the first subsidiary; `UPDATE … SET subsidiary_id = <id> WHERE subsidiary_id IS NULL` on every tenant table; one `subsidiary_user` row per existing user.

### P1 — enforcement
| Change | Statement |
|---|---|
| `subsidiary_id` → NOT NULL | `ALTER TABLE <t> MODIFY subsidiary_id BIGINT UNSIGNED NOT NULL` per tenant table, **after** verifying zero NULLs |
| FK (optional) | add only if zero orphans; guard for legacy data |

### P2 — capabilities
| Object | Type |
|---|---|
| `subsidiary_capability` | **new table**: PK, `subsidiary_id` FK, `capability_key` (40), `enabled` bool default true, `config` json nullable, UNIQUE(`subsidiary_id`,`capability_key`) |

Seed: enable every capability for the first subsidiary (preserve current behaviour).

### P3 — roles & permissions
| Object | Change |
|---|---|
| `roles` | + `subsidiary_id` (nullable, index), + `slug` (40, NOT NULL), + `is_system` (bool). Backfill existing roles → first subsidiary, `slug` from `role_name`. Then UNIQUE(`subsidiary_id`,`slug`) |
| `permissions` | **new table**: PK, `key` (60) unique, `capability_key` (40) nullable, `description` (191) nullable |
| `role_permission` | **new table**: PK, `role_id` FK, `permission_id` FK, UNIQUE(`role_id`,`permission_id`) |
| `user_course_role` | + `subsidiary_id` (nullable→NOT NULL after backfill, index); **`course_id` → NULLABLE** (`MODIFY course_id BIGINT UNSIGNED NULL`); backfill `subsidiary_id` from grant's `course.subsidiary_id`, fallback first subsidiary |
| `subsidiary` | + `permissions_version` (unsigned int, default 1) — cache-bust stamp |

Seed: upsert all `config('permissions')` keys; create platform template roles (`subsidiary_id=NULL`) + their default permission sets.

### P4 — sessions
| Object | Type |
|---|---|
| `sessions` | **new table** via `php artisan session:table` then guard it idempotently; needed when `SESSION_DRIVER=database`. PK conventions differ — create with the project's guard pattern, verify it migrates on the legacy DB |

## Data-integrity precondition — email uniqueness

The shared-pool identity (a person = one `user` row, member of many subsidiaries) **depends on
`user.email` being globally unique.** The legacy `user` table may not enforce this. Before P3,
check for and resolve duplicates, then add the constraint (guarded, idempotent):

```sql
SELECT email, COUNT(*) c FROM `user` GROUP BY email HAVING c > 1;   -- must be empty
```
```php
// migration (mysql, guarded): only add if no duplicates and index missing
DB::statement('ALTER TABLE `user` ADD UNIQUE `user_email_unique` (`email`)');
```
If duplicates exist, reconcile them first (they represent the *same person* under the shared-pool
model). Do this in a quiet window — it is a unique-index build.

## Backfill verification (run after P0, before P1 NOT NULL)

```sql
-- must all return 0
SELECT COUNT(*) FROM course           WHERE subsidiary_id IS NULL;
SELECT COUNT(*) FROM modules          WHERE subsidiary_id IS NULL;
SELECT COUNT(*) FROM attendance       WHERE subsidiary_id IS NULL;
-- … repeat for every tenant table …

-- every user has exactly one membership in the first subsidiary
SELECT COUNT(*) FROM `user` u
LEFT JOIN subsidiary_user su ON su.user_id = u.user_id
WHERE su.subsidiary_user_id IS NULL;     -- must be 0
```

After P3 backfill:
```sql
SELECT COUNT(*) FROM user_course_role WHERE subsidiary_id IS NULL;   -- must be 0
SELECT COUNT(*) FROM roles            WHERE subsidiary_id IS NULL AND is_system = 0; -- only templates may be NULL
```

## Running migrations

```bash
# local
php artisan migrate

# VPS (matches the deploy workflow; brief maintenance for heavy changes)
cd /var/www/avapakhomios
php8.2 artisan down --retry=60
php8.2 artisan migrate:deploy --force
php8.2 artisan up
```

> P1's NOT NULL and P3's `course_id` MODIFY are table-locking — run them in a quiet window
> (the deploy doc already enables maintenance mode). See [server-walkthrough.md](server-walkthrough.md).

## Rollback

- New tables: `migrate:rollback` drops them.
- Added columns: `down()` drops only programme-added columns.
- **Backfill data migrations are non-destructive** (`down()` is a no-op) — they leave rows stamped.
- NOT NULL / `course_id` nullable changes: provide an inverse `MODIFY` in `down()` guarded by driver.

## Index checklist (must exist after P1/P3)

`<tenant_table>.subsidiary_id` (all), `subsidiary.slug` (unique), `subsidiary.domain`,
`subsidiary_user(subsidiary_id,user_id)` unique, `subsidiary_capability(subsidiary_id,capability_key)`
unique, `roles(subsidiary_id,slug)` unique, `permissions.key` unique,
`role_permission(role_id,permission_id)` unique, `user_course_role.subsidiary_id`.
