<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleStudentAssessmentScore extends Model
{
    protected $table = 'module_student_assessment_scores';

    protected $primaryKey = 'score_id';

    protected $fillable = [
        'assessment_id',
        'criterion_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ModuleStudentAssessment::class, 'assessment_id', 'assessment_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssessmentCriterion::class, 'criterion_id', 'criterion_id');
    }

    public function getRouteKeyName(): string
    {
        return 'score_id';
    }
}
