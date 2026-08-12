<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentType extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'appointment_type';

    protected $primaryKey = 'appointment_type_id';

    public function getRouteKeyName(): string
    {
        return 'appointment_type_id';
    }

    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
        'default_capacity',
        'default_duration_minutes',
        'status',
    ];

    protected $casts = [
        'default_capacity' => 'integer',
        'default_duration_minutes' => 'integer',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class, 'appointment_type_id', 'appointment_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en' && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        return (string) $this->name_ar;
    }
}
