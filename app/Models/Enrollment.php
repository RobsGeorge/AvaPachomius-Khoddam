<?php

namespace App\Models;

use App\Support\Structure\RosterStatus;
use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dual-write mirror of user_course_role (T8b). UCR remains source of truth for reads.
 * T9a expands status beyond active/archived for cycle progression eligibility.
 */
class Enrollment extends Model
{
    use BelongsToChurch;

    public const STATUS_ACTIVE = RosterStatus::ACTIVE;

    public const STATUS_ARCHIVED = RosterStatus::ARCHIVED;

    public const STATUS_INACTIVE = RosterStatus::INACTIVE;

    public const STATUS_LEFT = RosterStatus::LEFT;

    public const STATUS_PASTORAL_HOLD = RosterStatus::PASTORAL_HOLD;

    protected $table = 'enrollments';

    protected $primaryKey = 'enrollment_id';

    protected $fillable = [
        'church_id',
        'user_id',
        'course_id',
        'role_id',
        'service_unit_id',
        'user_course_role_id',
        'status',
        'status_note',
        'status_changed_at',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class, 'service_unit_id', 'service_unit_id');
    }

    public function userCourseRole(): BelongsTo
    {
        return $this->belongsTo(UserCourseRole::class, 'user_course_role_id', 'user_course_role_id');
    }
}
