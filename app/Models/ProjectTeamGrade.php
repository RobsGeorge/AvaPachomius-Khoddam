<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTeamGrade extends Model
{
    use BelongsToChurch;

    protected $table = 'project_team_grades';

    protected $primaryKey = 'project_team_grade_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'points',
        'percent',
        'notes',
        'graded_by_user_id',
        'graded_at',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'percent' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_user_id', 'user_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ProjectTeamGradeScore::class, 'project_team_grade_id', 'project_team_grade_id');
    }
}
