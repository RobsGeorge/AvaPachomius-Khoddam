<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CourseCertificate extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'course_graduation_student_id',
        'user_id',
        'course_id',
        'certificate_uuid',
        'issued_at',
        'pdf_path',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourseCertificate $certificate) {
            if (! filled($certificate->certificate_uuid)) {
                $certificate->certificate_uuid = (string) Str::uuid();
            }
            if ($certificate->issued_at === null) {
                $certificate->issued_at = now();
            }
        });
    }

    public function graduationStudent(): BelongsTo
    {
        return $this->belongsTo(CourseGraduationStudent::class, 'course_graduation_student_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }
}
