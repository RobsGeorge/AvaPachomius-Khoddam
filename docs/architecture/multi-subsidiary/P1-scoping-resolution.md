# P1 — Scoping & resolution

**Goal:** enforce hard tenant isolation and resolve the current subsidiary per request. Still
**no subdomains** — resolution defaults to "main", so behavior is unchanged for the single
existing tenant, but the isolation machinery is now in place and tested.

**In scope:** `currentSubsidiary` binding, `BelongsToSubsidiary` trait (global scope + auto-stamp
on insert), `IdentifySubsidiary` middleware (main fallback), membership gate, `NOT NULL`/FK
enforcement on `subsidiary_id`, superadmin bypass, impersonation context.
**Out of scope:** real subdomain resolution (P4), capabilities (P2), permissions (P3).

---

## 1. Container binding + helper

The current subsidiary is bound once per request and read everywhere (scope, branding, nav).

```php
// app/Support/Tenancy.php
final class Tenancy
{
    public static function current(): ?Subsidiary
    {
        return app()->bound('currentSubsidiary') ? app('currentSubsidiary') : null;
    }

    public static function id(): ?int
    {
        return static::current()?->subsidiary_id;
    }

    public static function set(Subsidiary $s): void
    {
        app()->instance('currentSubsidiary', $s);
    }

    /** True when queries must be tenant-filtered (i.e. not the superadmin console). */
    public static function enforced(): bool
    {
        return static::current() !== null;
    }
}

// helper
function current_subsidiary(): ?Subsidiary { return \App\Support\Tenancy::current(); }
```

Design rule: **a subsidiary is bound for every normal request.** The *only* unbound context is
the superadmin console host (P4), which is `is_superadmin`-gated — so non-superadmins can never
reach an unscoped state.

## 2. `BelongsToSubsidiary` trait (the isolation core)

```php
// app/Models/Concerns/BelongsToSubsidiary.php
trait BelongsToSubsidiary
{
    public static function bootBelongsToSubsidiary(): void
    {
        // Read filter
        static::addGlobalScope('subsidiary', function (Builder $q) {
            if (\App\Support\Tenancy::enforced()) {
                $q->where($q->getModel()->getTable().'.subsidiary_id', \App\Support\Tenancy::id());
            }
        });

        // Write stamp — a controller cannot create into the wrong tenant
        static::creating(function (Model $m) {
            if (empty($m->subsidiary_id) && \App\Support\Tenancy::enforced()) {
                $m->subsidiary_id = \App\Support\Tenancy::id();
            }
        });
    }

    public function subsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'subsidiary_id', 'subsidiary_id');
    }
}
```

Apply the trait to every data-root model: `Course`, `Module`, `Content`, `Assignment`,
`Session`, `Exam`, `Asessment`, `CourseAssessment`, `Attendance`, `GradeCategory`, `ActivityLog`.

> **Child tables** (e.g. `ExamQuestion`, `GradeItem`, `StudentGrade`, `LectureMaterial`) are
> normally reached through a scoped parent. Any child that is **queried directly** must also get
> a `subsidiary_id` column + the trait, or it leaks. Audit direct `::where`/`::find` on children
> when applying the trait and extend `tenant_tables` as needed.

### Superadmin / cross-tenant bypass
When `Tenancy::enforced()` is false (console host, superadmin), the scope no-ops → superadmin
sees all subsidiaries. Inside a subsidiary subdomain, even a superadmin is scoped to that
subsidiary unless they explicitly enter a "view across" mode. Provide an escape hatch when needed:

```php
Model::withoutGlobalScope('subsidiary')->...   // superadmin-only code paths
```

## 3. `IdentifySubsidiary` middleware

```php
// app/Http/Middleware/IdentifySubsidiary.php
class IdentifySubsidiary
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Console host → no tenant binding (superadmin only; enforced by 'superadmin' mw on the routes)
        if ($host === config('tenancy.console_host')) {
            return $next($request);
        }

        $subsidiary =
            Subsidiary::where('domain', $host)->first()                    // custom domain (P4)
            ?? Subsidiary::where('slug', explode('.', $host)[0])->first()  // subdomain   (P4)
            ?? Subsidiary::main();                                          // P1 default

        abort_if(! $subsidiary || $subsidiary->status !== 'active', 404, 'Unknown subsidiary.');

        \App\Support\Tenancy::set($subsidiary);
        view()->share('currentSubsidiary', $subsidiary);

        return $next($request);
    }
}
```

Register in the `web` group **after `StartSession`, before `LogUserActivity`** in
`app/Http/Kernel.php`:

```php
'web' => [
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
    ShareErrorsFromSession::class, VerifyCsrfToken::class, SubstituteBindings::class,
    SetLocale::class,
    \App\Http\Middleware\IdentifySubsidiary::class,   // NEW
    \App\Http\Middleware\LogUserActivity::class,
],
```

In P1 every host falls through to `Subsidiary::main()`, so nothing changes for users.

## 4. Membership gate

Resolution (host→subsidiary) needs no auth, so it's global. The **membership check** needs auth,
so it's a separate middleware on authenticated route groups + enforced at login.

```php
// app/Http/Middleware/EnsureSubsidiaryMember.php
class EnsureSubsidiaryMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $sub  = \App\Support\Tenancy::current();

        if ($user && $sub && ! $user->is_superadmin && ! $user->belongsToSubsidiary($sub->subsidiary_id)) {
            abort(403, __('auth.not_a_member'));
        }
        return $next($request);
    }
}
```

- Alias `subsidiary.member` and add it to the authenticated route groups (alongside `auth`).
- Also check at login (`LoginController`/`AuthenticatedSessionController`): after credentials pass,
  if the user is not a member of the resolved subsidiary (and not superadmin), reject with a clear
  message. In P1 (main only) every backfilled user is a member, so this is a no-op until P4.

## 5. Enforce `NOT NULL` + FK

Now that P0 backfilled everything, lock it down (idempotent; guard each table):

```php
// migration 2026_06_18_000001_enforce_subsidiary_id_not_null.php  (mysql)
foreach (config('tenancy.tenant_tables') as $table) {
    if (Schema::hasColumn($table, 'subsidiary_id')) {
        DB::statement("ALTER TABLE `{$table}` MODIFY `subsidiary_id` BIGINT UNSIGNED NOT NULL");
        // Optional FK — only if no orphan rows; wrap in try/guard for legacy data.
    }
}
```

## 6. Impersonation context

`ImpersonationService` must set the impersonated user's subsidiary so the global scope follows the
impersonated identity, not the superadmin's. When impersonation starts, resolve a target
subsidiary (the one being viewed) and `Tenancy::set()` it for the duration; restore on stop.

## Acceptance criteria

- `Course::all()` (and every scoped model) returns only current-subsidiary rows.
- Creating any scoped model auto-stamps `subsidiary_id` = current; cannot write cross-tenant.
- A user without membership in the current subsidiary is rejected (superadmin exempt).
- Superadmin console / `withoutGlobalScope` sees all subsidiaries.
- Impersonation respects the impersonated user's subsidiary.
- **Main still behaves exactly as before** (all data is main; one tenant).
- Isolation tests: seed two subsidiaries in tests, assert cross-tenant read AND write both fail.
