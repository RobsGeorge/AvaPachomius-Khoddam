<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private instructor/admin notes about a student. Append-only.
 * created_by_user_id is stored for audit only — never render author in staff UI.
 */
class StudentInstructorNote extends Model
{
    use BelongsToChurch;

    protected $table = 'student_instructor_notes';

    protected $primaryKey = 'note_id';

    protected $fillable = [
        'church_id',
        'subject_user_id',
        'course_id',
        'module_id',
        'body',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('student_instructor_notes are append-only.');
        });

        static::deleting(function () {
            throw new LogicException('student_instructor_notes are append-only; use audit trails for corrections.');
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'note_id';
    }
}
