<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One submission per team per deliverable. Any active member may create or
 * replace it; the roster shows who submitted last.
 */
class ProjectDeliverableSubmission extends Model
{
    use BelongsToChurch;

    protected $table = 'project_deliverable_submissions';

    protected $primaryKey = 'project_deliverable_submission_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'project_deliverable_id',
        'submitted_by_user_id',
        'body',
        'link_url',
        'submitted_at',
        'is_late',
        'instructor_feedback',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_late' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'project_deliverable_submission_id';
    }

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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(
            ProjectSubmissionFile::class,
            'project_deliverable_submission_id',
            'project_deliverable_submission_id'
        )->orderBy('project_submission_file_id');
    }

    public function hasInstructorFeedback(): bool
    {
        return filled($this->instructor_feedback);
    }
}
