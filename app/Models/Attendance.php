<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Session;

class Attendance extends Model
{
    use BelongsToChurch;

    public const ATTENDED_STATUSES = ['Present', 'Permission', 'Late'];

    protected $table = 'attendance';

    protected $primaryKey = 'attendance_id';

    protected $fillable = [
        'user_id', 'session_id', 'taken_by_id', 'status',
        'permission_reason', 'attendance_time', 'lock_version',
    ];

    protected $casts = [
        'attendance_time' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function getDisplaySessionDateAttribute(): ?string
    {
        $date = $this->session?->session_date;

        return $date ? $date->copy()->addHours(3)->format('Y-m-d') : null;
    }

    public function getDisplayAttendanceTimeAttribute(): ?string
    {
        if (! $this->attendance_time) {
            return null;
        }

        $local = $this->attendance_time->copy()->addHours(3);
        $suffix = (int) $local->format('H') < 12 ? 'ص' : 'م';

        return $local->format('h:i').' '.$suffix;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by_id', 'user_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }
}

