<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not tenant-scoped: a registration application has no church_id yet — the church
 * is provisioned later by a superadmin after approval.
 */
class ChurchApplication extends Model
{
    public const STATUS_PENDING = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'church_applications';

    protected $primaryKey = 'church_application_id';

    protected $fillable = [
        'requested_name',
        'requested_short_name',
        'place_district',
        'place_governorate',
        'place_country_code',
        'contact_name',
        'contact_email',
        'contact_mobile',
        'message',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'admin_note',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getRouteKeyName(): string
    {
        return 'church_application_id';
    }
}
