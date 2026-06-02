<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;
use App\Models\Session;
use App\Models\CourseAssessment;
use App\Models\GradeCategory;

class Course extends Model
{

    protected $table = 'course';

    protected $primaryKey = 'course_id';

    protected $fillable = ['title', 'description', 'year'];

    public function sessions()
    {
        return $this->hasMany(Session::class, 'course_id', 'course_id');
    }

    public function modules()
    {
        return $this->belongsToMany(
            Module::class,
            'course_module',
            'course_id',
            'module_id',
            'course_id',
            'module_id'
        )->withPivot([
            'start_date', 'end_date', 'order_index', 'status',
            'feedback_open', 'ended_at', 'ended_by_user_id',
        ])->orderByPivot('order_index');
    }

    public function assessments()
    {
        return $this->hasMany(CourseAssessment::class, 'course_id', 'course_id');
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_course_role',
            'course_id',
            'user_id',
            'course_id',
            'user_id'
        )->withPivot('role_id', 'user_course_role_id');
    }

    public function userCourseRoles()
    {
        return $this->hasMany(UserCourseRole::class, 'course_id', 'course_id');
    }

    public function gradeCategories()
    {
        return $this->hasMany(GradeCategory::class, 'course_id', 'course_id')
                    ->orderBy('ordering');
    }

    /** Total weighted grade for a student (0–100). Requires gradeCategories.items.grades loaded. */
    public function studentTotalGrade(int $userId): float
    {
        return round($this->gradeCategories->sum(fn ($cat) => $cat->studentContribution($userId)), 2);
    }
}
