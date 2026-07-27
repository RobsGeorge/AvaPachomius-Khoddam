<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChurchSite extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'church_site';

    protected $primaryKey = 'church_site_id';

    public function getRouteKeyName(): string
    {
        return 'church_site_id';
    }

    protected $fillable = [
        'theme_draft',
        'theme_published',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'theme_draft' => 'array',
        'theme_published' => 'array',
        'published_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChurchSiteSection::class, 'church_site_id', 'church_site_id')
            ->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
