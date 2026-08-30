<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectAssessment extends Model
{
    use BelongsToChurch;

    protected $table = 'project_assessments';

    protected $primaryKey = 'project_assessment_id';

    protected $fillable = [
        'course_id',
        'module_id',
        'title',
        'description',
        'min_team_size',
        'max_team_size',
        'max_points',
        'passing_percent',
        'grading_mode',
        'is_published',
        'join_closes_at',
        'seed_pool_size',
        'results_announced_at',
        'results_announced_by_user_id',
        'sync_to_gradebook',
        'gradebook_item_id',
        'gradebook_synced_at',
        'created_by_user_id',
        'peer_eval_enabled',
        'peer_eval_opens_at',
        'peer_eval_closes_at',
        'peer_eval_scale_max',
        'peer_eval_prompt',
    ];

    protected $casts = [
        'min_team_size' => 'integer',
        'max_team_size' => 'integer',
        'max_points' => 'decimal:2',
        'passing_percent' => 'integer',
        'is_published' => 'boolean',
        'join_closes_at' => 'datetime',
        'seed_pool_size' => 'integer',
        'results_announced_at' => 'datetime',
        'sync_to_gradebook' => 'boolean',
        'gradebook_synced_at' => 'datetime',
        'peer_eval_enabled' => 'boolean',
        'peer_eval_opens_at' => 'datetime',
        'peer_eval_closes_at' => 'datetime',
        'peer_eval_scale_max' => 'integer',
    ];

    public const GRADING_MODE_RUBRIC = 'rubric';

    public const GRADING_MODE_DELIVERABLES = 'deliverables';

    public function getRouteKeyName(): string
    {
        return 'project_assessment_id';
    }

    public function usesDeliverableGrading(): bool
    {
        return ($this->grading_mode ?: self::GRADING_MODE_RUBRIC) === self::GRADING_MODE_DELIVERABLES;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_assessment_id', 'project_assessment_id')
            ->orderBy('sort_order')
            ->orderBy('project_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ProjectChangeRequest::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ProjectGradeCriterion::class, 'project_assessment_id', 'project_assessment_id')
            ->orderBy('sort_order')
            ->orderBy('project_grade_criterion_id');
    }

    public function teamGrades(): HasMany
    {
        return $this->hasMany(ProjectTeamGrade::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function memberGrades(): HasMany
    {
        return $this->hasMany(ProjectMemberGrade::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function announcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'results_announced_by_user_id', 'user_id');
    }

    public function areResultsAnnounced(): bool
    {
        return $this->results_announced_at !== null;
    }

    public function activeMembershipFor(int $userId): ?ProjectMembership
    {
        return $this->memberships()
            ->where('user_id', $userId)
            ->where('status', ProjectMembership::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Legacy assessments (created before v2) have no window and stay open.
     */
    public function isJoinWindowOpen(): bool
    {
        return $this->join_closes_at === null || $this->join_closes_at->isFuture();
    }

    public function hasJoinWindowClosed(): bool
    {
        return ! $this->isJoinWindowOpen();
    }

    /**
     * One self-service team change per student per assessment. v1 approved
     * admin change requests still count so the chance is not handed back.
     */
    public function hasUsedChangeChance(int $userId): bool
    {
        $usedSelfChange = $this->memberships()
            ->where('user_id', $userId)
            ->whereNotNull('change_chance_used_at')
            ->exists();

        if ($usedSelfChange) {
            return true;
        }

        return $this->changeRequests()
            ->where('user_id', $userId)
            ->where('status', ProjectChangeRequest::STATUS_APPROVED)
            ->exists();
    }

    public function pendingChangeRequestFor(int $userId): ?ProjectChangeRequest
    {
        return $this->changeRequests()
            ->where('user_id', $userId)
            ->where('status', ProjectChangeRequest::STATUS_PENDING)
            ->first();
    }
}
