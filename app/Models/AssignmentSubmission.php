<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentSubmission extends Model
{
    protected $primaryKey = 'submission_id';
    
    protected $fillable = [
        'assignment_id',
        'user_id',
        'submission_content',
        'file_path',
        'submitted_at',
        'points_earned',
        'feedback',
        'submitted_at',
        'team_submission_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'points_earned' => 'integer',
    ];

    protected $table = 'assignment_submission';

    public function assignment()
    {
        return $this->belongsTo(Assignment::class, 'assignment_id', 'assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the parent submission that this submission belongs to (if it's a team submission)
     */
    public function parentSubmission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'team_submission_id');
    }

    /**
     * Get all submissions that are part of this team submission
     */
    public function teamSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'team_submission_id');
    }

    /**
     * Get all team members for this submission
     */
    public function teamMembers()
    {
        if ($this->team_submission_id) {
            // If this is a child submission, get all submissions from the parent
            return AssignmentSubmission::where('team_submission_id', $this->team_submission_id)
                ->orWhere('id', $this->team_submission_id)
                ->with('user')
                ->get()
                ->pluck('user');
        } else {
            // If this is a parent submission, get all submissions including itself
            return AssignmentSubmission::where('team_submission_id', $this->id)
                ->orWhere('id', $this->id)
                ->with('user')
                ->get()
                ->pluck('user');
        }
    }

    /**
     * Check if this submission is part of a team
     */
    public function isTeamSubmission(): bool
    {
        return $this->team_submission_id !== null || $this->teamSubmissions()->exists();
    }

    /**
     * Get the main submission for a team submission
     */
    public function getMainSubmission()
    {
        if ($this->team_submission_id) {
            return $this->parentSubmission;
        }
        return $this;
    }
} 