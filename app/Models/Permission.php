<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $primaryKey = 'permission_id';

    protected $fillable = [
        'permission_group_id',
        'key',
        'type',
        'label_en',
        'label_ar',
        'description',
        'route_names',
        'http_methods',
        'nav_key',
        'is_system_only',
        'deprecated_at',
    ];

    protected $casts = [
        'route_names' => 'array',
        'http_methods' => 'array',
        'is_system_only' => 'boolean',
        'deprecated_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id', 'permission_group_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission',
            'permission_id',
            'role_id',
            'permission_id',
            'role_id'
        );
    }

    public function label(): string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' && $this->label_ar) ? $this->label_ar : $this->label_en;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated_at !== null;
    }
}
