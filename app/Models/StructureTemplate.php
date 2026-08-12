<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structure template registry (master-plan §15). Behavior binds to anchors, not level names.
 */
class StructureTemplate extends Model
{
    public const KEY_EDUCATIONAL_STANDARD = 'educational_standard';

    public const KEY_MEETING_FLAT = 'meeting_flat';

    public const KEY_CARE_SECTOR = 'care_sector';

    protected $table = 'structure_templates';

    protected $primaryKey = 'structure_template_id';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'levels',
        'anchors',
        'custom_field_defs',
    ];

    protected $casts = [
        'levels' => 'array',
        'anchors' => 'array',
        'custom_field_defs' => 'array',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(ChurchService::class, 'structure_template_id', 'structure_template_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar' && filled($this->name_ar)) {
            return $this->name_ar;
        }

        if ($locale === 'en' && filled($this->name_en)) {
            return $this->name_en;
        }

        return $this->name_en ?: $this->name_ar ?: $this->key;
    }

    public static function byKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }
}
