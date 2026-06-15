<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginTrial extends Model
{
    public $timestamps = false;

    protected $table = 'login_trials';

    protected $primaryKey = 'login_trial_id';

    protected $fillable = [
        'user_id',
        'email',
        'password_attempt',
        'password_confirmation',
        'current_password',
        'context',
        'route_name',
        'url',
        'ip_address',
        'user_agent',
        'device_summary',
        'success',
        'failure_reason',
        'created_at',
    ];

    protected $casts = [
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
