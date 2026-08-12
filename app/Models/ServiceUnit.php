<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hierarchical unit under a service (T8a). Dual-writes to course via nullable course_id.
 */
class ServiceUnit extends Model
{
    use BelongsToChurch;

    protected $table = 'service_units';

    protected $primaryKey = 'service_unit_id';

    protected $fillable = [
        'service_id',
        'church_id',
        'level_key',
        'parent_id',
        'title',
        'title_ar',
        'title_en',
        'sort_order',
        'course_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'service_unit_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'service_unit_id');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar' && filled($this->title_ar)) {
            return $this->title_ar;
        }
        if ($locale === 'en' && filled($this->title_en)) {
            return $this->title_en;
        }

        return $this->title;
    }
}
