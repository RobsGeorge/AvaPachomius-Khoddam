<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDeliverable extends Model
{
    use BelongsToChurch;

    protected $table = 'project_deliverables';

    protected $primaryKey = 'project_deliverable_id';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'due_at',
        'sort_order',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
