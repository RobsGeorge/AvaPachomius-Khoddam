<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\ImpersonationService;
use App\Services\People\PersonRegistryService;
use App\Services\RolePreviewService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany; 
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Attendance;
use App\Models\UserAssessment;
use App\Models\Role;
use App\Models\EventAdmin;
use App\Models\UserSystemRole;
use App\Services\CoursePermissionResolver;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;



class User extends Authenticatable
{
    use Concerns\SafelyCastsDates;
    use Notifiable, HasApiTokens, HasFactory;

    public const PHOTO_STATUS_PENDING = 'pending';

    public const PHOTO_STATUS_APPROVED = 'approved';

    public const PHOTO_STATUS_REJECTED = 'rejected';

    public const APPLICATION_STATUS_PENDING_REVIEW = 'pending_review';

    public const APPLICATION_STATUS_NEEDS_CORRECTION = 'needs_correction';

    public const APPLICATION_STATUS_APPROVED = 'approved';

    public const APPLICATION_STATUS_REJECTED = 'rejected';

    /** Legacy `user` table may lack Laravel timestamps until schema sync runs. */
    public $timestamps = false;

    protected $table = 'user';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name', 'first_name', 'second_name', 'third_name', 'profile_photo',
        'profile_photo_grace_started_at', 'profile_photo_uploaded_at',
        'profile_photo_deadline_at', 'profile_photo_status',
        'profile_photo_reviewed_at', 'profile_photo_reviewed_by_user_id', 'profile_photo_rejection_note',
        'student_onboarding_completed_at',
        'national_id', 'mobile_number',
        'email', 'job', 'date_of_birth', 'password',
        'is_verified', 'is_superadmin', 'remember_token', 'otp_code', 'otp_expires_at',
        'registration_completed', 'application_status', 'communication_locale',
        'person_id',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_verified'   => 'boolean',
        'is_superadmin' => 'boolean',
        'registration_completed' => 'boolean',
        'profile_photo_grace_started_at' => 'datetime',
        'profile_photo_uploaded_at' => 'datetime',
        'profile_photo_deadline_at' => 'datetime',
        'profile_photo_reviewed_at' => 'datetime',
        'student_onboarding_completed_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (! Schema::hasTable('people') || ! Schema::hasColumn('user', 'person_id')) {
                return;
            }
            if ($user->person_id) {
                return;
            }

            try {
                app(PersonRegistryService::class)->ensureForUser($user);
            } catch (\Throwable $e) {
                Log::warning('Failed to ensure person for user', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public static function fullNameFromParts(string $first, string $second, string $third): string
    {
        return trim(implode(' ', array_filter([$first, $second, $third], fn ($part) => $part !== '')));
    }

    public function attendancesTaken()
    {
        return $this->hasMany(Attendance::class, 'taken_by_id', 'user_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    public function userAssessments()
    {
        return $this->hasMany(UserAssessment::class, 'user_id', 'user_id');
    }

    public function submittedAssessments()
    {
        return $this->hasMany(UserAssessment::class, 'submitted_by_id', 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_course_role',
            'user_id',
            'role_id',
            'user_id',
            'role_id'
        )->withPivot('course_id', 'user_course_role_id');
    }

    public function courses()
    {
        return $this->belongsToMany(
            Course::class,
            'user_course_role',
            'user_id',
            'course_id',
            'user_id',
            'course_id'
        )->withPivot('role_id', 'user_course_role_id');
    }

    public function userCourseRoles()
    {
        return $this->hasMany(UserCourseRole::class, 'user_id', 'user_id');
    }

    public function userServiceRoles()
    {
        return $this->hasMany(UserServiceRole::class, 'user_id', 'user_id');
    }

    // Church tenancy (T0). Shared user pool + church_user membership.
    public function churches()
    {
        return $this->belongsToMany(Church::class, 'church_user', 'user_id', 'church_id', 'user_id', 'church_id')
            ->withPivot('status', 'joined_at');
    }

    public function churchMemberships()
    {
        return $this->hasMany(ChurchUser::class, 'user_id', 'user_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function belongsToChurch(int $churchId): bool
    {
        return $this->churchMemberships()
            ->where('church_id', $churchId)
            ->where('status', 'active')
            ->exists();
    }

    public function systemRoles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_system_role',
            'user_id',
            'role_id',
            'user_id',
            'role_id'
        );
    }

    public function userSystemRoles()
    {
        return $this->hasMany(UserSystemRole::class, 'user_id', 'user_id');
    }

    public function permissionsInCourse(Course $course): \Illuminate\Support\Collection
    {
        return app(CoursePermissionResolver::class)->permissionsInCourse($this, $course);
    }

    public function canInCourse(string $permission, Course $course): bool
    {
        return app(CoursePermissionResolver::class)->canInCourse($this, $permission, $course);
    }

    public function canInSystem(string $permission): bool
    {
        return app(CoursePermissionResolver::class)->canInSystem($this, $permission);
    }

    public function canInService(string $permission, ChurchService $service): bool
    {
        return app(CoursePermissionResolver::class)->canInService($this, $permission, $service);
    }

    public function permissionsInChurch(Church $church): \Illuminate\Support\Collection
    {
        return app(CoursePermissionResolver::class)->permissionsInChurch($this, $church);
    }

    public function canInChurch(string $permission, Church $church): bool
    {
        return app(CoursePermissionResolver::class)->canInChurch($this, $permission, $church);
    }

    public function canAnyInCourse(array $permissions, Course $course): bool
    {
        return app(CoursePermissionResolver::class)->canAnyInCourse($this, $permissions, $course);
    }

    public function previewRoleSlug(): ?string
    {
        if (! ($this->is_superadmin ?? false) || ! RolePreviewService::isActive()) {
            return null;
        }

        return RolePreviewService::previewRole()?->effectiveSlug();
    }

    /**
     * Learner persona (permission-based). Prefer course-scoped checks via canInCourse.
     */
    public function isStudent(?string $courseId = null): bool
    {
        $slug = $this->previewRoleSlug();
        if ($slug !== null) {
            return $slug === 'student';
        }

        $resolver = app(CoursePermissionResolver::class);

        if ($courseId) {
            $course = Course::query()->withoutGlobalScope('church')->find($courseId);

            return $course
                ? $resolver->canLearnerInCourse($this, $course)
                : false;
        }

        return $resolver->isLearnerAnywhere($this);
    }

    /**
     * Staff with instruction capabilities (not course-admin-only). Prefer canInCourse.
     */
    public function isInstructor(?string $courseId = null): bool
    {
        $slug = $this->previewRoleSlug();
        if ($slug !== null) {
            return $slug === 'instructor';
        }

        // Instructor ≈ staff without role.manage (admin has role.manage).
        if ($this->isAdmin($courseId)) {
            return false;
        }

        return $this->isInstructorOrAdmin($courseId);
    }

    /**
     * Course/system admin via role.manage (or system.role.manage).
     */
    public function isAdmin(?string $courseId = null): bool
    {
        $slug = $this->previewRoleSlug();
        if ($slug !== null) {
            if ($courseId && ! RolePreviewService::isGeneral()) {
                return $slug === 'admin'
                    && (int) $courseId === (int) session(RolePreviewService::SESSION_COURSE_ID);
            }

            return $slug === 'admin' || $this->canInSystem('system.role.manage');
        }

        $resolver = app(CoursePermissionResolver::class);

        if ($courseId) {
            $course = Course::query()->withoutGlobalScope('church')->find($courseId);

            return $course
                ? $resolver->canInCourse($this, 'role.manage', $course)
                : false;
        }

        return $resolver->isCourseAdminAnywhere($this);
    }

    /**
     * Staff bundle (instructor + admin templates) via permission keys.
     */
    public function isInstructorOrAdmin(?string $courseId = null): bool
    {
        $slug = $this->previewRoleSlug();
        if ($slug !== null) {
            if ($courseId && ! RolePreviewService::isGeneral()) {
                return in_array($slug, ['admin', 'instructor'], true)
                    && (int) $courseId === (int) session(RolePreviewService::SESSION_COURSE_ID);
            }

            return in_array($slug, ['admin', 'instructor'], true) || $this->canInSystem('system.role.manage');
        }

        $resolver = app(CoursePermissionResolver::class);

        if ($courseId) {
            $course = Course::query()->withoutGlobalScope('church')->find($courseId);

            return $course
                ? $resolver->canAnyStaffInCourse($this, $course)
                : false;
        }

        return $resolver->isStaffAnywhere($this) || $this->canInSystem('system.role.manage');
    }

    public function isEventAdmin(): bool
    {
        if (RolePreviewService::superadminBypassesPermissions($this)) {
            return true;
        }

        return $this->canInSystem('events.admin')
            || EventAdmin::where('user_id', $this->user_id)->exists();
    }

    /**
     * Review course applications queue / actions.
     * Honors system grants and course-role grants (roles hub → course admin).
     */
    public function canAccessAdminCourseApplications(?Course $course = null): bool
    {
        if ($this->is_superadmin ?? false) {
            return true;
        }

        if ($this->canInSystem('course_application.review')) {
            return true;
        }

        if ($course) {
            return $this->canInCourse('course_application.review', $course)
                || $this->isAdmin((string) $course->course_id);
        }

        return app(CoursePermissionResolver::class)
            ->canAnyInAnyCourse($this, ['course_application.review'])
            || $this->isAdmin();
    }

    /** Build / edit course application forms (course or system grant). */
    public function canAccessAdminCourseApplicationForms(?Course $course = null): bool
    {
        if ($this->is_superadmin ?? false) {
            return true;
        }

        if ($this->canInSystem('course_application.form_builder')) {
            return true;
        }

        if ($course) {
            return $this->canInCourse('course_application.form_builder', $course)
                || $this->isAdmin((string) $course->course_id);
        }

        return app(CoursePermissionResolver::class)
            ->canAnyInAnyCourse($this, ['course_application.form_builder'])
            || $this->isAdmin();
    }

    public function isBeingImpersonated(): bool
    {
        return ImpersonationService::isActive()
            && auth()->id() === $this->user_id;
    }

    public function displayName(): string
    {
        return self::fullNameFromParts(
            (string) $this->first_name,
            (string) $this->second_name,
            (string) ($this->third_name ?? '')
        );
    }

    public function hasProfilePhoto(): bool
    {
        return filled($this->profile_photo);
    }

    public function isProfilePhotoApproved(): bool
    {
        return $this->profile_photo_status === self::PHOTO_STATUS_APPROVED;
    }

    public function isProfilePhotoPending(): bool
    {
        if ($this->isProfilePhotoApproved() || $this->isProfilePhotoRejected()) {
            return false;
        }

        return $this->hasProfilePhoto()
            && ($this->profile_photo_status === self::PHOTO_STATUS_PENDING || $this->profile_photo_status === null);
    }

    public function isProfilePhotoRejected(): bool
    {
        return $this->profile_photo_status === self::PHOTO_STATUS_REJECTED;
    }

    public function needsProfilePhotoReview(): bool
    {
        return $this->isProfilePhotoPending();
    }

    public function isApplicationApproved(): bool
    {
        return $this->application_status === self::APPLICATION_STATUS_APPROVED
            && (bool) $this->is_verified;
    }

    public function registrationApplications()
    {
        return $this->hasMany(RegistrationApplication::class, 'user_id', 'user_id');
    }

    public function registrationDate(): ?Carbon
    {
        if ($this->created_at instanceof Carbon) {
            return $this->created_at;
        }

        $submitted = $this->registrationApplications()
            ->orderBy('submitted_at')
            ->value('submitted_at');

        return $submitted ? Carbon::parse($submitted) : null;
    }

    public function formattedRegistrationDate(): string
    {
        return $this->registrationDate()?->format('Y-m-d') ?? __('pages.not_available');
    }

    public function formattedMobile(): ?string
    {
        if (! $this->mobile_number) {
            return null;
        }

        return '0' . ltrim((string) $this->mobile_number, '0');
    }

    public function telUrl(): ?string
    {
        if (! $this->mobile_number) {
            return null;
        }

        return 'tel:+20' . ltrim((string) $this->mobile_number, '0');
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        if (! $this->mobile_number) {
            return null;
        }

        $url = 'https://wa.me/20' . ltrim((string) $this->mobile_number, '0');

        if ($message) {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }

    public function isBirthdayToday(?Carbon $on = null): bool
    {
        if (! $this->date_of_birth) {
            return false;
        }

        $on ??= now(config('attendance.timezone', config('app.timezone')));

        return (int) $this->date_of_birth->month === (int) $on->month
            && (int) $this->date_of_birth->day === (int) $on->day;
    }

    // Override the "must verify email" behavior since we have custom OTP
    public function hasVerifiedEmail() {
        return $this->is_verified;  // admin verified flag instead of default email_verified_at
    }

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));

        Mail::to($this->email)->send(new ResetPasswordMail($this, $resetUrl));
    }
}
