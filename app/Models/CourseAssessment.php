<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserAssessment;
use App\Models\Course;
use App\Models\Assessment;

class CourseAssessment extends Model
{

    protected $table = 'course_assessment';

    protected $primaryKey = 'course_assessment_id';

    protected $fillable = [
        'course_id', 'assessment_id', 'max_score',
        'min_score', 'assessment_date'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function userAssessments()
    {
        return $this->hasMany(UserAssessment::class, 'course_assessment_id');
    }
}

