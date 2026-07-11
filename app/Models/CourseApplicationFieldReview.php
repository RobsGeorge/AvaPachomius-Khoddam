<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseApplicationFieldReview extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'application_id',
        'field_key',
        'status',
        'comment',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CourseApplication::class, 'application_id');
    }
}
