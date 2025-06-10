<?php

namespace App\Traits;

trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany('App\Models\Role', 'user_course_role', 'user_id', 'role_id')
                    ->withPivot('course_id');
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles->contains('role_name', $role);
        }
        
        if (is_array($role)) {
            return $this->roles->whereIn('role_name', $role)->count() > 0;
        }

        return false;
    }

    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            return $this->hasRole($roles);
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllRoles($roles)
    {
        if (is_string($roles)) {
            return $this->hasRole($roles);
        }

        return $this->roles->whereIn('role_name', $roles)->count() === count($roles);
    }
} 