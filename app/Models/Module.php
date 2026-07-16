<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use App\Database\CourseModulePivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Content;
use App\Models\Lecture;

class Module extends Model
{
    use BelongsToChurch;

    protected $table = 'modules';

    protected $primaryKey = 'module_id';

    /** Legacy `modules` table has no Laravel timestamps. */
    public $timestamps = false;

    protected $fillable = ['title', 'description'];

    public function courses()
    {
        $relation = $this->belongsToMany(
            Course::class,
            'course_module',
            'module_id',
            'course_id',
            'module_id',
            'course_id'
        );

        $pivot = CourseModulePivot::columns();
        if ($pivot !== []) {
            $relation->withPivot($pivot);
        }

        if (CourseModulePivot::hasOrderIndex()) {
            $relation->orderByPivot('order_index');
        }

        return $relation;
    }

    public function courseSessions()
    {
        return $this->hasMany(Session::class, 'module_id', 'module_id')
            ->orderBy('week_number')
            ->orderBy('session_date');
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

