<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Content;

class Module extends Model
{
    protected $table = 'module';

    protected $primaryKey = 'module_id';

    protected $fillable = ['title', 'description'];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_module', 'module_id', 'course_id');
    }

    public function contents()
    {
        return $this->belongsToMany(Content::class, 'module_content', 'module_id', 'content_id');
    }
}

