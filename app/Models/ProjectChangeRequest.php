<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChangeRequest extends Model
{
    use BelongsToChurch;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'project_change_requests';

    protected $primaryKey = 'project_change_request_id';

    protected $fillable = [
        'project_assessment_id',
        'user_id',
        'from_project_id',
        'reason',
        'status',
        'admin_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'project_change_request_id';
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id', 'project_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
