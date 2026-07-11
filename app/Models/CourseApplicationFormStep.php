<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseApplicationFormStep extends Model
{
    protected $fillable = [
        'form_id',
        'title',
        'description',
        'order_index',
    ];

    protected $casts = [
        'order_index' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CourseApplicationForm::class, 'form_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CourseApplicationFormField::class, 'step_id')->orderBy('order_index');
    }
}
