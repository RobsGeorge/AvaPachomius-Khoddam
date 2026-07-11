<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseApplicationReviewTemplate extends Model
{
    public const KEY_RECEIVED = 'course_application_received';

    public const KEY_NEEDS_CORRECTION = 'course_application_needs_correction';

    public const KEY_APPROVED = 'course_application_approved';

    public const KEY_REJECTED = 'course_application_rejected';

    protected $fillable = [
        'course_id',
        'template_key',
        'locale',
        'subject',
        'body_html',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            self::KEY_RECEIVED,
            self::KEY_NEEDS_CORRECTION,
            self::KEY_APPROVED,
            self::KEY_REJECTED,
        ];
    }
}
