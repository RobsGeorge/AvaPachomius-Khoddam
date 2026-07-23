<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChurchService extends Model
{
    use BelongsToChurch;

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
        'slug',
        'structure_template_id',
        'level_labels',
        'enabled_levels',
        'custom_field_defs',
    ];

    protected $casts = [
        'permissions_version' => 'integer',
        'level_labels' => 'array',
        'enabled_levels' => 'array',
        'custom_field_defs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChurchService $service) {
            if (! Schema::hasColumn($service->getTable(), 'slug')) {
                return;
            }
            if (filled($service->slug)) {
                return;
            }
            $service->slug = static::uniqueSlugCandidate(
                (string) ($service->title_en ?: $service->title ?: 'service'),
                $service->church_id
            );
        });
    }

    /**
     * T8b: canonical public URLs use slug. Numeric legacy params still resolve
     * so RedirectNumericServiceToSlug can 301 to the slug URL.
     */
    public function getRouteKeyName(): string
    {
        if (Schema::hasColumn($this->getTable(), 'slug')) {
            return 'slug';
        }

        return 'service_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'slug' && is_numeric($value) && ctype_digit((string) $value)) {
            return $this->resolveRouteBindingQuery($this->newQuery(), $value, $this->getKeyName())
                ->first();
        }

        return $this->resolveRouteBindingQuery($this->newQuery(), $value, $field)
            ->first();
    }

    public static function uniqueSlugCandidate(string $source, mixed $churchId = null): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'service';
        }
        $base = mb_substr($base, 0, 70);

        $candidate = $base;
        $i = 2;
        while (static::query()
            ->when(
                $churchId !== null && Schema::hasColumn((new static)->getTable(), 'church_id'),
                fn ($q) => $q->where('church_id', $churchId)
            )
            ->where('slug', $candidate)
            ->exists()
        ) {
            $candidate = mb_substr($base, 0, 60).'-'.$i;
            $i++;
        }

        return $candidate;
    }

    public function structureTemplate(): BelongsTo
    {
        return $this->belongsTo(StructureTemplate::class, 'structure_template_id', 'structure_template_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ServiceUnit::class, 'service_id', 'service_id')
            ->orderBy('sort_order')
            ->orderBy('service_unit_id');
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

        if ($this->usesTranslatableDefaultName()) {
            $translated = trans('service.default_name', [], $locale);
            if (is_string($translated) && $translated !== '' && $translated !== 'service.default_name') {
                return $translated;
            }
        }

        if ($locale === 'ar' && filled($this->title_ar)) {
            return $this->title_ar;
        }
        if ($locale === 'en' && filled($this->title_en)) {
            return $this->title_en;
        }

        return $this->title;
    }

    /**
     * Seeded Default Service should resolve its label from lang files so
     * Translation Management (group: service, key: default_name) controls display.
     */
    public function usesTranslatableDefaultName(): bool
    {
        if ($this->title === 'Default Service' || $this->title_en === 'Default Service') {
            return true;
        }

        return in_array($this->title_ar, ['الخدمة الافتراضية', 'الخدمة الاساسية'], true);
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
            'title' => trans('service.default_name', [], 'en'),
            'title_ar' => trans('service.default_name', [], 'ar'),
            'title_en' => trans('service.default_name', [], 'en'),
            'description' => trans('service.default_description', [], 'en'),
            'status' => self::STATUS_ACTIVE,
            'permissions_version' => 0,
        ]);
    }
}
