<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One team's deviation from the shared rubric: an override of a shared
 * criterion (optionally excluding it) or an extra team-only criterion.
 */
class ProjectTeamGradeCriterion extends Model
{
    use BelongsToChurch;

    protected $table = 'project_team_grade_criteria';

    protected $primaryKey = 'project_team_grade_criterion_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'project_grade_criterion_id',
        'title',
        'max_points',
        'is_excluded',
        'sort_order',
    ];

    protected $casts = [
        'max_points' => 'decimal:2',
        'is_excluded' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function sharedCriterion(): BelongsTo
    {
        return $this->belongsTo(ProjectGradeCriterion::class, 'project_grade_criterion_id', 'project_grade_criterion_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(
            ProjectTeamCriterionScore::class,
            'project_team_grade_criterion_id',
            'project_team_grade_criterion_id'
        );
    }

    public function isOverride(): bool
    {
        return $this->project_grade_criterion_id !== null;
    }
}
