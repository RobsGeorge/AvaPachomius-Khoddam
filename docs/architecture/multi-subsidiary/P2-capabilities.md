# P2 — Capabilities (per-subsidiary feature switches)

**Goal:** make each subsidiary a different "product" by enabling a subset of features, each with
its own config (e.g. attendance mode/threshold). This is the layer that delivers "not all have
exams; attendance rules differ; some are reporting-only; some recur without modules."

**In scope:** capability catalog, `subsidiary_capability` table, `capability:` middleware,
`@capability` Blade directive, navigation refactor, feature-config consumption.
**Out of scope:** permissions (P3) — capability is the *ceiling*; permissions are *who*.

---

## 1. Capability catalog — `config/capabilities.php`

The catalog is **code-defined** (stable). Each entry declares the permissions it unlocks (used by
P3 to bound what roles can grant), route patterns it governs, and a config schema.

```php
<?php

return [
    'attendance' => [
        'label'       => 'capabilities.attendance',
        'permissions' => ['attendance.record', 'attendance.view_all', 'attendance.view_own', 'attendance.configure'],
        'config'      => ['mode' => 'strict', 'min_percentage' => 75, 'penalty' => true], // defaults
    ],
    'exams' => [
        'label'       => 'capabilities.exams',
        'permissions' => ['exam.author', 'exam.schedule', 'exam.grade', 'exam.take'],
        'config'      => [],
    ],
    'curriculum' => [
        'label'       => 'capabilities.curriculum',
        'permissions' => ['curriculum.manage', 'module.manage', 'course.manage', 'course.view'],
        'config'      => ['modules' => true, 'recurring_years' => false],
    ],
    'assignments' => [
        'label'       => 'capabilities.assignments',
        'permissions' => ['assignment.manage', 'assignment.submit', 'assignment.grade'],
        'config'      => [],
    ],
    'grades' => [
        'label'       => 'capabilities.grades',
        'permissions' => ['grade.manage', 'graduation.view'],
        'config'      => [],
    ],
    'assessments' => [
        'label'       => 'capabilities.assessments',
        'permissions' => ['assessment.manage'],
        'config'      => [],
    ],
    'reporting' => [
        'label'       => 'capabilities.reporting',
        'permissions' => ['report.view'],
        'config'      => [],
    ],
];
```

## 2. `subsidiary_capability` table

```php
// migration 2026_06_19_000001_create_subsidiary_capability_table.php
SchemaGuards::createTableIfMissing('subsidiary_capability', function (Blueprint $table) {
    $table->id('subsidiary_capability_id');
    $table->foreignId('subsidiary_id')->constrained('subsidiary', 'subsidiary_id')->cascadeOnDelete();
    $table->string('capability_key', 40);
    $table->boolean('enabled')->default(true);
    $table->json('config')->nullable();        // overrides over the catalog defaults
    $table->unique(['subsidiary_id', 'capability_key']);
});
```

## 3. Model + resolver

```php
// app/Models/SubsidiaryCapability.php
class SubsidiaryCapability extends Model
{
    protected $table = 'subsidiary_capability';
    protected $primaryKey = 'subsidiary_capability_id';
    public $timestamps = false;
    protected $fillable = ['subsidiary_id', 'capability_key', 'enabled', 'config'];
    protected $casts = ['enabled' => 'boolean', 'config' => 'array'];
}
```

Add to `Subsidiary` (cache the enabled set per request):

```php
public function hasCapability(string $key): bool
{
    return $this->enabledCapabilities()->has($key);
}

public function capabilityConfig(string $key): array
{
    $defaults = (array) data_get(config('capabilities'), "$key.config", []);
    $override = (array) optional($this->enabledCapabilities()->get($key))->config ?: [];
    return array_replace($defaults, $override);
}

public function enabledCapabilities(): \Illuminate\Support\Collection
{
    return $this->relationLoaded('capabilities')
        ? $this->capabilities->where('enabled', true)->keyBy('capability_key')
        : cache()->remember("sub:{$this->subsidiary_id}:caps", 300,
            fn () => $this->capabilities()->where('enabled', true)->get()->keyBy('capability_key'));
}

public function capabilities() { return $this->hasMany(SubsidiaryCapability::class, 'subsidiary_id', 'subsidiary_id'); }
```

> Cache invalidation: bust `sub:{id}:caps` whenever capabilities change (P5 UI does this).

## 4. `capability:` middleware

```php
// app/Http/Middleware/RequireCapability.php
class RequireCapability
{
    public function handle(Request $request, Closure $next, string $key)
    {
        $sub = \App\Support\Tenancy::current();
        abort_unless($sub && $sub->hasCapability($key), 404); // feature does not exist here
        return $next($request);
    }
}
```

Alias `capability` in `Kernel.php`, then wrap the **existing** route groups in `routes/web.php`:

```php
Route::middleware(['auth','subsidiary.member','capability:exams'])->group(function () { /* exam routes */ });
Route::middleware(['auth','subsidiary.member','capability:attendance'])->group(function () { /* attendance */ });
Route::middleware(['auth','subsidiary.member','capability:curriculum'])->group(function () { /* modules/curriculum/lectures */ });
// assignments, grades, assessments, reporting likewise
```

## 5. `@capability` Blade directive + navigation refactor

```php
// AppServiceProvider::boot()
Blade::if('capability', fn (string $key) => \App\Support\Tenancy::current()?->hasCapability($key));
```

Refactor `resources/views/layouts/navigation.blade.php`: replace the hardcoded
`@if(hasAnyRole(['admin','instructor']))` feature gates with capability gates (keep role/permission
checks only for "manage vs view"):

```blade
@capability('attendance')
    <a href="{{ route(auth()->user()->can('attendance.view_all') ? 'attendance.all' : 'attendance.my') }}" ...>
        {{ __('nav.attendance') }}
    </a>
@endcapability

@capability('exams')   <a href="{{ route('exams.index') }}" ...>{{ __('nav.exams') }}</a>   @endcapability
@capability('curriculum') ... @endcapability
@capability('reporting')  ... @endcapability
```

A subsidiary without `exams` never renders the link and 404s the routes — for that subsidiary only.

## 6. Feature-config consumption (the "different rules" payoff)

Replace hardcoded thresholds with capability config. Example — attendance:

```php
// AttendanceController / GraduationService
$cfg = current_subsidiary()->capabilityConfig('attendance'); // ['mode'=>'lenient','min_percentage'=>0,'penalty'=>false]
$min = $course->min_attendance_percentage ?? $cfg['min_percentage']; // course override wins, else subsidiary default
```

- **Strict** subsidiary: `mode=strict, min=75, penalty=true`.
- **Lenient** subsidiary: `mode=lenient, penalty=false`.
- **Reporting-only** subsidiary: `attendance` capability simply disabled.
- **Recurring years without modules**: `curriculum.config.modules=false, recurring_years=true`;
  a `course` with `year` set and no modules attached — nothing forces modules.

## 7. Seed: preserve status quo

On migrate, enable **all** capabilities for the `main` subsidiary so the existing app is unchanged:

```php
foreach (array_keys(config('capabilities')) as $key) {
    SubsidiaryCapability::firstOrCreate(
        ['subsidiary_id' => Subsidiary::main()->subsidiary_id, 'capability_key' => $key],
        ['enabled' => true]
    );
}
```

## Acceptance criteria

- Disabling a capability for a subsidiary 404s its routes and hides its nav — **only** for that
  subsidiary.
- Attendance/graduation read thresholds from capability config (with course override).
- `main` has all capabilities on → existing behavior unchanged.
- Capability changes invalidate the per-subsidiary cache.
