<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;

class LectureMaterial extends Model
{
    use BelongsToChurch;

    protected $primaryKey = 'material_id';

    protected $fillable = [
        'lecture_id',
        'title',
        'link',
    ];

    public function lecture()
    {
        return $this->belongsTo(Lecture::class, 'lecture_id', 'lecture_id');
    }
}
