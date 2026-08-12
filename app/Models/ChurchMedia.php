<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchMedia extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'church_media';

    protected $primaryKey = 'church_media_id';

    public function getRouteKeyName(): string
    {
        return 'church_media_id';
    }

    protected $fillable = [
        'path',
        'alt_ar',
        'alt_en',
        'width',
        'height',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function publicUrl(): string
    {
        return asset('storage/'.$this->path);
    }

    public function localizedAlt(): ?string
    {
        $locale = app()->getLocale();

        if ($locale === 'ar' && filled($this->alt_ar)) {
            return $this->alt_ar;
        }

        if ($locale !== 'ar' && filled($this->alt_en)) {
            return $this->alt_en;
        }

        return $this->alt_ar ?: $this->alt_en;
    }
}
