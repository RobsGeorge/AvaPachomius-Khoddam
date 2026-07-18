<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    use BelongsToChurch;

    protected $primaryKey = 'grade_id';

    protected $fillable = ['item_id', 'user_id', 'score', 'notes', 'graded_by_id', 'graded_at'];

    protected $casts = [
        'score'      => 'float',
        'graded_at'  => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(GradeItem::class, 'item_id', 'item_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function gradedBy()
    {
        return $this->belongsTo(User::class, 'graded_by_id', 'user_id');
    }
}
