<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;

class GradeItem extends Model
{
    use BelongsToChurch;

    protected $primaryKey = 'item_id';

    protected $fillable = ['category_id', 'session_id', 'title', 'max_score', 'item_date', 'description', 'ordering'];

    protected $casts = [
        'item_date' => 'date',
        'max_score' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(GradeCategory::class, 'category_id', 'category_id');
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'item_id', 'item_id');
    }

    public function gradedStudentsCount(): int
    {
        return $this->grades->whereNotNull('score')->count();
    }
}
