<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchSiteSection extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'church_site_section';

    protected $primaryKey = 'church_site_section_id';

    public function getRouteKeyName(): string
    {
        return 'church_site_section_id';
    }

    protected $fillable = [
        'church_site_id',
        'type',
        'sort_order',
        'enabled_draft',
        'enabled_published',
        'content_draft',
        'content_published',
    ];

    protected $casts = [
        'enabled_draft' => 'boolean',
        'enabled_published' => 'boolean',
        'content_draft' => 'array',
        'content_published' => 'array',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ChurchSite::class, 'church_site_id', 'church_site_id');
    }
}
