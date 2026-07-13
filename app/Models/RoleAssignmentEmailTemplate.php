<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleAssignmentEmailTemplate extends Model
{
    public const KEY_COURSE_ROLE_ASSIGNED = 'course_role_assigned';

    public const KEY_SYSTEM_ROLE_ASSIGNED = 'system_role_assigned';

    protected $fillable = [
        'template_key',
        'locale',
        'subject',
        'body_html',
    ];

    public static function keys(): array
    {
        return [
            self::KEY_COURSE_ROLE_ASSIGNED,
            self::KEY_SYSTEM_ROLE_ASSIGNED,
        ];
    }

    public static function placeholders(): array
    {
        return ['name', 'role_name', 'course_title', 'portal_url'];
    }
}
