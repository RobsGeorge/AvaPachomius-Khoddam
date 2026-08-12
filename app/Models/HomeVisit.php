<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HomeVisit extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'home_visit';

    protected $primaryKey = 'home_visit_id';

    public function getRouteKeyName(): string
    {
        return 'home_visit_id';
    }

    protected $fillable = [
        'assigned_user_id',
        'subject_type',
        'subject_id',
        'subject_name',
        'address',
        'scheduled_at',
        'duration_min',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_min' => 'integer',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'user_id');
    }

    /**
     * Linked subject (person|residence). Free-text subject_name/address remain for legacy rows.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    /**
     * Unfiltered relation for persistence only — NEVER eager-load into family-facing views.
     * Use {@see VisitNoteVisibility::visibleNotesFor()} for reads.
     */
    public function pastoralNotes(): HasMany
    {
        return $this->hasMany(VisitNote::class, 'home_visit_id', 'home_visit_id');
    }
}
