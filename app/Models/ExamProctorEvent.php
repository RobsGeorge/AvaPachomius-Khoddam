<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamProctorEvent extends Model
{
    public const TYPE_TAB_HIDDEN = 'tab_hidden';

    public const TYPE_WINDOW_BLUR = 'window_blur';

    public const TYPE_PAGE_HIDE = 'page_hide';

    public $timestamps = false;

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'attempt_id',
        'exam_id',
        'schedule_id',
        'user_id',
        'event_type',
        'warning_number',
        'details',
        'created_at',
    ];

    protected $casts = [
        'warning_number' => 'integer',
        'created_at'     => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id', 'attempt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
