<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Session;

class Attendance extends Model
{

    protected $table = 'attendance';

    protected $primaryKey = 'attendance_id';

    protected $fillable = [
        'user_id', 'session_id', 'taken_by_id', 'status', 
        'permission_reason', 'attendance_time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}

