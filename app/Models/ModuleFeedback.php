<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleFeedback extends Model
{
    protected $table = 'module_feedback';

    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'user_id', 'course_id', 'module_id',
        'lecture_rating', 'lecture_comments',
        'speaker_rating', 'speaker_comments',
        'workshop_rating', 'workshop_comments',
        'timing_rating', 'timing_comments',
        'content_rating', 'content_comments',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }
}
