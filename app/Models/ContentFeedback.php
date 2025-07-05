<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentFeedback extends Model
{
    use HasFactory;

    protected $table = 'content_feedback';

    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'user_id',
        'content_id',
        'lecture_rating',
        'lecture_comments',
        'speaker_rating',
        'speaker_comments',
        'general_feedback'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function content()
    {
        return $this->belongsTo(Content::class, 'content_id');
    }
} 