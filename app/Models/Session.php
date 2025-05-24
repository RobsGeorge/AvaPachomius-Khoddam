<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Attendance;

class Session extends Model
{

    protected $table = 'session';

    protected $primaryKey = 'session_id';

    protected $fillable = ['course_id', 'session_title', 'session_date'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }
}

