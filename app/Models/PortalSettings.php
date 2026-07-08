<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSettings extends Model
{
    protected $table = 'portal_settings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'profile_photo_grace_days',
        'profile_photo_gate_enabled',
        'profile_photo_gate_enabled_at',
        'theme_colors_draft',
        'theme_colors_published',
        'theme_colors_published_at',
        'theme_colors_published_by_user_id',
    ];

    protected $casts = [
        'profile_photo_grace_days' => 'integer',
        'profile_photo_gate_enabled' => 'boolean',
        'profile_photo_gate_enabled_at' => 'datetime',
        'theme_colors_draft' => 'array',
        'theme_colors_published' => 'array',
        'theme_colors_published_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled' => true,
            'profile_photo_gate_enabled_at' => now(),
        ]);
    }
}
