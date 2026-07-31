<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakGlassGrant extends Model
{
    public $timestamps = false;

    protected $table = 'break_glass_grants';

    protected $primaryKey = 'break_glass_grant_id';

    protected $fillable = [
        'staff_id',
        'organization_id',
        'reason',
        'granted_at',
        'expires_at',
        'self_approved',
        'created_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'self_approved' => 'boolean',
        'staff_id' => 'integer',
        'organization_id' => 'integer',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function isActive(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();

        return $this->expires_at !== null && $this->expires_at->greaterThan($at);
    }

    public function scopeActive(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query->where('expires_at', '>', $at);
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
