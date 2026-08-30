<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anonymous peer rating. Informational only — never writes member grades.
 */
class ProjectPeerRating extends Model
{
    use BelongsToChurch;

    protected $table = 'project_peer_ratings';

    protected $primaryKey = 'project_peer_rating_id';

    protected $fillable = [
        'project_assessment_id',
        'project_id',
        'rater_user_id',
        'ratee_user_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id', 'user_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_user_id', 'user_id');
    }
}
