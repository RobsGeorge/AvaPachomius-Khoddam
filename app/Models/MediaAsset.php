<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use BelongsToChurch;
    use SoftDeletes;

    public const CONTEXT_CURRICULUM = 'curriculum';

    protected $primaryKey = 'media_id';

    protected $fillable = [
        'church_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'context',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function lectureSlides(): HasOne
    {
        return $this->hasOne(Lecture::class, 'slides_media_id', 'media_id');
    }

    public function lectureMaterial(): HasOne
    {
        return $this->hasOne(LectureMaterial::class, 'media_id', 'media_id');
    }

    public function downloadRouteName(): string
    {
        return 'curriculum.media.download';
    }

    public function downloadUrl(): string
    {
        return route($this->downloadRouteName(), $this->media_id);
    }
}
