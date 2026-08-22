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
        'is_published',
        'created_by_user_id',
    ];

    protected $casts = [
        'min_team_size' => 'integer',
        'max_team_size' => 'integer',
        'is_published' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'project_assessment_id';
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

    public function activeMembershipFor(int $userId): ?ProjectMembership
    {
        return $this->memberships()
            ->where('user_id', $userId)
            ->where('status', ProjectMembership::STATUS_ACTIVE)
            ->first();
    }

    public function hasUsedChangeChance(int $userId): bool
    {
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
