<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveQuizOption extends Model
{
    protected $primaryKey = 'option_id';

    protected $fillable = [
        'question_id', 'label_text', 'label_image_path', 'is_correct', 'order_index',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(LiveQuizQuestion::class, 'question_id', 'question_id');
    }
}
