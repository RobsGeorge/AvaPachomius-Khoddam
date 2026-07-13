<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTestRun extends Model
{
    public $timestamps = false;

    protected $table = 'system_test_runs';

    protected $primaryKey = 'test_run_id';

    protected $fillable = [
        'suite', 'passed', 'failed', 'skipped', 'total', 'duration_ms',
        'batch_id', 'summary', 'output', 'status', 'triggered_by_id', 'created_at',
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
        return $this->status === 'passed' && $this->failed === 0;
    }
}
