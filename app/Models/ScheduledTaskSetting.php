<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskSetting extends Model
{
    protected $table = 'scheduled_task_settings';

    protected $primaryKey = 'setting_id';

    protected $fillable = [
        'task_key',
        'enabled',
        'cron_expression',
        'updated_by_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id', 'user_id');
    }
}
