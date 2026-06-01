<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Content;
use App\Models\Lecture;

class Module extends Model
{
    protected $table = 'modules';

    protected $primaryKey = 'module_id';

    protected $fillable = ['title', 'description'];

    public function courses()
    {
        return $this->belongsToMany(
            Course::class,
            'course_module',
            'module_id',
            'course_id',
            'module_id',
            'course_id'
        );
    }

    public function contents()
    {
        return $this->belongsToMany(
            Content::class,
            'module_content',
            'module_id',
            'content_id',
            'module_id',
            'content_id'
        );
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class, 'module_id', 'module_id')
                    ->orderBy('order_index')
                    ->orderBy('week_number');
    }
}

