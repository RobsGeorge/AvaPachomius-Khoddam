<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseApplicationForm extends Model
{
    protected $fillable = [
        'course_id',
        'is_enabled',
        'title',
        'description',
        'default_role_id',
        'settings',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function defaultRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_role_id', 'role_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CourseApplicationFormStep::class, 'form_id')->orderBy('order_index');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CourseApplication::class, 'form_id');
    }

    public static function forCourse(Course|int $course): ?self
    {
        $courseId = $course instanceof Course ? $course->course_id : $course;

        return static::query()
            ->where('course_id', $courseId)
            ->with(['steps.fields'])
            ->first();
    }
}
