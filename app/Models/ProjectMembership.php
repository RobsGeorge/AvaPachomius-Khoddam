<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMembership extends Model
{
    use BelongsToChurch;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEFT = 'left';

    protected $table = 'project_memberships';

    protected $primaryKey = 'project_membership_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'user_id',
        'status',
        'assigned_at',
        'left_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'left_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
