<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestionOption extends Model
{
    protected $primaryKey = 'option_id';

    protected $fillable = [
        'question_id',
        'label',
        'is_correct',
        'order_index',
    ];

    protected $casts = [
        'is_correct'  => 'boolean',
        'order_index' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id', 'question_id');
    }
}
