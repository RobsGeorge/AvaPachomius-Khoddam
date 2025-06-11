<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $primaryKey = 'assignment_id';
    
    protected $fillable = [
        'assignment_name',
        'assignment_description',
        'total_points',
        'due_date',
        'instructions',
        'resources',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id');
    }
} 