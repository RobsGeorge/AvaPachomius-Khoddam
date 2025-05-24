<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Course;
use App\Models\Role;

class UserCourseRole extends Model
{
    protected $table = 'user_course_role';
    protected $primaryKey = 'user_course_role_id';

    protected $fillable = ['user_id', 'course_id', 'role_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

}
