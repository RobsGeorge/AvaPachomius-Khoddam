<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackAnswer extends Model
{
    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'submission_id', 'question_id', 'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id', 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'question_id', 'question_id');
    }

    /**
     * @return list<string>
     */
    public function decodedValues(): array
    {
        if ($this->value === null || $this->value === '') {
            return [];
        }

        $raw = (string) $this->value;
        if (str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map(static fn ($item) => (string) $item, $decoded));
            }
        }

        return [$raw];
    }

    public function displayValue(): string
    {
        $values = $this->decodedValues();

        return $values === [] ? '—' : implode(' · ', $values);
    }
}
