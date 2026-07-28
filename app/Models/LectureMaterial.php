<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LectureMaterial extends Model
{
    use BelongsToChurch;

    public const SOURCE_EXTERNAL_LINK = 'external_link';

    public const SOURCE_HOSTED_FILE = 'hosted_file';

    protected $primaryKey = 'material_id';

    protected $fillable = [
        'lecture_id',
        'title',
        'source_type',
        'link',
        'media_id',
    ];

    protected $casts = [
        'media_id' => 'integer',
    ];

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'lecture_id', 'lecture_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id', 'media_id');
    }

    public function isHostedFile(): bool
    {
        return $this->source_type === self::SOURCE_HOSTED_FILE && $this->media_id !== null;
    }

    public function isExternalLink(): bool
    {
        return ! $this->isHostedFile() && filled($this->link);
    }

    public function hasContent(): bool
    {
        return $this->isHostedFile() || filled($this->link);
    }

    public function accessUrl(): ?string
    {
        if ($this->isHostedFile()) {
            $media = $this->relationLoaded('media') ? $this->media : $this->media()->first();

            return $media?->downloadUrl();
        }

        return filled($this->link) ? $this->link : null;
    }
}
