<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;
use App\Models\Session;
use App\Models\CourseAssessment;

class Course extends Model
{

    protected $table = 'course';

    protected $primaryKey = 'course_id';

    protected $fillable = ['title', 'description', 'year'];

    public function sessions()
    {
        return $this->hasMany(Session::class, 'course_id');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'course_module', 'course_id', 'module_id');
    }

    public function assessments()
    {
        return $this->hasMany(CourseAssessment::class, 'course_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_course_role', 'course_id', 'user_id')
                    ->withPivot('role_id');
    }
}
