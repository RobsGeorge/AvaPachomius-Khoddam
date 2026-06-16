# P0 — Foundation: tenant tables + backfill

**Goal:** create the tenancy data structures and backfill all existing data into a default
"main" subsidiary. **Zero behavior change** — after P0 the app behaves byte-for-byte as before;
nothing reads `subsidiary_id` yet.

**In scope:** `subsidiary` + `subsidiary_user` tables, nullable `subsidiary_id` on data-root
tables, models, idempotent backfill.
**Out of scope (deferred):** global scope, resolution middleware, `NOT NULL`/FK enforcement (P1);
`roles`/`user_course_role`/permissions (P3). **P0 does not touch auth.**

---

## File 1 — `config/tenancy.php`

```php
<?php

return [
    // Slug of the default subsidiary all pre-existing data belongs to.
    'main_slug' => env('TENANCY_MAIN_SLUG', 'main'),

    // Host that serves the cross-tenant superadmin console (no tenant binding). Used from P4.
    'console_host' => env('TENANCY_CONSOLE_HOST', 'admin.localhost'),

    // Data-root tables that carry subsidiary_id and (from P1) the global scope.
    // Auth tables (roles, user_course_role) are intentionally excluded until P3.
    'tenant_tables' => [
        'course', 'modules', 'content', 'assignments',
        'session', 'exams', 'assessment', 'course_assessment',
        'attendance', 'grade_categories', 'activity_logs',
    ],
];
```

The migrations, the future global scope, and the backfill all read this list — the tenant
boundary is defined in one place.

## File 2 — migration `2026_06_17_000001_create_subsidiary_tables.php`

New tables → idempotent create with the project's `id('x_id')` PK convention.

```php
<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('subsidiary', function (Blueprint $table) {
            $table->id('subsidiary_id');
            $table->string('slug', 50)->unique();
            $table->string('name', 120);
            $table->string('domain', 191)->nullable();        // custom-domain override (P4)
            $table->string('status', 20)->default('active');   // active|suspended|archived
            $table->json('settings')->nullable();              // branding/theme/locale/capability cfg
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('subsidiary_user', function (Blueprint $table) {
            $table->id('subsidiary_user_id');
            $table->foreignId('subsidiary_id')->constrained('subsidiary', 'subsidiary_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->string('status', 20)->default('active');   // active|invited|suspended
            $table->timestamp('joined_at')->nullable();
            $table->unique(['subsidiary_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidiary_user');
        Schema::dropIfExists('subsidiary');
    }
};
```

## File 3 — migration `2026_06_17_000002_add_subsidiary_id_to_tenant_tables.php`

Nullable, indexed `subsidiary_id` on each data root. Nullable + no hard FK on purpose — backfill
runs in File 4, then **P1** flips to `NOT NULL` (+ optional FK) once verified. Idempotent.

```php
<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenancy.tenant_tables') as $table) {
            MigrationSupport::addColumn($table, 'subsidiary_id', function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('subsidiary_id')->nullable();
                if (! $this->indexExists($table, "{$table}_subsidiary_id_index")) {
                    $blueprint->index('subsidiary_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenancy.tenant_tables') as $table) {
            if (Schema::hasColumn($table, 'subsidiary_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('subsidiary_id'));
            }
        }
    }

    // information_schema check — avoids Doctrine assumptions under SafeMySqlConnection.
    private function indexExists(string $table, string $index): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        );
        return $row !== null;
    }
};
```

> `MigrationSupport::addColumn` no-ops if the table is missing or the column exists, so this is
> safe on a partial/legacy DB.

## File 4 — migration `2026_06_17_000003_seed_main_and_backfill_subsidiary.php`

Pure data migration — **idempotent, re-runnable.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $slug = config('tenancy.main_slug');

        $mainId = DB::table('subsidiary')->where('slug', $slug)->value('subsidiary_id');
        if (! $mainId) {
            $mainId = DB::table('subsidiary')->insertGetId([
                'slug'       => $slug,
                'name'       => env('TENANCY_MAIN_NAME', 'Main'),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'subsidiary_id');
        }

        foreach (config('tenancy.tenant_tables') as $table) {
            if (Schema::hasColumn($table, 'subsidiary_id')) {
                DB::table($table)->whereNull('subsidiary_id')->update(['subsidiary_id' => $mainId]);
            }
        }

        DB::table('user')->select('user_id')->orderBy('user_id')->chunk(500, function ($users) use ($mainId) {
            $rows = collect($users)->map(fn ($u) => [
                'subsidiary_id' => $mainId,
                'user_id'       => $u->user_id,
                'status'        => 'active',
                'joined_at'     => now(),
            ])->all();
            DB::table('subsidiary_user')->insertOrIgnore($rows); // unique() dedupes re-runs
        });
    }

    public function down(): void
    {
        // Non-destructive: leave data stamped.
    }
};
```

## File 5 — Models

```php
// app/Models/Subsidiary.php
class Subsidiary extends Model
{
    protected $table = 'subsidiary';
    protected $primaryKey = 'subsidiary_id';
    protected $fillable = ['slug', 'name', 'domain', 'status', 'settings'];
    protected $casts = ['settings' => 'array'];

    public function members() { return $this->hasMany(SubsidiaryUser::class, 'subsidiary_id', 'subsidiary_id'); }
    public function users()   { return $this->belongsToMany(User::class, 'subsidiary_user', 'subsidiary_id', 'user_id', 'subsidiary_id', 'user_id')->withPivot('status', 'joined_at'); }

    public static function main(): self
    {
        return static::where('slug', config('tenancy.main_slug'))->firstOrFail();
    }
}

// app/Models/SubsidiaryUser.php
class SubsidiaryUser extends Model
{
    protected $table = 'subsidiary_user';
    protected $primaryKey = 'subsidiary_user_id';
    public $timestamps = false;
    protected $fillable = ['subsidiary_id', 'user_id', 'status', 'joined_at'];
    protected $casts = ['joined_at' => 'datetime'];

    public function subsidiary() { return $this->belongsTo(Subsidiary::class, 'subsidiary_id', 'subsidiary_id'); }
    public function user()       { return $this->belongsTo(User::class, 'user_id', 'user_id'); }
}
```

## File 6 — additions to `app/Models/User.php` (relations only)

```php
public function subsidiaries()
{
    return $this->belongsToMany(Subsidiary::class, 'subsidiary_user', 'user_id', 'subsidiary_id', 'user_id', 'subsidiary_id')
                ->withPivot('status', 'joined_at');
}

public function memberships()
{
    return $this->hasMany(SubsidiaryUser::class, 'user_id', 'user_id');
}

public function belongsToSubsidiary(int $subsidiaryId): bool
{
    return $this->memberships()->where('subsidiary_id', $subsidiaryId)->where('status', 'active')->exists();
}
```

## Acceptance criteria

- Fresh `migrate` **and** re-run on the legacy VPS DB both succeed (idempotent; no error if
  `subsidiary_id` already present).
- Every row in all `tenant_tables` has `subsidiary_id = main`.
- Every existing user has exactly one `subsidiary_user(main, …)` row.
- **Zero behavior change** — no route/middleware/query/nav reads `subsidiary_id`.
- `migrate:rollback` drops the new tables/column cleanly.

## Open items to confirm at build time

- `information_schema` index check works under `SafeMySqlConnection` (it mirrors what
  `LegacySchemaSync` already does — expected fine).
- Membership backfill chunk size (500) is fine for VPS scale.
