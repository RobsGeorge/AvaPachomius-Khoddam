<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dual-write mirror of user_course_role (T8b). UCR remains source of truth for reads.
 */
class Enrollment extends Model
{
    use BelongsToChurch;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

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
