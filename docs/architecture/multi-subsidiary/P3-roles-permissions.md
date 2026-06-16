# P3 — Roles & permissions (dynamic, per-subsidiary)

**Goal:** replace global role-name checks with **subsidiary-contextual, permission-based**
authorization, where roles are owned per subsidiary and grants are a **dynamic, admin-editable
matrix**. Supports: a user holding different roles in different subsidiaries; roles that exist
only in some subsidiaries (e.g. *student* in one, *servant/خادم* in another); custom roles with
no code changes.

**In scope:** evolve `roles`, add `permissions` + `role_permission`, scope `user_course_role`,
permission catalog + seeding, `RoleTemplateService`, contextual resolver, `permission:` middleware
(replacing `RoleMiddleware`), cache + guardrails, basic management controller.
**Out of scope:** polished management UI + provisioning UI (P5).

---

## Why this design is safe to be "fully dynamic"

- **Permission keys are a code-defined contract** (`config/permissions.php`). Routes reference
  keys; admins never edit route code → no lockout by misconfiguration.
- **Grants are data** (`role_permission`) → admins toggle freely, no deploy.
- **Capabilities are the ceiling** → an admin can't grant a permission their subsidiary's
  capabilities don't expose.
- **Roles need no code** → "servant" is just a bundle of permissions; code checks
  `permission:attendance.record`, never `role == 'servant'`.

## 1. Schema

### Evolve `roles` (idempotent column adds on the existing table)
```php
MigrationSupport::addColumn('roles', 'subsidiary_id', fn (Blueprint $t) =>
    $t->unsignedBigInteger('subsidiary_id')->nullable()->index());   // null = platform template
MigrationSupport::addStringColumn('roles', 'slug', 40, false);        // stable handle: admin/student/servant
MigrationSupport::addBooleanColumn('roles', 'is_system', false);      // protect built-ins
// Backfill: existing roles -> main, slug from role_name; then add UNIQUE(subsidiary_id, slug).
```

### New `permissions` + `role_permission`
```php
SchemaGuards::createTableIfMissing('permissions', function (Blueprint $t) {
    $t->id('permission_id');
    $t->string('key', 60)->unique();            // 'exam.grade'
    $t->string('capability_key', 40)->nullable(); // groups under a capability for the UI
    $t->string('description', 191)->nullable();
});

SchemaGuards::createTableIfMissing('role_permission', function (Blueprint $t) {
    $t->id('role_permission_id');
    $t->foreignId('role_id')->constrained('roles', 'role_id')->cascadeOnDelete();
    $t->foreignId('permission_id')->constrained('permissions', 'permission_id')->cascadeOnDelete();
    $t->unique(['role_id', 'permission_id']);
});
```

### Scope `user_course_role` (grants become subsidiary-anchored)
```php
MigrationSupport::addColumn('user_course_role', 'subsidiary_id', fn (Blueprint $t) =>
    $t->unsignedBigInteger('subsidiary_id')->nullable()->index());
// Make course_id NULLABLE (null = subsidiary-wide grant, for reporting-only / no-course subs):
DB::statement('ALTER TABLE `user_course_role` MODIFY `course_id` BIGINT UNSIGNED NULL');
// Backfill subsidiary_id from the grant's course->subsidiary_id, fallback main; then NOT NULL.
// Add a 'permissions_version' column to subsidiary for cache busting:
MigrationSupport::addColumn('subsidiary', 'permissions_version', fn (Blueprint $t) =>
    $t->unsignedInteger('permissions_version')->default(1));
```

### The resulting model (your example)
```
roles
 (subsidiary=Academy, slug='student',    name='طالب')
 (subsidiary=Academy, slug='instructor', name='مدرّس')
 (subsidiary=Service, slug='servant',    name='خادم')     ← only here
 (subsidiary=Service, slug='served',     name='مخدوم')    ← only here
 (subsidiary=Service, slug='admin',      name='أمين خدمة')
 (subsidiary=NULL,    slug='admin', is_system=1)          ← platform template

user_course_role
 (sub=Academy, user=Mina, role=student, course=…)         ← Mina is a student in Academy
 (sub=Service, user=Mina, role=servant, course=NULL)      ← …and a servant in Service
```

## 2. Permission catalog — `config/permissions.php`

```php
return [
    // key                       => capability_key
    'subsidiary.configure'       => null,            // platform / superadmin-only
    'subsidiary.members.manage'  => null,
    'role.manage'                => null,
    'user.approve'               => null,
    'attendance.record'          => 'attendance',
    'attendance.view_all'        => 'attendance',
    'attendance.view_own'        => 'attendance',
    'attendance.configure'       => 'attendance',
    'exam.author'                => 'exams',
    'exam.schedule'              => 'exams',
    'exam.grade'                 => 'exams',
    'exam.take'                  => 'exams',
    'curriculum.manage'          => 'curriculum',
    'module.manage'              => 'curriculum',
    'course.manage'              => 'curriculum',
    'course.view'                => 'curriculum',
    'assignment.manage'          => 'assignments',
    'assignment.submit'          => 'assignments',
    'assignment.grade'           => 'assignments',
    'grade.manage'               => 'grades',
    'graduation.view'            => 'grades',
    'assessment.manage'          => 'assessments',
    'report.view'                => 'reporting',
];
```

Permissions with `capability_key = null` are **platform-level**, superadmin-only, never shown in a
subsidiary admin's matrix.

## 3. Seed permissions + platform role templates

```php
// PermissionSeeder: upsert every key from config('permissions')
// RoleTemplateSeeder: create subsidiary_id=NULL template roles and their default permission sets:
$templates = [
    'admin'      => ['*'],                                  // all perms of enabled capabilities
    'instructor' => ['curriculum.manage','module.manage','course.manage','exam.author','exam.schedule','exam.grade','assignment.manage','assignment.grade','grade.manage','attendance.view_all'],
    'examiner'   => ['exam.author','exam.schedule','exam.grade'],
    'attendant'  => ['attendance.record','attendance.view_all'],
    'reporter'   => ['report.view'],
    'member'     => ['exam.take','assignment.submit','attendance.view_own','course.view','graduation.view'],
];
```

## 4. `RoleTemplateService` (used by provisioning, P5)

```php
class RoleTemplateService
{
    /** Clone platform templates into a subsidiary, limited to its enabled capabilities. */
    public function cloneInto(Subsidiary $sub): array
    {
        $enabledPerms = Permission::where(function ($q) use ($sub) {
            $q->whereNull('capability_key')                         // platform perms (admin only)
              ->orWhereIn('capability_key', $sub->enabledCapabilities()->keys());
        })->get();

        $created = [];
        foreach (Role::whereNull('subsidiary_id')->with('permissions')->get() as $template) {
            $role = Role::create([
                'subsidiary_id' => $sub->subsidiary_id,
                'slug' => $template->slug, 'role_name' => $template->role_name,
                'is_system' => $template->is_system,
            ]);
            $perms = $template->slug === 'admin'
                ? $enabledPerms->whereNotNull('capability_key')->merge($enabledPerms) // admin: everything available
                : $template->permissions->whereIn('key', $enabledPerms->pluck('key'));
            $role->permissions()->sync($perms->pluck('permission_id'));
            $created[$template->slug] = $role;
        }
        return $created;
    }
}
```

## 5. Contextual resolver on `User`

```php
/** Effective permission keys for this user in a subsidiary (cached per request + version). */
public function permissionsIn(Subsidiary $sub): \Illuminate\Support\Collection
{
    if ($this->is_superadmin) {
        return Permission::pluck('key'); // god mode
    }
    $key = "perms:{$sub->subsidiary_id}:{$this->user_id}:{$sub->permissions_version}";
    return cache()->remember($key, 600, function () use ($sub) {
        $roleIds = UserCourseRole::where('subsidiary_id', $sub->subsidiary_id)
            ->where('user_id', $this->user_id)->pluck('role_id');
        return DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->whereIn('role_permission.role_id', $roleIds)
            ->distinct()->pluck('permissions.key');
    });
}

public function canInSubsidiary(string $permission, ?Subsidiary $sub = null): bool
{
    $sub ??= current_subsidiary();
    return $sub && $this->permissionsIn($sub)->contains($permission);
}
```

Wire Laravel's Gate so `$user->can('exam.grade')` and `@can('exam.grade')` work against the current
subsidiary:

```php
// AuthServiceProvider::boot()
Gate::before(fn ($user) => $user->is_superadmin ? true : null);
foreach (array_keys(config('permissions')) as $key) {
    Gate::define($key, fn ($user) => $user->canInSubsidiary($key));
}
```

## 6. Replace `RoleMiddleware` with `permission:`

```php
// app/Http/Middleware/RequirePermission.php
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        abort_unless($user, 403);
        foreach ($permissions as $p) {
            if ($user->canInSubsidiary($p)) return $next($request);
        }
        abort(403, __('pages.not_authorized'));
    }
}
```

Migration of existing routes (`routes/web.php`):
| Old | New |
|---|---|
| `role:instructor,admin` | `permission:course.manage` (or the specific action) |
| `role:admin` | `permission:role.manage` / capability + permission |
| `attendance.staff` middleware | `permission:attendance.record` |
| `admin` middleware | keep for superadmin OR map to `permission:user.approve` etc. |
| `superadmin` middleware | **unchanged** (platform god mode) |

Update Blade gates from `hasAnyRole([...])` → `@can('...')`. Update `AdminMiddleware` /
`AttendanceStaffMiddleware` to permission checks (superadmin path stays).

## 7. Cache invalidation + guardrails

**Invalidation:** any change to `role_permission`, a user's grants, or capabilities → increment
`subsidiary.permissions_version` (instantly invalidates all `perms:{sub}:*` keys).

**Guardrails (`RolePermissionPolicy`)** — required before exposing the matrix to subsidiary admins:
- Admin may grant only permissions **they themselves hold**.
- Admin may grant only permissions whose `capability_key` is **enabled** for the subsidiary
  (platform-null perms are superadmin-only).
- **Lockout protection:** refuse to remove the last `role.manage`/admin grant in a subsidiary.
- Hard floor: the `admin.` console + the permission-management screen always require
  `is_superadmin` regardless of DB state.

## 8. Management controller (engine-level; UI polished in P5)

`RolePermissionController` — for the current subsidiary: list roles, create/edit a role
(slug+name), render the **roles × permissions matrix** (filtered to enabled capabilities), persist
toggles through the policy + version bump, all audited via `AuditLogService`.

## Acceptance criteria

- One user can hold different roles in different subsidiaries; checks resolve to the current one.
- A subsidiary can have roles that don't exist in another (student vs servant), incl. custom roles
  with no code changes.
- `permission:` gates enforce; superadmin bypasses; toggling a grant takes effect after version bump.
- Guardrails block privilege escalation and admin lockout.
- All former `role:`/`hasAnyRole` checks migrated; `main` users retain equivalent access.
