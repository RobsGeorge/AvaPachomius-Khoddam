<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackSurvey extends Model
{
    use BelongsToChurch;
    use Concerns\SafelyCastsDates;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $primaryKey = 'survey_id';

    public const BLOCK_KIND_EXAM = 'exam';

    public const BLOCK_KIND_PROJECT = 'project';

    protected $fillable = [
        'course_id', 'module_id', 'title', 'description', 'created_by_user_id',
        'status', 'is_mandatory', 'is_anonymous', 'due_at', 'opened_at', 'closed_at',
        'blocks_exam_id', 'blocks_project_assessment_id',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_anonymous' => 'boolean',
        'due_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function blockedExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'blocks_exam_id', 'exam_id');
    }

    public function blockedProjectAssessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'blocks_project_assessment_id', 'project_assessment_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class, 'survey_id', 'survey_id')
            ->orderBy('order_index');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FeedbackSubmission::class, 'survey_id', 'survey_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN
            && ($this->due_at === null || $this->due_at->isFuture());
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED
            || ($this->due_at !== null && $this->due_at->isPast());
    }

    public function canStaffDelete(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_OPEN], true);
    }

    public function isAnonymous(): bool
    {
        return $this->is_anonymous !== false;
    }

    /**
     * Blocking surveys hide results for a chosen exam/project, or (legacy)
     * every assessment on this survey's module when no target is stored.
     */
    public function blocksResults(): bool
    {
        return (bool) $this->is_mandatory;
    }

    /**
     * @deprecated Use blocksResults(); kept for existing views and tests.
     */
    public function blocksModuleResults(): bool
    {
        return $this->blocksResults();
    }

    public function blockedAssessmentKey(): ?string
    {
        if ($this->blocks_exam_id) {
            return self::BLOCK_KIND_EXAM.':'.$this->blocks_exam_id;
        }

        if ($this->blocks_project_assessment_id) {
            return self::BLOCK_KIND_PROJECT.':'.$this->blocks_project_assessment_id;
        }

        return null;
    }

    public function blockedAssessmentTitle(): ?string
    {
        if ($this->blocks_exam_id) {
            return $this->blockedExam?->exam_name;
        }

        if ($this->blocks_project_assessment_id) {
            return $this->blockedProjectAssessment?->title;
        }

        return null;
    }

    public function blocksExam(Exam $exam): bool
    {
        if (! $this->blocksResults() || (int) $this->course_id !== (int) $exam->course_id) {
            return false;
        }

        if ($this->blocks_exam_id) {
            return (int) $this->blocks_exam_id === (int) $exam->exam_id;
        }

        if ($this->blocks_project_assessment_id) {
            return false;
        }

        return (int) $this->module_id === (int) $exam->module_id;
    }

    public function blocksProjectAssessment(ProjectAssessment $assessment): bool
    {
        if (! $this->blocksResults() || (int) $this->course_id !== (int) $assessment->course_id) {
            return false;
        }

        if ($this->blocks_project_assessment_id) {
            return (int) $this->blocks_project_assessment_id === (int) $assessment->project_assessment_id;
        }

        if ($this->blocks_exam_id) {
            return false;
        }

        return (int) $this->module_id === (int) $assessment->module_id;
    }

    /**
     * @return array{0:?string, 1:?int}
     */
    public static function parseBlockedAssessment(?string $value): array
    {
        if (! is_string($value) || ! preg_match('/^(exam|project):(\d+)$/', $value, $matches)) {
            return [null, null];
        }

        return [$matches[1], (int) $matches[2]];
    }

    public function getRouteKeyName(): string
    {
        return 'survey_id';
    }
}
