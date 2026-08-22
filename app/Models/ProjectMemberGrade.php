<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMemberGrade extends Model
{
    use BelongsToChurch;

    public const SOURCE_TEAM = 'team';

    public const SOURCE_OVERRIDE = 'override';

    protected $table = 'project_member_grades';

    protected $primaryKey = 'project_member_grade_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'user_id',
        'points',
        'percent',
        'source',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isOverride(): bool
    {
        return $this->source === self::SOURCE_OVERRIDE;
    }

    public function passed(?int $passingPercent = null): bool
    {
        $passing = $passingPercent ?? (int) $this->assessment?->passing_percent ?? 0;

        return (float) $this->percent >= $passing;
    }
}
