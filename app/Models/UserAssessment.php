<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\CourseAssessment;

class UserAssessment extends Model
{
    protected $table = 'user_assessment';
    protected $primaryKey = 'user_assessment_id';

    protected $fillable = [
        'user_id', 'course_assessment_id',
        'submitted_by_id', 'student_score'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courseAssessment()
    {
        return $this->belongsTo(CourseAssessment::class, 'course_assessment_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }
}
