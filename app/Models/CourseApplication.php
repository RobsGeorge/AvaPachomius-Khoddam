<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseApplication extends Model
{
    use BelongsToChurch;

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_NEEDS_CORRECTION = 'needs_correction';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'course_id',
        'form_id',
        'status',
        'snapshot',
        'version',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'overall_rejection_note',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CourseApplicationForm::class, 'form_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function fieldReviews(): HasMany
    {
        return $this->hasMany(CourseApplicationFieldReview::class, 'application_id');
    }

    public function rejectedFields(): HasMany
    {
        return $this->fieldReviews()->where('status', CourseApplicationFieldReview::STATUS_REJECTED);
    }

    /** @return list<string> */
    public function reviewableFieldKeys(): array
    {
        $keys = [];
        $form = $this->form ?? $this->form()->with('steps.fields')->first();

        if (! $form) {
            return array_keys($this->snapshot ?? []);
        }

        foreach ($form->steps as $step) {
            foreach ($step->fields as $field) {
                if ($field->isInput()) {
                    $keys[] = $field->field_key;
                }
            }
        }

        return $keys;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_NEEDS_CORRECTION,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }
}
