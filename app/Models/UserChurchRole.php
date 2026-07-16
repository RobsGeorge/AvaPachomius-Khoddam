<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Church-wide role grant (T3). Distinct from course/service grants — used for
 * church-admin / priest / servant assignments that are not tied to a course.
 */
class UserChurchRole extends Model
{
    protected $table = 'user_church_role';

    protected $primaryKey = 'user_church_role_id';

    public $timestamps = false;

    protected $fillable = [
        'church_id',
        'user_id',
        'role_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }
};
