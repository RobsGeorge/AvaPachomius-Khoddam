<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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



class User extends Authenticatable
{
    use Notifiable, HasApiTokens, HasFactory;

    protected $table = 'user';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'first_name', 'second_name', 'third_name', 'profile_photo',
        'national_id', 'mobile_number',
        'email', 'job', 'date_of_birth', 'password',
        'is_verified', 'remember_token', 'otp_code', 'otp_expires_at'
    ];

    public function attendancesTaken()
    {
        return $this->hasMany(Attendance::class, 'taken_by_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function userAssessments()
    {
        return $this->hasMany(UserAssessment::class, 'user_id');
    }

    public function submittedAssessments()
    {
        return $this->hasMany(UserAssessment::class, 'submitted_by_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_course_role', 'user_id', 'role_id')
                    ->withPivot('course_id');
    }


    public function courses()
    {
        return $this->belongsToMany(Course::class, 'user_course_role', 'user_id', 'course_id')
                    ->withPivot('role_id');
    }

    // Override the "must verify email" behavior since we have custom OTP
    public function hasVerifiedEmail() {
        return $this->is_verified;  // admin verified flag instead of default email_verified_at
    }
}
