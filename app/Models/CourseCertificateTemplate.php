<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCertificateTemplate extends Model
{
    protected $fillable = [
        'course_id',
        'locale',
        'name',
        'body_html',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    /** @return list<string> */
    public static function placeholders(): array
    {
        return [
            'student_name',
            'course_title',
            'course_year',
            'final_grade',
            'letter_grade',
            'graduation_date',
            'certificate_id',
        ];
    }
}
