<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class UserCourseRole extends Model
{
    protected $table = 'user_course_role';

    protected $primaryKey = 'user_course_role_id';

    protected $fillable = [
        'user_id',
        'course_id',
        'church_id',
        'role_id',
        'eligible_for_grace',
        'pending_grace_marks',
        'staff_archived_at',
    ];

    protected $casts = [
        'eligible_for_grace' => 'boolean',
        'pending_grace_marks' => 'float',
        'staff_archived_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function scopeActiveStaff(Builder $query): Builder
    {
        if (self::hasStaffArchivedColumn()) {
            return $query->whereNull('staff_archived_at');
        }

        return $query;
    }

    public function scopeStaffArchivedOnly(Builder $query): Builder
    {
        if (self::hasStaffArchivedColumn()) {
            return $query->whereNotNull('staff_archived_at');
        }

        return $query->whereRaw('1 = 0');
    }

    private static function hasStaffArchivedColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn((new static)->getTable(), 'staff_archived_at');
        }

        return $hasColumn;
    }
}
