<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    protected $primaryKey = 'permission_group_id';

    protected $fillable = [
        'group_key',
        'label_en',
        'label_ar',
        'sort_order',
        'scope',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_group_id', 'permission_group_id');
    }

    public function visibility()
    {
        return $this->hasOne(CourseAdminGroupVisibility::class, 'permission_group_id', 'permission_group_id');
    }

    public function label(): string
    {
        $locale = app()->getLocale();

        return ($locale === 'ar' && $this->label_ar) ? $this->label_ar : $this->label_en;
    }

    public function isVisibleToCourseAdmins(): bool
    {
        return (bool) ($this->visibility?->visible_to_course_admins ?? true);
    }
}
