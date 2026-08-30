<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDeliverableGrade extends Model
{
    use BelongsToChurch;

    protected $table = 'project_deliverable_grades';

    protected $primaryKey = 'project_deliverable_grade_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'project_deliverable_id',
        'points',
        'graded_by_user_id',
        'graded_at',
    ];

    protected $casts = [
        'points' => 'decimal:2',
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

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ProjectDeliverable::class, 'project_deliverable_id', 'project_deliverable_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_user_id', 'user_id');
    }
}
