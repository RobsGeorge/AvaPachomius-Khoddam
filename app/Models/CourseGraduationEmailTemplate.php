<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseGraduationEmailTemplate extends Model
{
    public const KEY_GRADUATION_ANNOUNCED = 'graduation_announced';

    public const KEY_CERTIFICATE_ISSUED = 'certificate_issued';

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
            self::KEY_GRADUATION_ANNOUNCED,
            self::KEY_CERTIFICATE_ISSUED,
        ];
    }
}
