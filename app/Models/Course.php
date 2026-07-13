<?php

namespace App\Models;

use App\Database\CourseModulePivot;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;
use App\Models\Session;
use App\Models\Exam;
use App\Models\CourseAssessment;
use App\Models\GradeCategory;

class Course extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRADING_LOCKED = 'grading_locked';

    public const STATUS_ANNOUNCED = 'announced';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const GRACE_MODE_MANUAL = 'manual';

    protected $table = 'course';

    protected $primaryKey = 'course_id';

    protected $fillable = [
        'service_id',
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'branding_theme',
        'year',
        'default_session_start_time',
        'passing_percentage',
        'min_attendance_percentage',
        'status',
        'grading_locked_at',
        'grades_announced_at',
        'closed_at',
        'closed_by_user_id',
        'grace_marks_enabled',
        'max_grace_marks',
        'grace_eligibility_mode',
        'permissions_version',
        'roles_cloned_from_course_id',
    ];

    protected $casts = [
        'passing_percentage'        => 'float',
        'min_attendance_percentage' => 'float',
        'grading_locked_at'         => 'datetime',
        'grades_announced_at'       => 'datetime',
        'closed_at'                 => 'datetime',
        'grace_marks_enabled'       => 'boolean',
        'max_grace_marks'           => 'float',
    ];

    protected function brandingTheme(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if ($value === null || $value === '') {
                    return [];
                }

                if (is_array($value)) {
                    return $value;
                }

                try {
                    $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

                    return is_array($decoded) ? $decoded : [];
                } catch (\JsonException) {
                    return [];
                }
            },
            set: fn (?array $value) => $value === null || $value === [] ? null : json_encode($value),
        );
    }

    /** @return list<string> */
    public static function selectableStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_GRADING_LOCKED,
            self::STATUS_ANNOUNCED,
        ];
    }

    public function isSelectableForContext(): bool
    {
        return in_array($this->status, self::selectableStatuses(), true)
            || ($this->status === null || $this->status === '');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $key = $locale === 'ar' ? 'title_ar' : 'title_en';
        $localized = $this->{$key};

        if (is_string($localized) && trim($localized) !== '') {
            return trim($localized);
        }

        return (string) ($this->title ?? '');
    }

    public function localizedDescription(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $key = $locale === 'ar' ? 'description_ar' : 'description_en';
        $localized = $this->{$key};

        if (is_string($localized) && trim($localized) !== '') {
            return trim($localized);
        }

        return (string) ($this->description ?? '');
    }

    /** @return array{primary: ?string, accent: ?string} */
    public function brandingColors(): array
    {
        $theme = $this->branding_theme ?? [];
        if (! is_array($theme)) {
            $theme = [];
        }

        return [
            'primary' => $theme['primary'] ?? null,
            'accent' => $theme['accent'] ?? null,
        ];
    }

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'grace_eligibility_mode' => self::GRACE_MODE_MANUAL,
    ];

    public function sessions()
    {
        return $this->hasMany(Session::class, 'course_id', 'course_id');
    }

    public function modules()
    {
        $relation = $this->belongsToMany(
            Module::class,
            'course_module',
            'course_id',
            'module_id',
            'course_id',
            'module_id'
        );

        $pivot = CourseModulePivot::columns();
        if ($pivot !== []) {
            $relation->withPivot($pivot);
        }

        if (CourseModulePivot::hasOrderIndex()) {
            $relation->orderByPivot('order_index');
        }

        return $relation;
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'course_id', 'course_id');
    }

    public function assessments()
    {
        return $this->hasMany(CourseAssessment::class, 'course_id', 'course_id');
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_course_role',
            'course_id',
            'user_id',
            'course_id',
            'user_id'
        )->withPivot('role_id', 'user_course_role_id');
    }

    public function service()
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function userCourseRoles()
    {
        return $this->hasMany(UserCourseRole::class, 'course_id', 'course_id');
    }

    public function roles()
    {
        return $this->hasMany(Role::class, 'course_id', 'course_id');
    }

    public function gradeCategories()
    {
        return $this->hasMany(GradeCategory::class, 'course_id', 'course_id')
                    ->orderBy('ordering');
    }

    public function graduations()
    {
        return $this->hasMany(CourseGraduation::class, 'course_id', 'course_id')
            ->orderByDesc('announced_at');
    }

    public function latestGraduation()
    {
        return $this->hasOne(CourseGraduation::class, 'course_id', 'course_id')
            ->latestOfMany('announced_at');
    }

    public function certificateTemplates()
    {
        return $this->hasMany(CourseCertificateTemplate::class, 'course_id', 'course_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || $this->status === null
            || $this->status === '';
    }

    public function isGradingLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_GRADING_LOCKED,
            self::STATUS_ANNOUNCED,
            self::STATUS_CLOSED,
            self::STATUS_ARCHIVED,
        ], true);
    }

    public function areGradesAnnounced(): bool
    {
        return $this->grades_announced_at !== null
            && in_array($this->status, [
                self::STATUS_ANNOUNCED,
                self::STATUS_CLOSED,
                self::STATUS_ARCHIVED,
            ], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_ARCHIVED], true);
    }

    public function allowsGradeEditing(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function allowsStudentAssessments(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Total weighted grade for a student (0–100). Requires gradeCategories.items.grades loaded. */
    public function studentTotalGrade(int $userId): float
    {
        return round($this->gradeCategories->sum(fn ($cat) => $cat->studentContribution($userId)), 2);
    }

    public function hasGraduationCriteria(): bool
    {
        return $this->passing_percentage !== null && $this->min_attendance_percentage !== null;
    }

    public function effectivePassingPercentage(): float
    {
        return (float) ($this->passing_percentage ?? 60);
    }

    public function effectiveMinAttendancePercentage(): float
    {
        return (float) ($this->min_attendance_percentage ?? 75);
    }

    public function formattedDefaultSessionStartTime(): string
    {
        $time = $this->default_session_start_time;

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        if (is_string($time) && $time !== '') {
            return substr($time, 0, 5);
        }

        return substr((string) config('attendance.default_session_start_time', '09:00:00'), 0, 5);
    }

    public function effectiveDefaultSessionStartTime(): string
    {
        $time = $this->default_session_start_time;

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i:s');
        }

        if (is_string($time) && $time !== '') {
            return strlen($time) === 5 ? $time.':00' : $time;
        }

        $fallback = config('attendance.default_session_start_time', '09:00:00');

        return strlen($fallback) === 5 ? $fallback.':00' : $fallback;
    }
}
