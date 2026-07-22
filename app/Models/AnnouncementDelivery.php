<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDelivery extends Model
{
    use Concerns\SafelyCastsDates;

    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'announcement_id',
        'user_id',
        'read_at',
        'opened_at',
        'dismissed_at',
        'email_sent_at',
        'whatsapp_sent_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'opened_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id', 'announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isUnread(): bool
    {
        return ! $this->hasRealDateAttribute('read_at');
    }

    public function isDismissed(): bool
    {
        return $this->hasRealDateAttribute('dismissed_at');
    }
}
