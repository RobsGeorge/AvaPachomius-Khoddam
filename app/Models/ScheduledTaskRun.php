<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    public $timestamps = false;

    protected $table = 'scheduled_task_runs';

    protected $primaryKey = 'run_id';

    protected $fillable = [
        'task_key',
        'status',
        'trigger',
        'exit_code',
        'duration_ms',
        'output',
        'metadata',
        'started_at',
        'finished_at',
        'triggered_by_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id', 'user_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
