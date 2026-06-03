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
        )->withPivot([
            'start_date', 'end_date', 'order_index', 'status',
            'feedback_open', 'ended_at', 'ended_by_user_id',
        ]);
    }

    public function courseSessions()
    {
        return $this->hasMany(Session::class, 'module_id', 'module_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(
            Session::class,
            'module_session',
            'module_id',
            'session_id',
            'module_id',
            'session_id'
        )->withPivot('week_number')->orderByPivot('week_number');
    }

    public function feedback()
    {
        return $this->hasMany(ModuleFeedback::class, 'module_id', 'module_id');
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

    public function exams()
    {
        return $this->hasMany(Exam::class, 'module_id', 'module_id');
    }
}

