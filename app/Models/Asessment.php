<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CourseAssessment;

class Assessment extends Model
{

    protected $table = 'assessment';

    protected $primaryKey = 'assessment_id';

    protected $fillable = ['assessment_title'];

    public function courseAssessments()
    {
        return $this->hasMany(CourseAssessment::class, 'assessment_id');
    }
}
