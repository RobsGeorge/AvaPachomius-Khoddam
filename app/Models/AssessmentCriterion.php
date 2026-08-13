<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCriterion extends Model
{
    use BelongsToChurch;

    protected $table = 'assessment_criteria';

    protected $primaryKey = 'criterion_id';

    protected $fillable = [
        'church_id',
        'key',
        'label_en',
        'label_ar',
        'weight',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'integer',
        'order_index' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(ModuleStudentAssessmentScore::class, 'criterion_id', 'criterion_id');
    }

    public function label(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar' && filled($this->label_ar)) {
            return $this->label_ar;
        }

        return $this->label_en ?: $this->label_ar ?: $this->key;
    }

    public function getRouteKeyName(): string
    {
        return 'criterion_id';
    }
}
