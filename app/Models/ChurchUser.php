<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of a user in a church (shared global user pool — one login, member of
 * many churches). See docs/khedma-master-plan.md §5.
 */
class ChurchUser extends Model
{
    protected $table = 'church_user';

    protected $primaryKey = 'church_user_id';

    public $timestamps = false;

    protected $fillable = ['church_id', 'user_id', 'status', 'joined_at'];

    protected $casts = ['joined_at' => 'datetime'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
