<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventModuleTestRun extends Model
{
    public $timestamps = false;

    protected $table = 'event_module_test_runs';

    protected $primaryKey = 'test_run_id';

    protected $fillable = [
        'suite', 'passed', 'failed', 'total', 'duration_ms',
        'summary', 'output', 'status', 'triggered_by_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id', 'user_id');
    }

    public function isPassing(): bool
    {
        return $this->failed === 0 && $this->total > 0;
    }
}
