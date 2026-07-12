<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    public const MAX_UPLOAD_KB = 10240;

    public const MAX_UPLOAD_MB = 10;

    protected $primaryKey = 'assignment_id';
    
    protected $fillable = [
        'course_id',
        'assignment_name',
        'assignment_description',
        'total_points',
        'due_date',
        'instructions',
        'resources',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'course_id' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function isSubmissionOpen(): bool
    {
        return now()->addHours(3)->lessThanOrEqualTo($this->due_date);
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id', 'assignment_id');
    }

    /**
     * Get unique team submissions for this assignment
     * This returns only the main submissions (those without team_submission_id)
     */
    public function uniqueTeamSubmissions()
    {
        return $this->submissions()
            ->whereNull('team_submission_id')
            ->with(['user', 'teamSubmissions.user'])
            ->get();
    }

    /**
     * Get all submissions including team submissions
     * This returns all submissions, including those that are part of a team
     */
    public function allSubmissionsWithTeams()
    {
        return $this->submissions()
            ->with(['user', 'parentSubmission.user', 'teamSubmissions.user'])
            ->get();
    }
} 