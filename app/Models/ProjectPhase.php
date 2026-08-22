<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhase extends Model
{
    use BelongsToChurch;

    protected $table = 'project_phases';

    protected $primaryKey = 'project_phase_id';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'deadline',
        'sort_order',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
