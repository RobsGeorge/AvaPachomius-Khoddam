<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ChurchService extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'service';

    protected $primaryKey = 'service_id';

    protected $fillable = [
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'branding_theme',
        'status',
        'permissions_version',
    ];

    protected $casts = [
        'permissions_version' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'service_id';
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'service_id', 'service_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'service_id', 'service_id')
            ->whereNull('course_id')
            ->where('is_template', false);
    }

    public function userServiceRoles(): HasMany
    {
        return $this->hasMany(UserServiceRole::class, 'service_id', 'service_id');
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

    public function bumpPermissionsVersion(): void
    {
        $this->permissions_version = (int) $this->permissions_version + 1;
        $this->save();
    }

    public static function tableReady(): bool
    {
        return Schema::hasTable('service');
    }

    public static function defaultService(): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        return self::query()->orderBy('service_id')->first();
    }

    public static function ensureDefault(): self
    {
        $existing = self::defaultService();
        if ($existing) {
            return $existing;
        }

        return self::create([
            'title' => 'Default Service',
            'title_ar' => 'الخدمة الافتراضية',
            'title_en' => 'Default Service',
            'description' => 'Default parent service for courses.',
            'status' => self::STATUS_ACTIVE,
            'permissions_version' => 0,
        ]);
    }
}
