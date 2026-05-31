<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lecture extends Model
{
    protected $primaryKey = 'lecture_id';

    protected $fillable = [
        'module_id',
        'title',
        'week_number',
        'lecture_date',
        'video_link',
        'slides_link',
        'notes',
        'order_index',
    ];

    protected $casts = [
        'lecture_date' => 'date',
        'week_number'  => 'integer',
        'order_index'  => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function materials()
    {
        return $this->hasMany(LectureMaterial::class, 'lecture_id', 'lecture_id');
    }
}
