<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleStudentAssessment extends Model
{
    use BelongsToChurch;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $table = 'module_student_assessments';

    protected $primaryKey = 'assessment_id';

    protected $fillable = [
        'church_id',
        'course_id',
        'module_id',
        'user_id',
        'total_score',
        'status',
        'assessed_by_user_id',
    ];

    protected $casts = [
        'total_score' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id', 'user_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ModuleStudentAssessmentScore::class, 'assessment_id', 'assessment_id');
    }

    public function getRouteKeyName(): string
    {
        return 'assessment_id';
    }
}
