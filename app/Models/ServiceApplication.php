<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceApplication extends Model
{
    public const STATUS_PENDING = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'service_applications';

    protected $primaryKey = 'service_application_id';

    protected $fillable = [
        'user_id',
        'service_id',
        'form_id',
        'status',
        'snapshot',
        'admin_note',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ServiceApplicationForm::class, 'form_id', 'service_application_form_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'service_application_id';
    }
}
