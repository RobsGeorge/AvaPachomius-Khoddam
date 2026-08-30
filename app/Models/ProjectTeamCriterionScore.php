<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTeamCriterionScore extends Model
{
    use BelongsToChurch;

    protected $table = 'project_team_criterion_scores';

    protected $primaryKey = 'project_team_criterion_score_id';

    protected $fillable = [
        'project_team_grade_id',
        'project_team_grade_criterion_id',
        'points',
    ];

    protected $casts = [
        'points' => 'decimal:2',
    ];

    public function teamGrade(): BelongsTo
    {
        return $this->belongsTo(ProjectTeamGrade::class, 'project_team_grade_id', 'project_team_grade_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(
            ProjectTeamGradeCriterion::class,
            'project_team_grade_criterion_id',
            'project_team_grade_criterion_id'
        );
    }
}
