<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRevision extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_PUBLISHED = 'published';

    public const ACTION_REPUBLISHED = 'republished';

    public const ACTION_EMAIL_RESENT = 'email_resent';

    public const ACTION_WHATSAPP_DISPATCHED = 'whatsapp_dispatched';

    public $timestamps = false;

    protected $primaryKey = 'revision_id';

    protected $fillable = [
        'announcement_id',
        'user_id',
        'action',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id', 'announcement_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
