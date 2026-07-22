<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
