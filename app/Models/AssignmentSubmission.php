<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    protected $primaryKey = 'submission_id';
    
    protected $fillable = [
        'assignment_id',
        'user_id',
        'submission_content',
        'file_path',
        'points_earned',
        'feedback',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'points_earned' => 'integer',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
} 