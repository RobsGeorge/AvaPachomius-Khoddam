<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only student-visible timeline of join / leave / move / merge events.
 */
class ProjectMembershipEvent extends Model
{
    use BelongsToChurch;

    public const EVENT_JOINED = 'joined';

    public const EVENT_LEFT = 'left';

    public const EVENT_MOVED_IN = 'moved_in';

    public const EVENT_MOVED_OUT = 'moved_out';

    public const EVENT_MERGED_IN = 'merged_in';

    protected $table = 'project_membership_events';

    protected $primaryKey = 'project_membership_event_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'user_id',
        'actor_user_id',
        'event',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }
}
