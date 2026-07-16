<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class EmailTemplateMeta extends Model
{
    protected $table = 'email_template_meta';

    protected $fillable = [
        'family',
        'course_id',
        'template_key',
        'default_locale',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public static function tableReady(): bool
    {
        return Schema::hasTable('email_template_meta');
    }
}
