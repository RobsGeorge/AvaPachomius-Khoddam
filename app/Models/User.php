<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\ImpersonationService;
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
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;



class User extends Authenticatable
{
    use Notifiable, HasApiTokens, HasFactory;

    /** Legacy `user` table may lack Laravel timestamps until schema sync runs. */
    public $timestamps = false;

    protected $table = 'user';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name', 'first_name', 'second_name', 'third_name', 'profile_photo',
        'national_id', 'mobile_number',
        'email', 'job', 'date_of_birth', 'password',
        'is_verified', 'is_superadmin', 'remember_token', 'otp_code', 'otp_expires_at',
        'registration_completed',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_verified'   => 'boolean',
        'is_superadmin' => 'boolean',
        'registration_completed' => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

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

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('role_name', $roleName);
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->whereIn('role_name', $roleNames)->isNotEmpty();
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
