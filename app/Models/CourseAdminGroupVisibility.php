<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseAdminGroupVisibility extends Model
{
    protected $table = 'course_admin_group_visibility';

    protected $fillable = [
        'permission_group_id',
        'visible_to_course_admins',
        'set_by_user_id',
    ];

    protected $casts = [
        'visible_to_course_admins' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id', 'permission_group_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id', 'user_id');
    }
}
