<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecture extends Model
{
    use BelongsToChurch;

    protected $primaryKey = 'lecture_id';

    protected $fillable = [
        'module_id',
        'session_id',
        'title',
        'week_number',
        'lecture_date',
        'video_link',
        'slides_link',
        'slides_media_id',
        'notes',
        'order_index',
    ];

    protected $casts = [
        'lecture_date' => 'date',
        'week_number'  => 'integer',
        'order_index'  => 'integer',
        'slides_media_id' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LectureMaterial::class, 'lecture_id', 'lecture_id');
    }

    public function slidesMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'slides_media_id', 'media_id');
    }

    public function hasHostedSlides(): bool
    {
        return $this->slides_media_id !== null;
    }

    public function hasSlides(): bool
    {
        return filled($this->slides_link) || $this->hasHostedSlides();
    }

    public function slidesUrl(): ?string
    {
        if ($this->slides_media_id) {
            return $this->slidesMedia?->downloadUrl();
        }

        return filled($this->slides_link) ? $this->slides_link : null;
    }
}
