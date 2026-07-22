<?php

namespace App\Models;

use App\Services\CoursePermissionResolver;
use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    use BelongsToChurch;

    protected $table = 'roles';

    protected $primaryKey = 'role_id';

    public $timestamps = false;

    protected $fillable = [
        'role_name',
        'role_decription',
        'course_id',
        'service_id',
        'church_id',
        'slug',
        'description',
        'is_system',
        'is_template',
        'cloned_from_role_id',
        'permissions_version',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_template' => 'boolean',
        'permissions_version' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    protected static function churchScopeAllowsNullTemplates(): bool
    {
        return true;
    }

    /**
     * Platform role templates must keep church_id NULL so cloneTemplatesInto*
     * can find them while TenantContext is bound (P1.2 dormant tenancy).
     */
    protected static function shouldStampChurchIdOnCreate(Model $model): bool
    {
        return ! (bool) $model->getAttribute('is_template');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'cloned_from_role_id', 'role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'role_id',
            'permission_id',
            'role_id',
            'permission_id'
        );
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_course_role',
            'role_id',
            'user_id',
            'role_id',
            'user_id'
        )->withPivot('course_id', 'user_course_role_id');
    }

    public function userCourseRoles(): HasMany
    {
        return $this->hasMany(UserCourseRole::class, 'role_id', 'role_id');
    }

    public function userServiceRoles(): HasMany
    {
        return $this->hasMany(UserServiceRole::class, 'role_id', 'role_id');
    }

    public function systemUsers(): HasMany
    {
        return $this->hasMany(UserSystemRole::class, 'role_id', 'role_id');
    }

    public function scopeAssignableToCourses(Builder $query): Builder
    {
        return $query
            ->whereNotNull('course_id')
            ->where('is_template', false);
    }

    public function scopeAssignableToServices(Builder $query): Builder
    {
        return $query
            ->whereNotNull('service_id')
            ->whereNull('course_id')
            ->where('is_template', false);
    }

    public function scopeForService(Builder $query, int|string $serviceId): Builder
    {
        return $query->assignableToServices()->where('service_id', $serviceId);
    }

    public function isServiceScoped(): bool
    {
        return $this->service_id !== null && $this->course_id === null;
    }

    public function scopeForCourse(Builder $query, int|string $courseId): Builder
    {
        return $query->assignableToCourses()->where('course_id', $courseId);
    }

    public function scopeLegacyGlobals(Builder $query): Builder
    {
        return $query
            ->whereNull('course_id')
            ->where('is_template', false);
    }

    public function isCourseScoped(): bool
    {
        return $this->course_id !== null;
    }

    public function isTemplate(): bool
    {
        return (bool) $this->is_template;
    }

    public function displayName(): string
    {
        return $this->role_name;
    }

    public function effectiveSlug(): string
    {
        return $this->slug ?? \Illuminate\Support\Str::slug($this->role_name);
    }

    /**
     * Role IDs that hold any of the given permission keys (authz via keys, not role_name).
     *
     * @param  list<string>  $keys
     * @return Collection<int, int>
     */
    public static function roleIdsHoldingAnyPermission(array $keys): Collection
    {
        if ($keys === []
            || ! Schema::hasTable('role_permission')
            || ! Schema::hasTable('permissions')) {
            return collect();
        }

        return DB::table('role_permission')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permission.permission_id')
            ->whereIn('permissions.key', $keys)
            ->whereNull('permissions.deprecated_at')
            ->distinct()
            ->pluck('role_permission.role_id');
    }

    /**
     * Learner roster roles: permission keys and/or student template slug.
     * Slug union keeps enrollments that use `createRole('student')` / unscoped
     * template clones before permissions are synced onto the role.
     *
     * @return Collection<int, int>
     */
    public static function studentRoleIds(): Collection
    {
        $bySlug = static::query()
            ->where(function ($q) {
                $q->where('slug', 'student')
                    ->orWhere('slug', 'like', 'student-%');
            })
            ->pluck('role_id');

        $learner = self::roleIdsHoldingAnyPermission(CoursePermissionResolver::LEARNER_PERMISSION_KEYS);
        $staff = self::roleIdsHoldingAnyPermission(CoursePermissionResolver::STAFF_PERMISSION_KEYS);

        if ($learner->isEmpty()) {
            return $bySlug->values();
        }

        return $learner->diff($staff)->merge($bySlug)->unique()->values();
    }

    /**
     * Staff roster roles via staff permission keys and/or admin|instructor slugs.
     *
     * @return Collection<int, int>
     */
    public static function staffRoleIds(): Collection
    {
        $bySlug = static::query()
            ->where(function ($q) {
                $q->whereIn('slug', ['admin', 'instructor'])
                    ->orWhere('slug', 'like', 'admin-%')
                    ->orWhere('slug', 'like', 'instructor-%');
            })
            ->pluck('role_id');

        $staff = self::roleIdsHoldingAnyPermission(CoursePermissionResolver::STAFF_PERMISSION_KEYS);

        if ($staff->isEmpty()) {
            return $bySlug->values();
        }

        return $staff->merge($bySlug)->unique()->values();
    }

    public static function studentRoleForCourse(int|string $courseId): ?self
    {
        $ids = self::studentRoleIds();
        if ($ids->isNotEmpty()) {
            $byPermission = static::query()
                ->where('course_id', $courseId)
                ->whereIn('role_id', $ids)
                ->orderBy('role_id')
                ->first();

            if ($byPermission) {
                return $byPermission;
            }
        }

        return static::query()
            ->where('course_id', $courseId)
            ->where(function ($q) {
                $q->where('slug', 'student')
                    ->orWhere('slug', 'like', 'student-%');
            })
            ->orderBy('role_id')
            ->first();
    }
}
