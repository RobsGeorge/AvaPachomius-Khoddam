<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskDefinition extends Model
{
    protected $table = 'scheduled_task_definitions';

    protected $primaryKey = 'definition_id';

    protected $fillable = [
        'task_key',
        'label_en',
        'label_ar',
        'description_en',
        'description_ar',
        'command',
        'parameters',
        'cron_expression',
        'timezone',
        'enabled',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'parameters' => 'array',
        'enabled' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id', 'user_id');
    }

    public function localizedLabel(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->label_ar ?: $this->label_en)
            : ($this->label_en ?: $this->label_ar);
    }

    public function localizedDescription(): ?string
    {
        $description = app()->getLocale() === 'ar'
            ? ($this->description_ar ?: $this->description_en)
            : ($this->description_en ?: $this->description_ar);

        return $description !== '' ? $description : null;
    }

    /** @return array<string, mixed> */
    public function toTaskDefinition(): array
    {
        return [
            'label' => $this->localizedLabel(),
            'description' => $this->localizedDescription() ?? '',
            'type' => 'command',
            'command' => $this->command,
            'parameters' => $this->parameters ?? [],
            'schedule' => [],
            'is_custom' => true,
            'definition_id' => $this->definition_id,
        ];
    }
}
