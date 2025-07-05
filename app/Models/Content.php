<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;

class Content extends Model
{
    use HasFactory;

    protected $table = 'content';

    protected $primaryKey = 'content_id';

    protected $fillable = [
        'title', 
        'content_location',
        'session_title',
        'session_date',
        'lecture_name',
        'speaker_name',
        'audio_link',
        'slides_link',
        'description'
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_content', 'content_id', 'module_id');
    }

    public function feedback()
    {
        return $this->hasMany(ContentFeedback::class, 'content_id');
    }

    public function userFeedback($userId)
    {
        return $this->feedback()->where('user_id', $userId)->first();
    }
}
