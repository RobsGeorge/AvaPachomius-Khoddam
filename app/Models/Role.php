<?php

namespace App\Models;

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

    public function systemUsers(): HasMany
    {
        return $this->hasMany(UserSystemRole::class, 'role_id', 'role_id');
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
