<?php

namespace App\Models;

use App\Database\LegacySchemaSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PortalSettings extends Model
{
    protected $table = 'portal_settings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'profile_photo_grace_days',
        'profile_photo_gate_enabled',
        'profile_photo_gate_enabled_at',
    ];

    protected $casts = [
        'profile_photo_grace_days' => 'integer',
        'profile_photo_gate_enabled' => 'boolean',
        // Do not cast profile_photo_gate_enabled_at: MySQL zero-dates throw on datetime cast
        // and this value is read on every authenticated page via the photo gate.
    ];

    public static function current(): self
    {
        LegacySchemaSync::ensurePortalSettingsSchema();

        if (! Schema::hasTable('portal_settings')) {
            return new static([
                'id' => 1,
                'profile_photo_grace_days' => 3,
                'profile_photo_gate_enabled' => true,
            ]);
        }

        $settings = static::query()->firstOrCreate(['id' => 1], array_filter([
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled' => true,
            'profile_photo_gate_enabled_at' => Schema::hasColumn('portal_settings', 'profile_photo_gate_enabled_at')
                ? now()
                : null,
        ], fn ($value) => $value !== null));

        $rawEnabledAt = $settings->getAttributes()['profile_photo_gate_enabled_at'] ?? null;
        if (is_string($rawEnabledAt) && str_starts_with($rawEnabledAt, '0000-00-00')) {
            $settings->forceFill(['profile_photo_gate_enabled_at' => null])->save();
        }

        return $settings;
    }
}
