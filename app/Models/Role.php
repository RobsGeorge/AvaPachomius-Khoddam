<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'role_id';

    public $timestamps = false;

    protected $fillable = [
        'role_name',
        'role_decription',
        'course_id',
        'service_id',
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

    /** @return Collection<int, int> */
    public static function studentRoleIds(): Collection
    {
        return static::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(role_name) = ?', ['student'])
                    ->orWhere('slug', 'student');
            })
            ->pluck('role_id');
    }

    /** @return Collection<int, int> */
    public static function staffRoleIds(): Collection
    {
        return static::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(role_name) IN (?, ?)', ['admin', 'instructor'])
                    ->orWhereIn('slug', ['admin', 'instructor']);
            })
            ->pluck('role_id');
    }

    public static function studentRoleForCourse(int|string $courseId): ?self
    {
        return static::query()
            ->where('course_id', $courseId)
            ->where(function ($q) {
                $q->whereRaw('LOWER(role_name) = ?', ['student'])
                    ->orWhere('slug', 'student');
            })
            ->first();
    }
}
