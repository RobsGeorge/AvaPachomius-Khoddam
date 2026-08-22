<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGradeCriterion extends Model
{
    use BelongsToChurch;

    protected $table = 'project_grade_criteria';

    protected $primaryKey = 'project_grade_criterion_id';

    protected $fillable = [
        'project_assessment_id',
        'title',
        'max_points',
        'sort_order',
    ];

    protected $casts = [
        'max_points' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }
}
