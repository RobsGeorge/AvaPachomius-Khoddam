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
        'profile_photo_gate_enabled_at' => 'datetime',
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

        return static::query()->firstOrCreate(['id' => 1], array_filter([
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled' => true,
            'profile_photo_gate_enabled_at' => Schema::hasColumn('portal_settings', 'profile_photo_gate_enabled_at')
                ? now()
                : null,
        ], fn ($value) => $value !== null));
    }
}
