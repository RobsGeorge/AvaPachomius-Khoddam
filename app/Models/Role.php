<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'role_id';

    protected $fillable = ['role_name', 'role_decription'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_course_role', 'role_id', 'user_id')
                    ->withPivot('course_id');
    }
}

